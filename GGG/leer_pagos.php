<?php

ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';
require 'auth_guard.php';
requireActiveOperator($pdo);

// 1. Capturamos el término de búsqueda de la barra
$query = $_GET['q'] ?? '';

try {
    // 🔍 ACTO DE MAGIA (JOIN): Traemos el pago, lo unimos con el cliente y el operador
    $sql = "SELECT p.id, p.reference, p.monto, p.coin, p.fecha, 
                   c.ci, c.nombres, c.apellidos, 
                   o.username AS nombre_operador
            FROM pagos p 
            LEFT JOIN clientes c ON p.id_cliente = c.id 
            LEFT JOIN operator o ON p.id_operador = o.id 
            WHERE 1=1";

    $params = [];

    // 2. Si hay texto en la barra de búsqueda, añadimos los filtros a la consulta
    if (!empty($query)) {
        // Hacemos que la búsqueda funcione con múltiples columnas
        $sql .= " AND (p.reference LIKE :q 
                    OR c.ci LIKE :q 
                    OR c.nombres LIKE :q 
                    OR c.apellidos LIKE :q 
                    OR p.monto LIKE :q 
                    OR p.coin LIKE :q 
                    OR o.username LIKE :q)";
        $params['q'] = '%' . $query . '%';
    }

    $sql .= " ORDER BY p.fecha DESC LIMIT 50"; // Limitamos a 50 para mantener la taquilla controlada

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode(['success' => true, 'data' => $pagos]);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error al cargar la taquilla: ' . $e->getMessage()]);
}
?>