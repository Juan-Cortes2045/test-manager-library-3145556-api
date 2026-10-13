<?php

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Json;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function login(Request $request, LoginService $service): JsonResponse
    {
        $data = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $result = $service->login($data['username'], $data['password']);

        return Json::ok(['access_token' => $result->accessToken, 'token_type' => 'Bearer', 'expires_in' => $result->expiresIn, 'role' => $result->role->value]);
    }

    public function createUser(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $data = $request->validate(['username' => 'required|string|max:120', 'password' => 'required|string', 'role' => 'required|string']);
        $user = $service->create($data['username'], $data['password'], $data['role']);

        return Json::ok(['id' => $user->id, 'username' => $user->username, 'role' => $user->role->value], 201);
    }
}
