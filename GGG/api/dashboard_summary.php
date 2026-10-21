<?php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
ob_clean();
require_once __DIR__ . '/../includes/auth_guard.php';
requireActiveOperator($pdo);

try {
    $configuracion = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'dias_aviso_vencimiento'")->fetchColumn();
    if ($configuracion === false) {
        throw new RuntimeException('No está configurado el plazo de aviso de vencimiento.');
    }
    $dias = (int) $configuracion;
    $dias = max(0, min(90, $dias));
    $fechaLimite = "DATE_ADD(CURDATE(), INTERVAL $dias DAY)";
    $stmt = $pdo->query("
        SELECT
            COUNT(CASE
                WHEN estatus <> 'Inactivo' AND fecha_vencimiento < CURDATE()
                THEN 1
            END) AS vencidos,
            COUNT(CASE
                WHEN estatus <> 'Inactivo'
                 AND fecha_vencimiento BETWEEN CURDATE() AND $fechaLimite
                THEN 1
            END) AS por_vencer,
            COUNT(CASE
                WHEN estatus = 'Activo'
                 AND (fecha_vencimiento IS NULL OR fecha_vencimiento > $fechaLimite)
                THEN 1
            END) AS activos,
            COUNT(CASE WHEN estatus = 'Inactivo' THEN 1 END) AS inactivos,
            COUNT(CASE WHEN estatus = 'Deuda' THEN 1 END) AS con_deuda
        FROM clientes
    ");
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'dias_aviso' => $dias,
        'vencidos' => (int) ($resumen['vencidos'] ?? 0),
        'por_vencer' => (int) ($resumen['por_vencer'] ?? 0),
        'activos' => (int) ($resumen['activos'] ?? 0),
        'inactivos' => (int) ($resumen['inactivos'] ?? 0),
        'con_deuda' => (int) ($resumen['con_deuda'] ?? 0)
    ]);
} catch (PDOException | RuntimeException $e) {
    ob_clean();
    error_log('No se pudo cargar el resumen de vencimientos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo cargar el resumen de vencimientos.']);
}
