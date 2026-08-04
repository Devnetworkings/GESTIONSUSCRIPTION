<?php
// Archivo: perfil_cliente.php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';

if (!isset($_SESSION['operador_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Corte: Operador no autorizado.']);
    exit;
}

$id_cliente = $_GET['id'] ?? '';

if (empty($id_cliente)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Falta el ID del talento.']);
    exit;
}

try {
    // 1. Buscamos el perfil completo del actor
    $stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$id_cliente]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Talento no encontrado en la base de datos.']);
        exit;
    }

    // 2. Buscamos todas sus escenas de acción (pagos)
    $stmt_pagos = $pdo->prepare("
        SELECT p.reference, p.monto, p.coin, p.fecha, o.username as operador
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