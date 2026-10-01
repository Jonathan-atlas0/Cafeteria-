<?php

/**
 * health.php — Endpoint de Health Check
 * ──────────────────────────────────────
 * Equivalente ao Microsoft.Extensions.Diagnostics.HealthChecks (.NET)
 * com endpoints /health/live (liveness) e /health/ready (readiness).
 *
 * ROTAS:
 *   GET /health.php           → readiness (verifica todas as dependências)
 *   GET /health.php?live=1    → liveness  (apenas verifica se o PHP está vivo)
 *
 * Retorna JSON no mesmo formato do ASP.NET Core HealthChecks:
 *   {
 *     "status": "Healthy",
 *     "duration": "23ms",
 *     "entries": {
 *       "postgres": { "status": "Healthy", "duration": "12ms" },
 *       "disk":     { "status": "Healthy", "data": { "free_gb": 18.5 } }
 *     }
 *   }
 *
 * HTTP Status:
 *   200 → Healthy (todos os checks passaram)
 *   503 → Unhealthy (ao menos um check falhou)
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

// ── Liveness — apenas verifica se o PHP está respondendo ─────────────────────
// Equivalente ao /health/live do ASP.NET Core (sem verificar dependências)
if (isset($_GET['live'])) {
    echo json_encode([
        'status'   => 'Healthy',
        'duration' => '0ms',
        'entries'  => [
            'self' => ['status' => 'Healthy', 'description' => 'PHP process is alive']
        ],
    ]);
    exit;
}

// ── Readiness — verifica todas as dependências ────────────────────────────────
// Equivalente ao /health/ready do ASP.NET Core

$checks  = [];
$inicio  = microtime(true);

// ── Check 1: PostgreSQL ───────────────────────────────────────────────────────
// Equivalente ao .AddNpgSql() / .AddSqlServer() do .NET
$t = microtime(true);
try {
    $host = getenv('DB_HOST') ?: 'db';
    $user = getenv('DB_USER') ?: 'cafeteria';
    $pass = getenv('DB_PASS') ?: 'cafeteria123';
    $bd   = getenv('DB_NAME') ?: 'Cafeteria';

    $pdo = new PDO(
        "pgsql:host=$host;dbname=$bd",
        $user,
        $pass,
        [PDO::ATTR_TIMEOUT => 2] // timeout de 2s — equivale ao HealthCheckOptions.Timeout
    );
    $pdo->query('SELECT 1'); // query de ping

    $checks['postgres'] = [
        'status'      => 'Healthy',
        'duration'    => round((microtime(true) - $t) * 1000) . 'ms',
        'description' => 'PostgreSQL connection OK',
    ];
} catch (\Throwable $e) {
    $checks['postgres'] = [
        'status'      => 'Unhealthy',
        'duration'    => round((microtime(true) - $t) * 1000) . 'ms',
        'description' => $e->getMessage(),
    ];
}

// ── Check 2: Espaço em disco ──────────────────────────────────────────────────
// Equivalente a um DiskStorageHealthCheck customizado no .NET
$t = microtime(true);
try {
    $logsDir  = __DIR__ . '/logs';
    $freeBytes = disk_free_space($logsDir ?: '/');
    $freeGb    = round($freeBytes / (1024 ** 3), 2);
    $minGb     = 0.5; // mínimo de 500 MB livres

    $checks['disk'] = [
        'status'      => $freeGb >= $minGb ? 'Healthy' : 'Unhealthy',
        'duration'    => round((microtime(true) - $t) * 1000) . 'ms',
        'description' => "Disk space: {$freeGb} GB free",
        'data'        => ['free_gb' => $freeGb, 'min_gb' => $minGb],
    ];
} catch (\Throwable $e) {
    $checks['disk'] = [
        'status'      => 'Unhealthy',
        'duration'    => round((microtime(true) - $t) * 1000) . 'ms',
        'description' => $e->getMessage(),
    ];
}

// ── Status global ─────────────────────────────────────────────────────────────
// Equivalente ao comportamento padrão do HealthCheckService do ASP.NET Core
$globalStatus = in_array('Unhealthy', array_column($checks, 'status'), true)
    ? 'Unhealthy'
    : 'Healthy';

$duracaoTotal = round((microtime(true) - $inicio) * 1000) . 'ms';

// 200 → Healthy | 503 → Unhealthy (mesmo comportamento do ASP.NET Core)
http_response_code($globalStatus === 'Healthy' ? 200 : 503);

echo json_encode([
    'status'   => $globalStatus,
    'duration' => $duracaoTotal,
    'entries'  => $checks,
], JSON_PRETTY_PRINT);
