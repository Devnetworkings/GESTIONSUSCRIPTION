<?php
require 'conexion.php';
$token = $_GET['token'] ?? '';
$message = 'El enlace expiró o no es válido.';

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT operator_id FROM email_verification_tokens WHERE token_hash = ? AND expires_at > NOW() FOR UPDATE');
        $stmt->execute([hash('sha256', $token)]);
        $operatorId = $stmt->fetchColumn();
        if ($operatorId) {
            $stmt = $pdo->prepare('UPDATE operator SET contacto_verified_at = NOW() WHERE id = ?');
            $stmt->execute([$operatorId]);
            $stmt = $pdo->prepare('DELETE FROM email_verification_tokens WHERE operator_id = ?');
            $stmt->execute([$operatorId]);
            $pdo->commit();
            $message = 'Correo verificado. Ya puedes usarlo para recuperar la contraseña.';
        } else {
            $pdo->rollBack();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        error_log($e->getMessage());
        $message = 'No se pudo verificar el correo. Solicita un enlace nuevo al administrador.';
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verificación de correo</title><link rel="stylesheet" href="app.css"></head>
<body><main id="loginpage"><section id="formu"><h3>VERIFICACIÓN DE CORREO</h3><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><a href="Index.php">Ir al inicio de sesión</a></section></main></body>
</html>
