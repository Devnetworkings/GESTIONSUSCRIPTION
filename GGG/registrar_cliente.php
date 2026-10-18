<?php
session_start();
header('Content-Type: application/json');
require 'conexion.php';
require 'Cliente.php';
require 'validaciones.php';
require 'auth_guard.php';
requireActiveOperator($pdo);

$ci = normalizarCedula($_POST['ci'] ?? '');
$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$tlf = normalizarTelefono($_POST['tlf'] ?? '');
$email = trim($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($ci === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'La cédula debe tener de 6 a 9 dígitos (ejemplo: 30830476).']);
    exit;
}
if ($nombres === '' || strlen($nombres) > 50 || strlen($apellidos) > 50) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio y nombres/apellidos no pueden exceder 50 caracteres.']);
    exit;
}
if ($tlf === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El teléfono debe tener formato 04xx-xxxxxxx (ejemplo: 0412-1234567).']);
    exit;
}
if ($email !== '' && !emailValido($email)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El correo debe tener un formato válido y no exceder 254 caracteres.']);
    exit;
}

try {
    $nuevoCliente = new Cliente($ci, $nombres, $apellidos, $tlf, $email, $direccion);
    $nuevoCliente->guardar($pdo);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code($e->getCode() === '23000' ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getCode() === '23000' ? 'Ya existe un cliente con esa cédula.' : 'No se pudo registrar el cliente.'
    ]);
}
?>
