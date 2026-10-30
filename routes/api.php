<?php

use App\Http\Controllers\Api\V1\EmailVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/email/verify/{id}/{hash}', EmailVerificationController::class)
    ->middleware('signed')
    // Only match user IDs containing digits, so invalid values never reach the controller.
    ->whereNumber('id')
    // SHA-1 produces exactly 40 lowercase hexadecimal characters (0-9 and a-f).
    ->where('hash', '[a-f0-9]{40}')
    ->name('verification.verify');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
