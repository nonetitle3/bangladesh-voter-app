<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController
{
    public function configStatus()
    {
        return response()->json([
            'ADMIN_USERNAME' => (bool) env('ADMIN_USERNAME'),
            'ADMIN_PASSWORD' => (bool) env('ADMIN_PASSWORD'),
            'ADMIN_PASSWORD_HASH' => (bool) env('ADMIN_PASSWORD_HASH'),
            'JWT_SECRET' => (bool) env('JWT_SECRET'),
            'DATABASE_AUTH' => true,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $secret = (string) env('JWT_SECRET', '');
        if ($secret === '') {
            return response()->json(['error' => 'Admin authentication is not configured'], 500);
        }

        $envUsername = (string) env('ADMIN_USERNAME', '');
        $plain = (string) env('ADMIN_PASSWORD', '');
        $legacyHash = (string) env('ADMIN_PASSWORD_HASH', '');

        // First preserve the exact existing Render environment-admin contract.
        if ($envUsername !== '' && hash_equals($envUsername, $data['username'])) {
            if ($plain !== '') {
                $valid = hash_equals($plain, $data['password']);
            } elseif ($legacyHash !== '') {
                $valid = password_verify($data['password'], $legacyHash);
            } else {
                $valid = false;
            }
            if (!$valid) {
                return response()->json(['error' => 'Invalid username or password'], 401);
            }
            return $this->issueToken($secret, $envUsername, 'admin');
        }

        // Additional accounts are stored in MySQL and use Laravel's bcrypt/argon
        // password hasher. The password hash is never returned by any API.
        $user = User::query()->where('username', $data['username'])->first();
        if (!$user || !$user->is_active || !Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Invalid username or password'], 401);
        }

        return $this->issueToken($secret, $user->username, $user->role, (string) $user->id);
    }

    private function issueToken(string $secret, string $username, string $role, ?string $subject = null)
    {
        $now = time();
        $claims = [
            'iat' => $now,
            'exp' => $now + 28800,
            'username' => $username,
            'role' => $role,
        ];
        if ($subject !== null) {
            $claims['sub'] = $subject;
        }
        $token = JWT::encode($claims, $secret, 'HS256');
        return response()->json(['success' => true, 'token' => $token]);
    }

    public function me(Request $request)
    {
        return response()->json(['authenticated' => true] + ($request->attributes->get('legacy_admin') ?? []));
    }
}
