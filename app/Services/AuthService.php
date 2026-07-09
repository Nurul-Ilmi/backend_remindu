<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'xp_points'    => 0,
            'level'        => 1,
            'extreme_mode' => false,
            'wa_connected' => false,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user
        ];
    }

    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        // Delete old tokens (optional, if we want single-device login)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user
        ];
    }

    public function googleAuth(string $credential): array
    {
        // Verify with Google
        $response = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$credential}");

        if ($response->failed()) {
            throw new Exception('Token Google tidak valid.', 401);
        }

        $payload = $response->json();

        // Ensure token has email
        if (!isset($payload['email'])) {
            throw new Exception('Email tidak ditemukan dari akun Google.', 400);
        }

        // Find user by email or google_id
        $user = User::where('email', $payload['email'])->first();

        if ($user) {
            // Update google_id if it's missing
            if (!$user->google_id) {
                $user->update(['google_id' => $payload['sub']]);
            }
        } else {
            // Create new user
            $user = User::create([
                'name'         => $payload['name'] ?? 'User Google',
                'email'        => $payload['email'],
                'password'     => null, // Nullable
                'google_id'    => $payload['sub'],
                'xp_points'    => 0,
                'level'        => 1,
                'extreme_mode' => false,
                'wa_connected' => false,
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user
        ];
    }
}
