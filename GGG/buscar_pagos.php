<?php
// Archivo: buscar_pagos.php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';

if (!isset($_SESSION['operador_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Corte: Operador no autorizado.']);
    exit;
}

$query = $_GET['q'] ?? '';

// ¡Cambiamos a LEFT JOIN para que no desaparezcan pagos huérfanos!
$sql = "SELECT p.id, p.reference, p.monto, p.coin, p.fecha, 
               c.ci, c.nombres, c.apellidos, 
               o.username AS nombre_operador
        FROM pagos p 
        LEFT JOIN clientes c ON p.id_cliente = c.id 
        LEFT JOIN operator o ON p.id_operador = o.id 
        WHERE 1=1";
$params = [];

if (!empty($query)) {
    $sql .= " AND (p.reference LIKE :q OR c.ci LIKE :q OR c.nombres LIKE :q)";
    $params['q'] = '%' . $query . '%';
}

$sql .= " ORDER BY p.fecha DESC LIMIT 50";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ob_clean();
    echo json_encode(['success' => true, 'data' => $resultados]);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error en el set de pagos: ' . $e->getMessage()]);
}
?>