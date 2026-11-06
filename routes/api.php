<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Base prefix /api/v1 is set in bootstrap/app.php.

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/auth/login', [AuthController::class, 'login']);

// Everything else requires a token (CA-01.3).
Route::middleware('auth.token')->group(function () {
    Route::post('/users', [UserController::class, 'store'])->middleware('role:admin');
});
