<?php
require_once __DIR__ . '/../includes/conexion.php';
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || strlen($password) < 10 || strlen($password) > 72) {
        $message = 'Enlace no válido o contraseña menor a 10 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT operator_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() FOR UPDATE');
            $stmt->execute([hash('sha256', $token)]);
            $operatorId = $stmt->fetchColumn();
            if (!$operatorId) {
                $pdo->rollBack();
                $message = 'El enlace expiró o ya fue utilizado. Solicita uno nuevo.';
            } else {
                $stmt = $pdo->prepare('UPDATE operator SET PW = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $operatorId]);
                $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE operator_id = ?');
                $stmt->execute([$operatorId]);
                $pdo->commit();
                $success = true;
                $message = 'Contraseña actualizada. Ya puedes iniciar sesión.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            error_log($e->getMessage());
            $message = 'No se pudo actualizar la contraseña.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Restablecer contraseña</title><link rel="stylesheet" href="../assets/css/app.css"></head>
<body>
<main id="loginpage">
    <form id="formu" method="post">
        <h3>RESTABLECER CONTRASEÑA</h3>
        <?php if ($message !== ''): ?><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if (!$success): ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="password" name="password" placeholder="Nueva contraseña (mínimo 10 caracteres)" minlength="10" required>
            <input type="submit" id="send" value="Guardar contraseña">
        <?php else: ?>
            <a href="../Index.php">Ir al inicio de sesión</a>
        <?php endif; ?>
    </form>
</main>
</body>
</html>
