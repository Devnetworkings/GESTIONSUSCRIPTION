<?php

ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';

if (isset($_SESSION['operador_id'])) {
    $stmt = $pdo->prepare('SELECT username, operator_role, activo FROM operator WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['operador_id']]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$operator || (int) $operator['activo'] !== 1) {
        $_SESSION = [];
        session_destroy();
        echo json_encode(['success' => false]);
        exit;
    }
    $_SESSION['operador_nombre'] = $operator['username'];
    $_SESSION['operador_role'] = $operator['operator_role'];
    echo json_encode([
        'success' => true,
        'operador' => $_SESSION['operador_nombre'],
        'role' => $_SESSION['operador_role']
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>