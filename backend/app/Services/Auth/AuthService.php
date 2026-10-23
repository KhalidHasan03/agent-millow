<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DataTransferObjects\Auth\LoginCredentials;
use App\DataTransferObjects\Auth\RegistrationData;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    public function __construct(
        private readonly AccessTokenService $tokens,
        private readonly BetaAccessPolicy $betaAccess,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function register(RegistrationData $data): AuthenticationResult
    {
        $this->betaAccess->assertMayAuthenticate($data->email);
        $this->betaAccess->assertRegistrationEnabled();

        $email = $this->normalizeEmail($data->email);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['The email address is already registered.'],
            ]);
        }

        $user = DB::transaction(function () use ($data, $email): User {
            return User::query()->create([
                'name' => $data->name,
                'email' => $email,
                'password' => $data->password,
                'role' => UserRole::User,
            ]);
        });

        $this->recordLogin($user, $data->deviceName);
        $this->audit->record('auth.registered', $user, $user, ['email' => $user->email]);

        return $this->tokens->issueTokenForSession($user, $data->deviceName);
    }

    /**
     * @throws ValidationException
     */
    public function login(LoginCredentials $data): AuthenticationResult
    {
        $this->betaAccess->assertMayAuthenticate($data->email);

        $email = $this->normalizeEmail($data->email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($data->password, $user->password)) {
            $this->logLoginFailure($email, $user);
            $this->audit->record('auth.failed', $user, null, ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $this->recordLogin($user, $data->deviceName);
        $this->audit->record('auth.login', $user, $user, ['email' => $user->email]);

        if ($data->issueToken) {
            return $this->tokens->issue($user, $data->deviceName);
        }

        return $this->tokens->issueTokenForSession($user, $data->deviceName);
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof Model) {
            $token->delete();
        }

        $this->audit->record('auth.logout', $user, $user);
    }

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function logLoginFailure(string $email, ?User $user): void
    {
        Log::warning('auth.login_failed', [
            'reason' => $user === null ? 'unknown_account' : 'invalid_password',
            'email_hash' => substr(hash('sha256', $email), 0, 16),
            'user_id' => $user?->getKey(),
            'ip' => $this->currentIp(),
        ]);
    }

    private function currentIp(): ?string
    {
        $request = app()->bound('request') ? request() : null;

        return $request instanceof Request ? $request->ip() : null;
    }

    private function recordLogin(User $user, string $deviceName): void
    {
        $payload = [
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ];

        if (! $user->exists) {
            return;
        }

        $user->forceFill($payload)->save();
    }
}
