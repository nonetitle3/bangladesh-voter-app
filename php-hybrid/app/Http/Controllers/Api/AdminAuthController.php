<?php
namespace App\Http\Controllers\Api;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;

class AdminAuthController {
    public function configStatus() {
        return response()->json([
            'ADMIN_USERNAME' => (bool) env('ADMIN_USERNAME'),
            'ADMIN_PASSWORD' => (bool) env('ADMIN_PASSWORD'),
            'ADMIN_PASSWORD_HASH' => (bool) env('ADMIN_PASSWORD_HASH'),
            'JWT_SECRET' => (bool) env('JWT_SECRET'),
        ]);
    }
    public function login(Request $request) {
        $data = $request->validate(['username' => ['required','string','max:100'], 'password' => ['required','string','max:1024']]);
        $username = (string) env('ADMIN_USERNAME', '');
        $plain = (string) env('ADMIN_PASSWORD', '');
        $hash = (string) env('ADMIN_PASSWORD_HASH', '');
        $secret = (string) env('JWT_SECRET', '');
        if ($username === '' || $secret === '' || ($plain === '' && $hash === '')) {
            return response()->json(['error' => 'Admin authentication is not configured'], 503);
        }
        $validPassword = $plain !== '' ? hash_equals($plain, $data['password']) : password_verify($data['password'], $hash);
        if (!hash_equals($username, $data['username']) || !$validPassword) {
            return response()->json(['error' => 'Invalid username or password'], 401);
        }
        $now = time();
        $token = JWT::encode([
            'iat' => $now, 'exp' => $now + 28800,
            'username' => $username, 'role' => 'admin',
        ], $secret, 'HS256');
        return response()->json(['success' => true, 'token' => $token]);
    }
    public function me(Request $request) {
        return response()->json(['authenticated' => true] + ($request->attributes->get('legacy_admin') ?? []));
    }
}
