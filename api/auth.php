<?php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json; charset=utf-8');
gggRequireCsrfToken();
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auditoria.php';

$operador = $_POST['operator'] ?? '';
$password_ingresada = $_POST['password'] ?? '';

if (empty($operador) || empty($password_ingresada)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Faltan credenciales.']);
    exit;
}

try {
    reactivarOperadoresConVacacionesVencidas($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, PW, username, operator_role, operator_status, activo,
                (login_locked_until IS NOT NULL AND login_locked_until > NOW()) AS login_bloqueado
         FROM operator
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $operador]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    $cuentaActiva = $usuario
        && (int) $usuario['activo'] === 1
        && $usuario['operator_status'] === 'Activo';
    $accesoBloqueado = $usuario && (int) $usuario['login_bloqueado'] === 1;
    $claveValida = $cuentaActiva
        && !$accesoBloqueado
        && !empty($usuario['PW'])
        && password_verify($password_ingresada, $usuario['PW']);

    if ($claveValida) {
        $stmt = $pdo->prepare('UPDATE operator SET login_failed_attempts = 0, login_locked_until = NULL WHERE id = ?');
        $stmt->execute([$usuario['id']]);
        session_regenerate_id(true);
        $_SESSION['operador_logueado'] = true;
        $_SESSION['operador_id'] = $usuario['id'];
        $_SESSION['operador_nombre'] = $usuario['username'];
        $_SESSION['operador_role'] = $usuario['operator_role'] ?? 'operador';
        registrarAuditoria($pdo, 'inicio_sesion', 'operador', $usuario['id'], [], $usuario['id'], $usuario['username']);

        ob_clean();
        echo json_encode(['success' => true, 'operador' => $usuario['username'], 'role' => $_SESSION['operador_role']]);
    } else {
        if ($cuentaActiva && !$accesoBloqueado && !empty($usuario['PW'])) {
            $stmt = $pdo->prepare(
                'UPDATE operator
                 SET login_locked_until = CASE
                         WHEN login_locked_until IS NOT NULL AND login_locked_until <= NOW() THEN NULL
                         WHEN login_failed_attempts >= 2 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                         ELSE NULL
                     END,
                     login_failed_attempts = CASE
                         WHEN login_failed_attempts >= 3 THEN 1
                         ELSE login_failed_attempts + 1
                     END
                 WHERE id = ? AND (login_locked_until IS NULL OR login_locked_until <= NOW())'
            );
            $stmt->execute([$usuario['id']]);
        }

        registrarAuditoria($pdo, 'inicio_sesion_fallido', 'operador', null, ['username' => $operador], null, 'Anónimo');
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Credenciales inválidas o acceso temporalmente bloqueado. Tras 3 intentos fallidos, el acceso se bloquea durante 15 minutos. Usa «Olvidé mi contraseña» para recibir un enlace de cambio de clave.'
        ]);
    }
} catch (PDOException $e) {
    error_log('Operator authentication failed: ' . $e->getMessage());
    http_response_code(503);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo completar el inicio de sesión. Verifica la conexión y que la base tenga el esquema actual.'
    ]);
}