<?php

namespace App\Http\Controllers;

use App\Application\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function login(Request $request, LoginService $service): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $result = $service->login($data['username'], $data['password']);

        return response()->json([
            'access_token' => $result->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $result->expiresIn,
            'role' => $result->role,
        ]);
    }
}
