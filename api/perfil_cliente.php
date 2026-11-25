<?php
// Archivo: perfil_cliente.php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/bcv_rate.php';
$currentOperator = requireActiveOperator($pdo);
$esAdmin = $currentOperator['operator_role'] === 'admin';

$id_cliente = $_GET['id'] ?? '';

if (empty($id_cliente)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Falta el ID del talento.']);
    exit;
}

try {
    // 1. Buscamos el perfil completo del actor
    $dias = max(0, min(90, (int) $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'dias_aviso_vencimiento'")->fetchColumn()));
    $stmt_cliente = $pdo->prepare("
        SELECT *,
               CASE WHEN tipo_documento IS NULL OR tipo_documento = '' THEN ci ELSE CONCAT(tipo_documento, '-', ci) END AS ci_completa,
               CASE
                   WHEN estatus = 'Inactivo' THEN 'Inactivo'
                   WHEN fecha_vencimiento < CURDATE() THEN 'Vencido'
                   WHEN fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 'Por vencer'
                   ELSE estatus
               END AS estatus_calculado
        FROM clientes
        WHERE id = ?
    ");
    $stmt_cliente->execute([$dias, $id_cliente]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Talento no encontrado en la base de datos.']);
        exit;
    }

    // 2. Buscamos todas sus escenas de acci�n (pagos)
    $stmt_pagos = $pdo->prepare("
        SELECT p.reference, p.monto, p.coin, p.metodo_pago, p.fecha_pago,
               p.tasa_bcv, p.fecha_valor_bcv
               " . ($esAdmin ? ", p.fecha_registro, o.username AS operador" : "") . "
        FROM pagos p
        JOIN operator o ON p.id_operador = o.id
        WHERE p.id_cliente = ?
        ORDER BY p.fecha_registro DESC
    ");
    $stmt_pagos->execute([$id_cliente]);
    $pagos = completarPagosConTasaActualReferencial($stmt_pagos->fetchAll(PDO::FETCH_ASSOC), $pdo);

    ob_clean();
    // Enviamos un paquete doble: Datos + Pagos
    echo json_encode(['success' => true, 'es_admin' => $esAdmin, 'cliente' => $cliente, 'pagos' => $pagos]);

} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error en el set: ' . $e->getMessage()]);
}
?>