<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/voter.php';

header('Access-Control-Allow-Origin: ' . (envv('CORS_ORIGIN', '*')));
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Expose-Headers: Content-Disposition');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
try {
    if ($path === '/' || $path === '/api/health') json_response(['status'=>'ok','service'=>'Bangladesh Voter PHP API','runtime'=>PHP_VERSION]);
    if ($path === '/api/admin/config-status') json_response([
        'ADMIN_USERNAME'=>(bool)envv('ADMIN_USERNAME'),
        'ADMIN_PASSWORD'=>(bool)envv('ADMIN_PASSWORD'),
        'ADMIN_PASSWORD_HASH'=>(bool)envv('ADMIN_PASSWORD_HASH'),
        'JWT_SECRET'=>(bool)envv('JWT_SECRET')
    ]);
    if ($path === '/api/admin/login' && $method === 'POST') {
        $input = body_json();
        $username = (string)($input['username'] ?? '');
        $password = (string)($input['password'] ?? '');
        $admin = envv('ADMIN_USERNAME');
        $plain = envv('ADMIN_PASSWORD');
        $hash = envv('ADMIN_PASSWORD_HASH');
        $secret = envv('JWT_SECRET');
        if (!$admin || !$secret || (!$plain && !$hash)) json_response(['error'=>'Admin authentication is not configured'], 503);
        $valid = hash_equals($admin, $username) && ($plain ? hash_equals($plain, $password) : password_verify($password, $hash));
        if (!$valid) json_response(['error'=>'Invalid username or password'], 401);
        $now = time();
        $token = Firebase\JWT\JWT::encode(['iat'=>$now,'exp'=>$now+28800,'username'=>$username,'role'=>'admin'], $secret, 'HS256');
        json_response(['success'=>true,'token'=>$token]);
    }
    if ($path === '/api/admin/me') json_response(['authenticated'=>true] + require_admin());
    if ($path === '/api/stats' && $method === 'GET') json_response(public_stats());
    if (preg_match('#^/api/(divisions|districts|upazilas|unions|wards|voters)(?:/|$)#', $path)) {
        hierarchy_route($path);
    }
    if ($path === '/api/search' && $method === 'GET') json_response(public_search($_GET));
    if (str_starts_with($path, '/api/admin/')) {
        require_admin();
        admin_route($path, $method);
    }
    json_response(['error'=>'Not found','path'=>$path], 404);
} catch (Throwable $e) {
    error_log((string)$e);
    json_response(['error'=>'Server error','detail'=>envv('APP_DEBUG') === 'true' ? $e->getMessage() : 'An internal error occurred'], 500);
}
