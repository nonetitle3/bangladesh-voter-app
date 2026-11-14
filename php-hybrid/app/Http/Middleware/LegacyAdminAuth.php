<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyAdminAuth
{
    public function __construct(private LegacyAuthenticate $authenticate) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->authenticate->handle($request, function (Request $authenticated) use ($next) {
            $identity = $authenticated->attributes->get('auth_user', []);
            if (($identity['role'] ?? '') !== 'admin') {
                return response()->json(['error' => 'Admin access required'], 403);
            }
            return $next($authenticated);
        });
    }
}
