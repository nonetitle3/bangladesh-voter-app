<?php
namespace App\\Http\\Controllers\\Api;
use Illuminate\\Support\\Facades\\DB;
class HealthController {
    public function __invoke() {
        $database = 'unavailable';
        try { DB::select('SELECT 1'); $database = 'ok'; } catch (\\Throwable $e) {}
        return response()->json([
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'service' => 'Bangladesh Voter Laravel API',
            'runtime' => PHP_VERSION,
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $database === 'ok' ? 200 : 503);
    }
}
