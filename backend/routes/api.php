<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Audit\AuditController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Conversations\ConversationController;
use App\Http\Controllers\Api\V1\Conversations\MessageController;
use App\Http\Controllers\Api\V1\Conversations\ProviderController;
use App\Http\Controllers\Api\V1\Conversations\StreamController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Memories\MemoryController;
use App\Http\Controllers\Api\V1\Tools\ToolController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('register');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('user', [AuthController::class, 'user'])->name('user');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('me')->name('me.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::put('password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:sensitive')
            ->name('password');
        Route::delete('/', [ProfileController::class, 'destroy'])
            ->middleware('throttle:sensitive')
            ->name('destroy');
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('providers', [ProviderController::class, 'index'])->name('providers.index');

        Route::apiResource('conversations', ConversationController::class);

        Route::prefix('conversations/{conversation}')->name('conversations.')->group(function (): void {
            Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
            Route::post('messages', [MessageController::class, 'store'])
                ->middleware('throttle:chat')
                ->name('messages.store');
            Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

            Route::get('stream', StreamController::class)
                ->middleware('throttle:chat')
                ->name('stream');
        });

        Route::apiResource('memories', MemoryController::class)
            ->middleware('throttle:memory');

        Route::get('tools', [ToolController::class, 'index'])->name('tools.index');
        Route::post('tools/call', [ToolController::class, 'store'])
            ->middleware('throttle:tool')
            ->name('tools.call');
        Route::get('tools/calls/{toolCall}', [ToolController::class, 'show'])->name('tools.calls.show');
        Route::post('tools/calls/{toolCall}/approve', [ToolController::class, 'approve'])
            ->middleware('throttle:tool')
            ->name('tools.calls.approve');
        Route::post('tools/calls/{toolCall}/reject', [ToolController::class, 'reject'])
            ->middleware('throttle:tool')
            ->name('tools.calls.reject');

        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    });
});
