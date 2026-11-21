<?php

function enviarCorreoTexto(string $destinatario, string $asunto, string $mensaje): void
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('No están instaladas las dependencias de correo. Ejecuta composer install.');
    }
    require_once $autoload;

    $host = getenv('SMTP_HOST');
    $username = getenv('SMTP_USERNAME');
    $password = getenv('SMTP_PASSWORD');
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $username;
    $fromName = getenv('SMTP_FROM_NAME') ?: 'Sistema de gestión GGG';
    $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
    $port = filter_var(getenv('SMTP_PORT') ?: '587', FILTER_VALIDATE_INT);

    if (!$host || !$username || !$password || !$fromEmail || !$port || $port < 1 || $port > 65535) {
        throw new RuntimeException('Configura SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD y SMTP_PORT válidos.');
    }
    if (!in_array($secure, ['tls', 'ssl'], true)) {
        throw new RuntimeException('SMTP_SECURE debe ser tls o ssl.');
    }

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = $password;
    $mailer->SMTPSecure = $secure === 'ssl'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = $port;
    $mailer->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $mailer->Timeout = 15;
    $mailer->setFrom($fromEmail, $fromName);
    $mailer->addAddress($destinatario);
    $mailer->Subject = $asunto;
    $mailer->Body = $mensaje;
    $mailer->isHTML(false);
    $mailer->send();
}
