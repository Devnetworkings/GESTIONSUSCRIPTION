<?php

// Archivo: buscar_clientes.php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
requireActiveOperator($pdo);

// 1. Capturamos los filtros de la URL (si existen)
$termino_busqueda = $_GET['q'] ?? '';
$estatus = $_GET['estatus'] ?? ''; // Aquí es donde llega "Deuda" desde tu select del frontend
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = min(100, max(10, (int) ($_GET['por_pagina'] ?? 50)));
$offset = ($pagina - 1) * $por_pagina;
$diasAviso = (int) $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'dias_aviso_vencimiento'")->fetchColumn();
$diasAviso = max(0, min(90, $diasAviso));

$sqlBase = ' FROM clientes WHERE 1=1';
$parametros = [];

if (!empty($termino_busqueda)) {
    $sqlBase .= " AND (ci LIKE :q
                    OR nombres LIKE :q
                    OR apellidos LIKE :q
                    OR email LIKE :q
                    OR tlf LIKE :q)";
    $parametros['q'] = '%' . $termino_busqueda . '%';
}

if (!empty($estatus)) {
    if ($estatus === 'Activo') {
        $sqlBase .= " AND estatus = 'Activo'
                      AND (fecha_vencimiento IS NULL OR fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL $diasAviso DAY))";
    } elseif ($estatus === 'Inactivo') {
        $sqlBase .= " AND estatus = 'Inactivo'";
    } elseif ($estatus === 'Vencido') {
        $sqlBase .= " AND estatus <> 'Inactivo'
                      AND fecha_vencimiento IS NOT NULL
                      AND fecha_vencimiento < CURDATE()";
    } elseif ($estatus === 'Por vencer') {
        $sqlBase .= " AND estatus <> 'Inactivo'
                      AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $diasAviso DAY)";
    } elseif ($estatus === 'Deuda') {
        $sqlBase .= " AND estatus = 'Deuda'";
    }
}

$estatusCalculado = "CASE
    WHEN estatus = 'Inactivo' THEN 'Inactivo'
    WHEN fecha_vencimiento < CURDATE() THEN 'Vencido'
    WHEN fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $diasAviso DAY) THEN 'Por vencer'
    ELSE estatus
END";
$sqlTotal = 'SELECT COUNT(*)' . $sqlBase;
$sql = "SELECT id, ci, nombres, apellidos, tlf, email, $estatusCalculado AS estatus" . $sqlBase
    . ' ORDER BY nombres ASC LIMIT :limite OFFSET :offset';

try {
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($parametros);
    $total = (int) $stmtTotal->fetchColumn();
    $stmt = $pdo->prepare($sql);
    foreach ($parametros as $nombre => $valor) {
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
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'No se pudo buscar clientes.']);
}
?>