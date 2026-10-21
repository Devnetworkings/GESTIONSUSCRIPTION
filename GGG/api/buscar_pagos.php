<?php

// Archivo: buscar_pagos.php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
requireActiveOperator($pdo);

$query = $_GET['q'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$metodo = $_GET['metodo'] ?? '';
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = min(100, max(10, (int) ($_GET['por_pagina'] ?? 50)));
$offset = ($pagina - 1) * $por_pagina;

// �Cambiamos a LEFT JOIN para que no desaparezcan pagos hu�rfanos!
$sql = "SELECT p.id, p.reference, p.monto, p.coin, p.fecha, p.metodo_pago, p.id_plan,
               p.tasa_bcv, p.fecha_valor_bcv,
               c.ci, c.nombres, c.apellidos, 
               o.username AS nombre_operador
        FROM pagos p 
        LEFT JOIN clientes c ON p.id_cliente = c.id 
        LEFT JOIN operator o ON p.id_operador = o.id 
        WHERE 1=1";
$params = [];

if (!empty($query)) {
    // Buscamos en todas las columnas relevantes de la tabla
    $sql .= " AND (p.reference LIKE :q 
                OR c.ci LIKE :q 
                OR c.nombres LIKE :q 
                OR c.apellidos LIKE :q 
                OR p.monto LIKE :q 
                OR p.coin LIKE :q 
                OR p.metodo_pago LIKE :q
                OR o.username LIKE :q)";
    $params['q'] = '%' . $query . '%';
}

if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $sql .= ' AND p.fecha >= :desde';
    $params['desde'] = $desde;
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $sql .= ' AND p.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY)';
    $params['hasta'] = $hasta;
}
if (($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) || ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta))) {
    http_response_code(422);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Formato de fecha no válido.']);
    exit;
}
if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    http_response_code(422);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'La fecha inicial no puede ser posterior a la fecha final.']);
    exit;
}
if (in_array($metodo, ['pagomovil', 'divisa', 'transferencia'], true)) {
    $sql .= ' AND p.metodo_pago = :metodo';
    $params['metodo'] = $metodo;
}

$sqlTotal = 'SELECT COUNT(*) ' . substr($sql, strpos($sql, 'FROM pagos p'));
$sql .= ' ORDER BY p.fecha DESC LIMIT :limite OFFSET :offset';

try {
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();
    $stmt = $pdo->prepare($sql);
    foreach ($params as $nombre => $valor) {
        $stmt->bindValue(':' . $nombre, $valor);
    }
    $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ob_clean();
    echo json_encode(['success' => true, 'data' => $resultados, 'pagina' => $pagina, 'por_pagina' => $por_pagina, 'total' => $total, 'paginas' => (int) ceil($total / $por_pagina)]);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error en el set de pagos: ' . $e->getMessage()]);
}
?>