<?php

function requireActiveOperator(PDO $pdo): array
{
    require_once __DIR__ . '/auditoria.php';
    reactivarOperadoresConVacacionesVencidas($pdo);

    if (!isset($_SESSION['operador_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT username, operator_role, operator_status, activo FROM operator WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['operador_id']]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$operator || (int) $operator['activo'] !== 1 || $operator['operator_status'] !== 'Activo') {
        $_SESSION = [];
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'La cuenta está desactivada o ya no existe. Contacta a un administrador.']);
        exit;
    }

    $_SESSION['operador_nombre'] = $operator['username'];
    $_SESSION['operador_role'] = $operator['operator_role'];
    return $operator;
}

function requireOperatorRole(array $operator, array $roles): void
{
    if (!in_array($operator['operator_role'], $roles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
        exit;
    }
}
