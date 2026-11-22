<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');
gggRequireCsrfToken();
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/cliente.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/auditoria.php';
require_once __DIR__ . '/../includes/auth_guard.php';
$currentOperator = requireActiveOperator($pdo);
requireOperatorRole($currentOperator, ['admin', 'operador']);

$tipoDocumento = strtoupper(trim($_POST['tipo_documento'] ?? ''));
$ciNumero = trim($_POST['ci'] ?? '');
$cedula = in_array($tipoDocumento, ['V', 'E', 'J', 'G', 'P'], true)
    && preg_match('/^\d{6,9}$/', $ciNumero)
    ? descomponerCedula($ciNumero, $tipoDocumento)
    : false;
$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$tlf = normalizarTelefono($_POST['tlf'] ?? '');
$email = trim($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($cedula === false) {
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
    $pdo->beginTransaction();
    $nuevoCliente = new Cliente($cedula['tipo_documento'], $cedula['ci'], $nombres, $apellidos, $tlf, $email, $direccion);
    $nuevoCliente->guardar($pdo);
    $stmt = $pdo->prepare('SELECT id FROM clientes WHERE tipo_documento <=> ? AND ci = ? LIMIT 1');
    $stmt->execute([$cedula['tipo_documento'], $cedula['ci']]);
    $idCliente = $stmt->fetchColumn();
    registrarAuditoria($pdo, 'cliente_creado', 'cliente', $idCliente ?: null, [
        'tipo_documento' => $cedula['tipo_documento'],
        'ci' => $cedula['ci'],
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'tlf' => $tlf,
        'email' => $email,
        'direccion' => $direccion
    ]);
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($e->getCode() === '23000' ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getCode() === '23000' ? 'Ya existe un cliente con esa cédula.' : 'No se pudo registrar el cliente.'
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'No se pudo registrar el cliente.']);
}
?>
