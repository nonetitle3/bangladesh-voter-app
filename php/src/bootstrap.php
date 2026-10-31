<?php
declare(strict_types=1);

use Firebase\\JWT\\JWT;
use Firebase\\JWT\\Key;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Dhaka');

function envv(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = envv('DB_HOST');
    $name = envv('DB_NAME');
    $user = envv('DB_USER');
    $pass = envv('DB_PASSWORD');
    if (!$host || !$name || !$user) throw new RuntimeException('MySQL is not configured. Set DB_HOST, DB_NAME, DB_USER and DB_PASSWORD.');
    $port = envv('DB_PORT', '3306');
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function body_json(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
function bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\\s+(.+)$/i', $header, $m) ? trim($m[1]) : null;
}
function admin_user(): ?array {
    $token = bearer_token();
    $secret = envv('JWT_SECRET');
    if (!$token || !$secret) return null;
    try {
        $claims = JWT::decode($token, new Key($secret, 'HS256'));
        if (($claims->role ?? '') !== 'admin' || ($claims->exp ?? 0) < time()) return null;
        return ['username' => $claims->username ?? 'admin', 'role' => 'admin'];
    } catch (Throwable $e) { return null; }
}
function require_admin(): array {
    $user = admin_user();
    if (!$user) json_response(['error' => 'Authentication required or token expired'], 401);
    return $user;
}
function clean_text(string $s): string {
    $s = str_replace("\xc2\xa0", ' ', $s);
    return trim(preg_replace('/[ \\t]+/u', ' ', $s) ?? $s);
}
function bn_digits(string $s): string {
    return strtr($s, ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9']);
}
function app_root(string $path = ''): string { return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : ''); }
