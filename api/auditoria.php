<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$currentOperator = requireActiveOperator($pdo);
requireOperatorRole($currentOperator, ['soporte_ti']);

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = min(100, max(10, (int) ($_GET['por_pagina'] ?? 50)));
$offset = ($pagina - 1) * $porPagina;

try {
    $total = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT actor_username, action, entity_type, entity_id, changes, created_at
         FROM audit_logs
         ORDER BY created_at DESC, id DESC
         LIMIT :limite OFFSET :offset'
    );
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as &$log) {
        $log['changes'] = $log['changes'] !== null ? json_decode($log['changes'], true) : [];
    }
    unset($log);
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total' => $total,
        'paginas' => (int) ceil($total / $porPagina)
    ]);
} catch (PDOException $e) {
    error_log('No se pudo consultar la auditoría: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo consultar el historial.']);
}
