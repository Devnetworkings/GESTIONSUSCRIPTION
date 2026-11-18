<?php

require_once __DIR__ . '/entorno.php';

$esProduccion = strtolower(getenv('APP_ENV') ?: 'development') === 'production';
$configuracionProduccion = __DIR__ . '/conexion.prod.php';

if ($esProduccion && is_file($configuracionProduccion)) {
    require_once $configuracionProduccion;
    if (isset($pdo) && $pdo instanceof PDO) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json');
    error_log('The production database configuration did not initialize PDO.');
    die(json_encode(['success' => false, 'message' => 'No se pudo configurar la conexión a la base de datos.']));
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'ggg';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

if ($esProduccion && (!getenv('DB_HOST') || !getenv('DB_NAME') || !getenv('DB_USER'))) {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('Production database credentials are not configured.');
    die(json_encode(['success' => false, 'message' => 'La conexión a la base de datos no está configurada.']));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Error de conexión.']));
}
