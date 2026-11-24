<?php

if (defined('GGG_ENTORNO_LISTO')) {
    return;
}
define('GGG_ENTORNO_LISTO', true);

$raizAplicacion = dirname(__DIR__);
$archivoEntorno = $raizAplicacion . DIRECTORY_SEPARATOR . '.env';
$cargadorComposer = $raizAplicacion . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($cargadorComposer)) {
    require_once $cargadorComposer;
}
require_once __DIR__ . DIRECTORY_SEPARATOR . 'env_loader.php';
if (is_file($archivoEntorno)) {
    gggLoadEnvFile($archivoEntorno);
}

date_default_timezone_set('America/Caracas');

if (strtolower(getenv('APP_ENV') ?: 'development') === 'production') {
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
