<?php

use Illuminate\Support\Facades\Route;

// Base prefix /api/v1 is set in bootstrap/app.php.

Route::get('/health', fn () => response()->json(['status' => 'ok']));
