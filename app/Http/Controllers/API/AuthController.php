<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $data = $this->authService->register($validated);

        return response()->json($data, 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $data = $this->authService->login($validated);

        return response()->json($data);
    }

    /**
     * Handle Google Login
     */
    public function googleAuth(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        try {
            $data = $this->authService->googleAuth($request->credential);
            return response()->json($data);
        } catch (Exception $e) {
            $status = $e->getCode() ?: 400;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Get the authenticated User
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout'
        ]);
    }
}
