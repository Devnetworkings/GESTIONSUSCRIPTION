<?php

if (defined('GGG_ENTORNO_LISTO')) {
    return;
}

define('GGG_ENTORNO_LISTO', true);
define('GGG_ES_PRODUCCION', !(defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows'));

date_default_timezone_set('America/Caracas');

if (GGG_ES_PRODUCCION) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$tmpDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0700, true);
}
if (is_dir($tmpDir) && is_writable($tmpDir) && session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path($tmpDir);
}

if (!function_exists('ggg_app_base_url')) {
    function ggg_app_base_url(): string
    {
        $candidates = [
            getenv('APP_BASE_URL') ?: '',
            $_ENV['APP_BASE_URL'] ?? '',
            $_SERVER['APP_BASE_URL'] ?? '',
        ];
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return rtrim($value, '/');
            }
        }
        return '';
    }
}

if (ggg_app_base_url() === '' && !empty($_SERVER['HTTP_HOST'])) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $root = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $basePath = '';
    if ($docRoot !== '' && strncmp($root, $docRoot, strlen($docRoot)) === 0) {
        $basePath = substr($root, strlen($docRoot));
    }
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath;
    putenv('APP_BASE_URL=' . $baseUrl);
    $_ENV['APP_BASE_URL'] = $baseUrl;
    $_SERVER['APP_BASE_URL'] = $baseUrl;
}
