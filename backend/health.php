<?php
/**
 * Minimal operational health endpoint.
 *
 * Returns only generic service state. It deliberately omits application
 * versions, environment values, database names, and exception details.
 */

define('EKARAMCHARI', true);
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $connection = Database::getInstance()->getConnection();
    $connection->query('SELECT 1');

    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'checks' => [
            'database' => 'ok'
        ]
    ]);
} catch (Throwable $exception) {
    error_log('Health endpoint database check failed: ' . $exception->getMessage());

    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'checks' => [
            'database' => 'unavailable'
        ]
    ]);
}
