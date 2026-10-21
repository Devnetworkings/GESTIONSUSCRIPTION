<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
requireActiveOperator($pdo);
require_once __DIR__ . '/../includes/bcv_rate.php';

try {
    $stmt = $pdo->query("SELECT id, nombre, 1 AS duracion_meses, precio, 'USD' AS coin FROM planes WHERE activo = 1 AND precio > 0 ORDER BY nombre");
    echo json_encode([
        'success' => true,
        'planes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'tasa_bcv' => obtenerTasaBcv($pdo)
    ]);
} catch (PDOException $e) {
    error_log('No se pudieron cargar los planes de pago: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudieron cargar los planes.']);
}
