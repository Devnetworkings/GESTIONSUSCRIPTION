<?php
ob_start();
session_start();
require 'conexion.php';
header('Content-Type: application/json');

$operador = $_POST['operator'] ?? '';
$password_ingresada = $_POST['password'] ?? '';

if (empty($operador) || empty($password_ingresada)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Faltan credenciales.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, PW, username FROM operator WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $operador]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && !empty($usuario['PW']) && password_verify($password_ingresada, $usuario['PW'])) {
        $_SESSION['operador_logueado'] = true;
        $_SESSION['operador_id'] = $usuario['id'];
        $_SESSION['operador_nombre'] = $usuario['username'];

        ob_clean();
        echo json_encode(['success' => true, 'operador' => $usuario['username']]);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    }
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error de conexión.']);
}