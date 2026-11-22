<?php

function gggCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('La sesión debe iniciarse antes de generar el token CSRF.');
    }

    if (empty($_SESSION['ggg_csrf_token']) || !is_string($_SESSION['ggg_csrf_token'])) {
        $_SESSION['ggg_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['ggg_csrf_token'];
}

function gggRequireCsrfToken(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }

    $expected = $_SESSION['ggg_csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || $provided === '') {
        $provided = $_POST['csrf_token'] ?? '';
    }

    if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'La sesión expiró o la solicitud no es válida. Recarga la página.']);
        exit;
    }
}
