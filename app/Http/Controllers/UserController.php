<?php

namespace App\Http\Controllers;

use App\Application\Auth\CreateLibrarianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    public function store(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
            'role' => ['required', 'string'],
        ]);

        $user = $service->create($data['username'], $data['password'], $data['role']);

        // Never password nor password_hash (CA-02.3).
        return response()->json(['id' => $user->id, 'username' => $user->username, 'role' => $user->role->value], 201);
    }
}
