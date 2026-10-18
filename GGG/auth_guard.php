<?php

function requireActiveOperator(PDO $pdo): array
{
    if (!isset($_SESSION['operador_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT username, operator_role, activo FROM operator WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['operador_id']]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$operator || (int) $operator['activo'] !== 1) {
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
