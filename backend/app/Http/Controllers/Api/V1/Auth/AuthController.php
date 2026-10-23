<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\DataTransferObjects\Auth\LoginCredentials;
use App\DataTransferObjects\Auth\RegistrationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register(
            RegistrationData::fromRequest($request->validated(), $request->deviceName())
        );

        $this->establishSession($request, $result->user, $result->plainTextToken);

        return $this->tokenAwareResponse($result->user, $result->plainTextToken, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            LoginCredentials::fromRequest($request->validated(), $request->deviceName())
        );

        $this->establishSession($request, $result->user, $result->plainTextToken);

        return $this->tokenAwareResponse($result->user, $result->plainTextToken);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->user());

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    private function establishSession(Request $request, User $user, ?string $plainTextToken): void
    {
        if ($plainTextToken !== null || ! $request->hasSession()) {
            return;
        }

        $guard = Auth::guard('web');

        if ($guard instanceof StatefulGuard) {
            $guard->login($user);
        }

        $request->session()->regenerate();
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadCount('tokens');

        return response()->json([
            'data' => new UserResource($user),
            'meta' => [
                'active_tokens' => $user->tokens_count,
            ],
        ]);
    }

    private function tokenAwareResponse(User $user, ?string $plainTextToken, int $status = Response::HTTP_OK): JsonResponse
    {
        $user->loadCount('tokens');

        return response()->json([
            'data' => new UserResource($user),
            'token' => $plainTextToken,
            'token_type' => $plainTextToken !== null ? 'Bearer' : null,
            'meta' => [
                'active_tokens' => $user->tokens_count,
            ],
        ], $status);
    }
}
