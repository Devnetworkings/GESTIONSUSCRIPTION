<?php

if (!function_exists('gggInstallationIsClean')) {
    function gggInstallationIsClean(): bool
    {
        $projectRoot = dirname(__DIR__);
        $envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';
        $productionConfig = $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'conexion.prod.php';

        if (is_file($envFile) || is_file($productionConfig)) {
            return false;
        }

        $dbName = getenv('DB_NAME');
        if (!is_string($dbName) || trim($dbName) === '') {
            if (PHP_OS_FAMILY === 'Windows'
                && strtolower(getenv('APP_ENV') ?: 'development') !== 'production') {
                $dbName = 'ggg';
            } else {
                return true;
            }
        }

        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $dbPort = filter_var(getenv('DB_PORT') ?: '3306', FILTER_VALIDATE_INT);
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPassword = getenv('DB_PASSWORD') ?: '';

        if (!preg_match('/^[A-Za-z0-9_]+$/', trim($dbName))
            || $dbPort === false || $dbPort < 1 || $dbPort > 65535
            || !is_string($dbHost) || $dbHost === '') {
            return true;
        }

        try {
            $pdo = new PDO(
                'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . trim($dbName) . ';charset=utf8mb4',
                $dbUser,
                $dbPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $applicationTables = [
                'clientes',
                'operator',
                'planes',
                'pagos',
                'app_settings',
                'audit_logs',
                'email_verification_tokens',
                'password_reset_tokens',
            ];

            return count(array_intersect($applicationTables, $tables)) === 0;
        } catch (Throwable $e) {
            return true;
        }
    }
}
