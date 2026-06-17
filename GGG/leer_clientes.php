<?php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';

// Seguridad en el set
if (!isset($_SESSION['operador_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Operador no autorizado.']);
    exit;
}

try {
    // 🔍 Extraemos los últimos 10 clientes, ordenados por los más recientes
    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 10");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode(['success' => true, 'data' => $clientes]);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al cargar el elenco: ' . $e->getMessage()]);
}
?>