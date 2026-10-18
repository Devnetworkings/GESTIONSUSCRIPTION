<?php
// Archivo: perfil_cliente.php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';
require 'auth_guard.php';
requireActiveOperator($pdo);

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
        SELECT p.reference, p.monto, p.coin, p.metodo_pago, p.fecha,
               p.tasa_bcv, p.fecha_valor_bcv, o.username as operador
        FROM pagos p
        JOIN operator o ON p.id_operador = o.id
        WHERE p.id_cliente = ?
        ORDER BY p.fecha DESC
    ");
    $stmt_pagos->execute([$id_cliente]);
    $pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    // Enviamos un paquete doble: Datos + Pagos
    echo json_encode(['success' => true, 'cliente' => $cliente, 'pagos' => $pagos]);

} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error en el set: ' . $e->getMessage()]);
}
?>