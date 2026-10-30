<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/email/verify/{id}/{hash}', EmailVerificationController::class)
    ->middleware('signed')
    // Only match user IDs containing digits, so invalid values never reach the controller.
    ->whereNumber('id')
    // SHA-1 produces exactly 40 lowercase hexadecimal characters (0-9 and a-f).
    ->where('hash', '[a-f0-9]{40}')
    ->name('verification.verify');

Route::post('/v1/register', action: [AuthController::class, 'register']);

Route::post('/v1/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::get('/v1/me', [AuthController::class, 'profile'])
    // Unverified users may read emailVerifiedAt so the frontend can choose its next screen.
    ->middleware('auth:sanctum');
