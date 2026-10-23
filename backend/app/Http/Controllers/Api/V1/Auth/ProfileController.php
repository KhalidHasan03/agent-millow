<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionService $sessions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            if (isset($data['name'])) {
                $user->name = (string) $data['name'];
            }

            if (isset($data['email'])) {
                $normalized = mb_strtolower(trim((string) $data['email']));

                if ($normalized !== $user->email) {
                    if (User::query()->where('email', $normalized)->whereKeyNot($user->getKey())->exists()) {
                        throw ValidationException::withMessages([
                            'email' => ['The email address is already registered.'],
                        ]);
                    }

                    $user->email = $normalized;
                    $user->email_verified_at = null;
                }
            }

            $user->save();
        });

        return response()->json([
            'data' => new UserResource($user->refresh()),
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', new PasswordPolicy],
        ]);

        $user = $request->user();

        if (! Hash::check((string) $validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        DB::transaction(function () use ($request, $user, $validated): void {
            $user->password = (string) $validated['password'];
            $user->save();

            $this->sessions->logoutEverywhere($user, $this->currentSessionId($request));

            $this->audit->record('auth.password_changed', $user, $user, [], $request);
        });

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check((string) $validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password does not match your current password.'],
            ]);
        }

        DB::transaction(function () use ($request, $user): void {
            $this->audit->record('auth.deleted', $user, $user, [], $request);

            $user->tokens()->delete();
            $user->delete();
        });

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    private function currentSessionId(Request $request): ?string
    {
        return $request->hasSession() ? $request->session()->getId() : null;
    }
}
