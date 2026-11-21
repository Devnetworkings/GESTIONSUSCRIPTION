<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auditoria.php';
if (isset($_SESSION['operador_id'])) {
    registrarAuditoria(
        $pdo,
        'cierre_sesion',
        'operador',
        (string) $_SESSION['operador_id'],
        [],
        (string) $_SESSION['operador_id'],
        (string) ($_SESSION['operador_nombre'] ?? '')
    );
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ../index.php');
exit;
?>
