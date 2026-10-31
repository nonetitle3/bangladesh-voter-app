<?php
namespace App\\Http\\Middleware;
use Closure;
use Firebase\\JWT\\JWT;
use Firebase\\JWT\\Key;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class LegacyAdminAuth {
    public function handle(Request $request, Closure $next): Response {
        $header = $request->header('Authorization', '');
        $secret = (string) env('JWT_SECRET', '');
        if (!preg_match('/^Bearer\\s+(.+)$/i', $header, $m) || $secret === '') {
            return response()->json(['error' => 'Authentication required'], 401);
        }
        try {
            $claims = JWT::decode($m[1], new Key($secret, 'HS256'));
            if (($claims->role ?? '') !== 'admin' || (int)($claims->exp ?? 0) < time()) {
                return response()->json(['error' => 'Invalid or expired token'], 401);
            }
            $request->attributes->set('legacy_admin', [
                'username' => $claims->username ?? 'admin',
                'role' => $claims->role,
            ]);
        } catch (\\Throwable $e) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }
        return $next($request);
    }
}
