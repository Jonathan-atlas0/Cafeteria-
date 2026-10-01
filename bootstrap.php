<?php
/**
 * bootstrap.php
 * Carrega o autoload do Composer, o LoggerService e as funções de pedidos.
 */

declare(strict_types=1);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/observability/LoggerService.php';
require_once __DIR__ . '/pedidos.php';

use Cafeteria\Observability\LoggerService;

LoggerService::info('Application bootstrap completed', [
    'php_version' => PHP_VERSION,
    'environment' => getenv('APP_ENV') ?: 'production',
]);
