<?php

ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';

if (isset($_SESSION['operador_id'])) {
    $operator = requireActiveOperator($pdo);
    if (!$operator) {
        echo json_encode(['success' => false]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'operador' => $operator['username'],
        'role' => $operator['operator_role']
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>