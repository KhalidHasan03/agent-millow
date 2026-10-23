<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
    'health' => url('/api/v1/health'),
]));
