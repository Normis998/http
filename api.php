<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/WeatherService.php';

$config = require __DIR__ . '/config.php';

try {
    $db      = getDb($config['db']);
    $service = new WeatherService($db, $config);
    $city    = $_GET['city'] ?? $config['default_city'];
    echo json_encode($service->getWeather($city), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}