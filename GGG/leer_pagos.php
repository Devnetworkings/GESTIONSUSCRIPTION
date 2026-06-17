<?php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';

// ¿Hay seguridad en la puerta?
if (!isset($_SESSION['operador_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Operador no autorizado.']);
    exit;
}

try {
    // 🔍 ACTO DE MAGIA (JOIN): Traemos el pago y lo unimos con los datos de ese cliente
    $sql = "SELECT p.id, p.reference, p.monto, p.coin, c.ci, c.nombres, c.apellidos 
            FROM pagos p 
            INNER JOIN clientes c ON p.id_cliente = c.id 
            ORDER BY p.id DESC LIMIT 10";

    $stmt = $pdo->query($sql);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode(['success' => true, 'data' => $pagos]);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al cargar la taquilla: ' . $e->getMessage()]);
}
?>