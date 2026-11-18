<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/auth_guard.php';
requireActiveOperator($pdo);

$id = trim($_POST['id'] ?? '');
$ci = normalizarCedula($_POST['ci'] ?? '');
$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$tlf = normalizarTelefono($_POST['tlf'] ?? '');
$email = trim($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$estatus = $_POST['estatus'] ?? '';
$fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');

if ($id === '' || $ci === false || $nombres === '' || strlen($nombres) > 50 || strlen($apellidos) > 50 || !in_array($estatus, ['Activo', 'Inactivo', 'Deuda'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Completa los campos obligatorios con valores válidos.']);
    exit;
}
if ($tlf === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El teléfono debe tener formato 04xx-xxxxxxx (ejemplo: 0412-1234567).']);
    exit;
}
if ($email !== '' && !emailValido($email)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El correo electrónico no tiene un formato válido.']);
    exit;
}
$fechaParseada = $fecha_vencimiento !== '' ? DateTime::createFromFormat('!Y-m-d', $fecha_vencimiento) : null;
if ($fecha_vencimiento !== '' && (!$fechaParseada || $fechaParseada->format('Y-m-d') !== $fecha_vencimiento)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'La fecha de vencimiento no es válida.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'UPDATE clientes SET ci = ?, nombres = ?, apellidos = ?, tlf = ?, email = ?, direccion = ?, estatus = ?, fecha_vencimiento = ? WHERE id = ?'
    );
    $stmt->execute([$ci, $nombres, $apellidos, $tlf, $email, $direccion, $estatus, $fecha_vencimiento !== '' ? $fecha_vencimiento : null, $id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code($e->getCode() === '23000' ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getCode() === '23000' ? 'La identificación ya está asignada a otro cliente.' : 'No se pudo actualizar el cliente.'
    ]);
}
