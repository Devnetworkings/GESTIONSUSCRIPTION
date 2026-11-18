<?php
function enviarVerificacionContacto(PDO $pdo, $operadorId, $username, $email)
{
    $baseUrl = ggg_app_base_url();
    if ($baseUrl === '') {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM email_verification_tokens WHERE operator_id = ?');
        $stmt->execute([(string) $operadorId]);
        $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (operator_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))');
        $stmt->execute([(string) $operadorId, hash('sha256', $token)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $link = $baseUrl . '/pages/verificar_correo.php?token=' . rawurlencode($token);
    $message = "Hola {$username},\n\nConfirma que tienes acceso a este correo abriendo el enlace antes de 24 horas:\n{$link}\n\nSi no esperabas este mensaje, ignóralo.";
    if (mail($email, 'Verifica tu correo', $message, "Content-Type: text/plain; charset=UTF-8\r\n")) {
        return true;
    }

    $stmt = $pdo->prepare('DELETE FROM email_verification_tokens WHERE operator_id = ?');
    $stmt->execute([(string) $operadorId]);
    error_log('Operator email verification message could not be sent.');
    return false;
}
