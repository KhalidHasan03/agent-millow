<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;

final class AccessTokenService
{
    public function issue(User $user, string $deviceName): AuthenticationResult
    {
        $this->pruneToLimit($user);

        $expiresAt = $this->expiresAt();

        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return new AuthenticationResult($user, $token->plainTextToken);
    }

    public function issueTokenForSession(User $user, string $deviceName): AuthenticationResult
    {
        return new AuthenticationResult($user, null);
    }

    public function revokeAll(User $user): int
    {
        return $user->tokens()->delete();
    }

    private function expiresAt(): ?Carbon
    {
        $minutes = config('agent_millow.tokens.expiration_minutes');

        if (! is_numeric($minutes)) {
            return null;
        }

        return now()->addMinutes((int) $minutes);
    }

    private function pruneToLimit(User $user): void
    {
        $limit = (int) config('agent_millow.tokens.max_active_per_user', 20);

        if ($limit <= 0) {
            return;
        }

        $count = $user->tokens()->count();

        if ($count < $limit) {
            return;
        }

        $user->tokens()
            ->orderBy('created_at')
            ->limit($count - $limit + 1)
            ->get()
            ->each(fn ($token) => $token->delete());
    }
}
