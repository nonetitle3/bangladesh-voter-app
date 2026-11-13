<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        $secret = (string) env('JWT_SECRET', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches) || $secret === '') {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        try {
            $claims = JWT::decode($matches[1], new Key($secret, 'HS256'));
            $role = (string) ($claims->role ?? '');
            if (!in_array($role, ['admin', 'viewer'], true)) {
                return response()->json(['error' => 'Invalid or expired token'], 401);
            }

            $identity = [
                'username' => (string) ($claims->username ?? ''),
                'role' => $role,
                'user_id' => null,
            ];

            // Tokens issued by the old Node service have no subject. Continue to
            // accept those unchanged; new database-user tokens are revocable.
            if (isset($claims->sub)) {
                $user = User::query()->find((int) $claims->sub);
                if (!$user || !$user->is_active || $user->role !== $role) {
                    return response()->json(['error' => 'Account is inactive or no longer authorized'], 401);
                }
                $identity['username'] = $user->username;
                $identity['user_id'] = (int) $user->id;
            }

            $request->attributes->set('legacy_admin', $identity);
            $request->attributes->set('auth_user', $identity);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        return $next($request);
    }
}
