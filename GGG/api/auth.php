<?php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
require_once __DIR__ . '/../includes/conexion.php';
header('Content-Type: application/json');

$operador = $_POST['operator'] ?? '';
$password_ingresada = $_POST['password'] ?? '';

if (empty($operador) || empty($password_ingresada)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Faltan credenciales.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, PW, username, operator_role, activo FROM operator WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $operador]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && (int) $usuario['activo'] === 1 && !empty($usuario['PW']) && password_verify($password_ingresada, $usuario['PW'])) {
        session_regenerate_id(true);
        $_SESSION['operador_logueado'] = true;
        $_SESSION['operador_id'] = $usuario['id'];
        $_SESSION['operador_nombre'] = $usuario['username'];
        $_SESSION['operador_role'] = $usuario['operator_role'] ?? 'operador';

        ob_clean();
        echo json_encode(['success' => true, 'operador' => $usuario['username'], 'role' => $_SESSION['operador_role']]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    }
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error de conexión.']);
}