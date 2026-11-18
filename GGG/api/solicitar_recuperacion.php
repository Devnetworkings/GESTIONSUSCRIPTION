<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';

$username = trim($_POST['username'] ?? '');
if ($username === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Escribe tu usuario.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, username, contacto, contacto_verified_at FROM operator WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$operator || empty($operator['contacto_verified_at']) || !filter_var($operator['contacto'] ?? '', FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => true, 'message' => 'Si la cuenta tiene un correo válido, recibirás un enlace para restablecer la contraseña.']);
        exit;
    }

    $baseUrl = ggg_app_base_url();
    if ($baseUrl === '') {
        error_log('APP_BASE_URL must be configured to send password reset links.');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'La recuperación no está configurada en el servidor.']);
        exit;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE operator_id = ?');
    $stmt->execute([(string) $operator['id']]);
    $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (operator_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
    $stmt->execute([(string) $operator['id'], hash('sha256', $token)]);
    $pdo->commit();

    $link = $baseUrl . '/pages/restablecer_clave.php?token=' . rawurlencode($token);
    $message = "Hola {$operator['username']},\n\nUsa este enlace antes de una hora para cambiar tu contraseña:\n{$link}\n\nSi no solicitaste el cambio, ignora este mensaje.";
    if (!mail($operator['contacto'], 'Restablecer contraseña', $message, "Content-Type: text/plain; charset=UTF-8\r\n")) {
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE operator_id = ?');
        $stmt->execute([(string) $operator['id']]);
        error_log('Password recovery email could not be sent.');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo. Revisa la configuración de correo del servidor.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Si la cuenta tiene un correo válido, recibirás un enlace para restablecer la contraseña.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'No se pudo procesar la solicitud de recuperación.']);
}
