<?php
// Archivo: register.php
ob_start(); // Encendemos la aspiradora para atrapar espacios fantasma[cite: 10]
require_once __DIR__ . '/../includes/entorno.php';
session_start();
ini_set('display_errors', 0); //[cite: 10]
error_reporting(0); //[cite: 10]

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/operador.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/email_verification.php';
header('Content-Type: application/json'); //[cite: 10]

try {
    $operatorCount = (int) $pdo->query('SELECT COUNT(*) FROM operator')->fetchColumn();
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo verificar el estado de las cuentas.']);
    exit;
}

$role = 'operador';
if ($operatorCount === 0) {
    $role = 'admin';
} else {
    $stmtRole = $pdo->prepare('SELECT operator_role FROM operator WHERE id = ? AND activo = 1 LIMIT 1');
    $stmtRole->execute([$_SESSION['operador_id'] ?? '']);
    if ($stmtRole->fetchColumn() !== 'admin') {
        http_response_code(403);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Solo un administrador puede registrar operadores.']);
        exit;
    }
    $roleSolicitado = $_POST['operator_role'] ?? 'operador';
    if (!in_array($roleSolicitado, ['admin', 'operador'], true)) {
        http_response_code(422);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El rol solicitado no es válido.']);
        exit;
    }
    $role = $roleSolicitado;
}

// Captura de datos corregida
$tipo_doc = $_POST['tipo_doc'] ?? 'V'; //[cite: 10]
$ci_numero = $_POST['ci'] ?? ''; // ¡Aquí atrapamos el número real!
$ci = normalizarCedula($ci_numero, $tipo_doc);
$nombres = $_POST['nombres'] ?? ''; //[cite: 10]
$apellidos = $_POST['apellidos'] ?? ''; //[cite: 10]
$contacto = $_POST['contacto'] ?? null; //[cite: 10]
$direccion = $_POST['direccion'] ?? null; //[cite: 10]
$tlf = $_POST['tlf'] ?? ''; //[cite: 10]
$username = $_POST['username'] ?? ''; //[cite: 10]
$pw = $_POST['pw'] ?? ''; //[cite: 10]

// Validación
if ($ci === false || empty($nombres) || strlen($nombres) > 50 || empty($apellidos) || strlen($apellidos) > 50 || empty($username) || strlen($username) > 50 || strlen($pw) < 10 || strlen($pw) > 72) {
    ob_clean(); // Limpiamos el escenario antes de enviar[cite: 10]
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Revisa la cédula (6 a 9 dígitos), nombres, usuario y contraseña (10 a 72 bytes).']);
    exit;
}
if (($tlf = normalizarTelefono($tlf)) === false) {
    ob_clean();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El teléfono debe tener formato 04xx-xxxxxxx (ejemplo: 0412-1234567).']);
    exit;
}
if (!emailValido($contacto)) {
    ob_clean();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El correo de contacto no es válido.']);
    exit;
}

try {
    // ¡Acción! Instanciamos a nuestro operador y lo guardamos
    $nuevoOperador = new Operador($ci, $nombres, $apellidos, $contacto, $direccion, $tlf, $username, $pw, $role);
    $nuevoOperador->guardar($pdo);
    $stmtId = $pdo->prepare('SELECT id FROM operator WHERE username = ? LIMIT 1');
    $stmtId->execute([$username]);
    $operadorId = $stmtId->fetchColumn();
    $correoEnviado = $operadorId && enviarVerificacionContacto($pdo, $operadorId, $username, $contacto);

    ob_clean(); // Limpiamos el escenario antes de enviar[cite: 10]
    echo json_encode([
        'success' => true,
        'message' => $correoEnviado
            ? 'Operador creado. Revisa el correo y confirma el enlace para habilitar la recuperación de contraseña.'
            : 'Operador creado; el correo todavía no está verificado. Configura el correo del servidor y solicita el enlace desde Configuración.'
    ]); //[cite: 10]

} catch (PDOException $e) {
    ob_clean(); // Limpiamos el escenario antes de enviar[cite: 10]
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => '¡Cédula o teléfono ya registrados!']); //[cite: 10]
    } else {
        // Agregué el mensaje real del error para que te sea más fácil depurar en el futuro
        echo json_encode(['success' => false, 'message' => 'Error interno en la bóveda: ' . $e->getMessage()]);
    }
}
?>