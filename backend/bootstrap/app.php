<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceRequestSize;
use App\Http\Middleware\ForceJsonForApi;
use App\Http\Middleware\SecurityHeaders;
use App\Providers\Llm\Exceptions\ProviderAuthenticationException;
use App\Providers\Llm\Exceptions\ProviderException;
use App\Providers\Llm\Exceptions\ProviderNotConfiguredException;
use App\Providers\Llm\Exceptions\ProviderRateLimitException;
use App\Providers\Llm\Exceptions\ProviderTimeoutException;
use App\Providers\Llm\Exceptions\UnknownProviderException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->prepend(ForceJsonForApi::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(EnforceRequestSize::class);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'request_id' => $request->attributes->get('request_id'),
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'This action is unauthorized.',
                'request_id' => $request->attributes->get('request_id'),
            ], 403);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
                'request_id' => $request->attributes->get('request_id'),
            ], $exception->status);
        });

        $exceptions->render(function (UnknownProviderException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The selected provider is not available.',
                'request_id' => $request->attributes->get('request_id'),
            ], 422);
        });

        $exceptions->render(function (ProviderNotConfiguredException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The selected provider is not configured.',
                'request_id' => $request->attributes->get('request_id'),
            ], 503);
        });

        $exceptions->render(function (ProviderAuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The provider request could not be completed.',
                'request_id' => $request->attributes->get('request_id'),
            ], 503);
        });

        $exceptions->render(function (ProviderRateLimitException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The provider is rate limiting requests.',
                'request_id' => $request->attributes->get('request_id'),
            ], 429);
        });

        $exceptions->render(function (ProviderTimeoutException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The provider request timed out.',
                'request_id' => $request->attributes->get('request_id'),
            ], 504);
        });

        $exceptions->render(function (ProviderException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'The provider request failed.',
                'request_id' => $request->attributes->get('request_id'),
            ], 502);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Request failed.',
                'request_id' => $request->attributes->get('request_id'),
            ], $exception->getStatusCode());
        });
    })->create();
