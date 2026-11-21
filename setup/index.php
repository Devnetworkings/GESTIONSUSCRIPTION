<?php
$projectRoot = dirname(__DIR__);
$enableFile = $projectRoot . DIRECTORY_SEPARATOR . '.setup-enabled';
$progressFile = $projectRoot . DIRECTORY_SEPARATOR . '.setup-progress';
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';

require_once $projectRoot . '/includes/entorno.php';
require_once $projectRoot . '/includes/estado_instalacion.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!gggInstallationIsClean()) {
    require_once $projectRoot . '/includes/conexion.php';
    require_once $projectRoot . '/includes/auth_guard.php';

    $setupOperator = requireActiveOperator($pdo);
    requireOperatorRole($setupOperator, ['soporte_ti']);
}

if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

function gggSetupEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function gggSetupPostString(string $name, bool $trim = true): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        throw new RuntimeException('El formulario contiene un valor no válido.');
    }

    return $trim ? trim($value) : $value;
}

function gggSetupEnvValue(string $value): string
{
    if (preg_match('/[\r\n\x00]/', $value)) {
        throw new RuntimeException('Un valor de configuración contiene caracteres no permitidos.');
    }

    return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
}

function gggSetupWriteEnv(string $path, string $contents): void
{
    if (is_file($path)) {
        throw new RuntimeException('Ya existe .env y no se sobrescribirá. Revisa esa configuración antes de continuar.');
    }

    $temporaryPath = tempnam(dirname($path), '.env-install-');
    if ($temporaryPath === false) {
        throw new RuntimeException('PHP no pudo crear el archivo temporal de configuración.');
    }

    try {
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('No se pudo escribir la configuración .env.');
        }
        @chmod($temporaryPath, 0600);
        if (file_exists($path) || !rename($temporaryPath, $path)) {
            throw new RuntimeException('No se pudo guardar .env. Revisa los permisos de la carpeta del proyecto.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

function gggSetupTables(PDO $pdo): array
{
    return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
}

function gggSetupHasApplicationTables(array $tables): bool
{
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

    return count(array_intersect($applicationTables, $tables)) > 0;
}

function gggSetupLock(string $enableFile, string $message): void
{
    if (is_file($enableFile) && !unlink($enableFile)) {
        error_log('Could not remove .setup-enabled after detecting an existing installation.');
    }

    http_response_code(409);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Instalador bloqueado</title></head>'
        . '<body><main><h1>Instalador bloqueado</h1><p>'
        . gggSetupEscape($message)
        . '</p><p>No se modificó la base de datos.</p><a href="../index.php">Ir al inicio</a></main></body></html>';
    exit;
}

function gggSetupCheckEmptyDatabase(PDO $pdo, array $tables, bool $resume): void
{
    if (!$resume && $tables) {
        throw new RuntimeException('La base de datos no está vacía. El asistente solo instala en una base nueva; no modifica instalaciones existentes.');
    }

    $knownTables = [
        'clientes',
        'operator',
        'planes',
        'pagos',
        'app_settings',
        'audit_logs',
        'email_verification_tokens',
        'password_reset_tokens',
    ];
    foreach ($tables as $table) {
        if (!in_array($table, $knownTables, true)) {
            throw new RuntimeException('La base contiene tablas ajenas al asistente. Usa una base de datos vacía.');
        }
    }

    if ($resume) {
        foreach (['clientes', 'pagos', 'operator', 'audit_logs', 'email_verification_tokens', 'password_reset_tokens'] as $table) {
            if (in_array($table, $tables, true)) {
                $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
                if ($count > 0) {
                    throw new RuntimeException('La base ya contiene datos. El asistente no continuará para evitar alterarlos.');
                }
            }
        }
    }
}

function gggSetupEnvContent(array $db, string $baseUrl): string
{
    $values = [
        'APP_ENV' => 'production',
        'APP_BASE_URL' => $baseUrl,
        'DB_HOST' => $db['host'],
        'DB_PORT' => (string) $db['port'],
        'DB_NAME' => $db['name'],
        'DB_USER' => $db['user'],
        'DB_PASSWORD' => $db['password'],
        'SMTP_HOST' => '',
        'SMTP_PORT' => '587',
        'SMTP_SECURE' => 'tls',
        'SMTP_USERNAME' => '',
        'SMTP_PASSWORD' => '',
        'SMTP_FROM_EMAIL' => '',
        'SMTP_FROM_NAME' => 'Sistema de gestión GGG',
    ];

    $lines = [];
    foreach ($values as $key => $value) {
        $lines[] = $key . '=' . gggSetupEnvValue($value);
    }
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

$errorMessage = '';
$successMessage = '';
$preflightWarning = '';
$form = [
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'app_base_url' => '',
    'admin_tipo_doc' => 'V',
    'admin_ci' => '',
    'admin_nombres' => '',
    'admin_apellidos' => '',
    'admin_tlf' => '',
    'admin_email' => '',
    'admin_username' => '',
];

$productionConfig = $projectRoot . '/includes/conexion.prod.php';
if (is_file($envFile)) {
    gggSetupLock($enableFile, 'Ya existe .env. Para proteger la configuración actual, el asistente no puede usarse en esta instalación.');
}
if (is_file($productionConfig)) {
    gggSetupLock($enableFile, 'Ya existe includes/conexion.prod.php. Conserva la configuración actual y no uses el asistente en esta instalación.');
}

if (!is_file($progressFile)) {
    $configuredDbName = getenv('DB_NAME');
    if (($configuredDbName === false || trim($configuredDbName) === '')
        && PHP_OS_FAMILY === 'Windows'
        && strtolower(getenv('APP_ENV') ?: 'development') !== 'production') {
        $configuredDbName = 'ggg';
    }

    if (is_string($configuredDbName) && trim($configuredDbName) !== '') {
        $configuredDbName = trim($configuredDbName);
        $configuredDbHost = getenv('DB_HOST') ?: '127.0.0.1';
        $configuredDbPort = filter_var(getenv('DB_PORT') ?: '3306', FILTER_VALIDATE_INT);
        $configuredDbUser = getenv('DB_USER') ?: 'root';
        $configuredDbPassword = getenv('DB_PASSWORD') ?: '';

        if (!preg_match('/^[A-Za-z0-9_]+$/', $configuredDbName)
            || $configuredDbHost === '' || strlen($configuredDbHost) > 253
            || preg_match('/[\x00-\x20;]/', $configuredDbHost)) {
            $preflightWarning = 'No se pudo comprobar automáticamente la base configurada porque DB_HOST o DB_NAME no son válidos.';
        } elseif ($configuredDbPort === false || $configuredDbPort < 1 || $configuredDbPort > 65535) {
            $preflightWarning = 'No se pudo comprobar automáticamente la base configurada porque DB_PORT no es válido. Verifica la base antes de continuar.';
        } else {
            try {
                $configuredPdo = new PDO(
                    'mysql:host=' . $configuredDbHost . ';port=' . $configuredDbPort . ';dbname=' . $configuredDbName . ';charset=utf8mb4',
                    $configuredDbUser,
                    $configuredDbPassword,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
                );
                if (gggSetupHasApplicationTables(gggSetupTables($configuredPdo))) {
                    gggSetupLock($enableFile, 'Se detectaron tablas de una instalación existente en la base configurada. El asistente solo se puede usar antes de instalar.');
                }
            } catch (PDOException $e) {
                error_log('Could not inspect the configured database before setup: ' . $e->getMessage());
                $preflightWarning = 'No se pudo comprobar automáticamente la base configurada. Asegúrate de seleccionar una base vacía; el asistente volverá a verificarla antes de instalar.';
            }
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($form as $key => $default) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            $form[$key] = trim($_POST[$key]);
        }
    }

    try {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['setup_csrf'], $csrf)) {
            throw new RuntimeException('La sesión de instalación expiró. Recarga la página e inténtalo otra vez.');
        }
        if (is_file($envFile)) {
            throw new RuntimeException('Ya existe .env. El asistente no sobrescribe credenciales existentes; usa la configuración manual o mueve ese archivo solo después de respaldarlo.');
        }
        if (!is_writable($projectRoot)) {
            throw new RuntimeException('PHP necesita permiso temporal para escribir .env en la carpeta del proyecto.');
        }

        $dbHost = gggSetupPostString('db_host');
        $dbPort = filter_var(gggSetupPostString('db_port'), FILTER_VALIDATE_INT);
        $dbName = gggSetupPostString('db_name');
        $dbUser = gggSetupPostString('db_user');
        $dbPassword = gggSetupPostString('db_password', false);
        $baseUrl = rtrim(gggSetupPostString('app_base_url'), '/');
        $adminType = strtoupper(gggSetupPostString('admin_tipo_doc'));
        $adminCi = gggSetupPostString('admin_ci');
        $adminNames = gggSetupPostString('admin_nombres');
        $adminLastNames = gggSetupPostString('admin_apellidos');
        $adminEmail = gggSetupPostString('admin_email');
        $adminUsername = gggSetupPostString('admin_username');
        $adminPassword = gggSetupPostString('admin_password', false);

        require_once $projectRoot . '/includes/validaciones.php';
        require_once $projectRoot . '/includes/operador.php';
        $adminPhone = normalizarTelefono(gggSetupPostString('admin_tlf'));

        if ($dbHost === '' || strlen($dbHost) > 253 || preg_match('/[\x00-\x20;]/', $dbHost)) {
            throw new RuntimeException('El host de MySQL no es válido.');
        }
        if ($dbPort === false || $dbPort < 1 || $dbPort > 65535) {
            throw new RuntimeException('El puerto de MySQL debe estar entre 1 y 65535.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new RuntimeException('El nombre de la base debe contener solo letras, números y guion bajo.');
        }
        if ($dbUser === '' || preg_match('/[\r\n\x00]/', $dbUser) || preg_match('/[\r\n\x00]/', $dbPassword)) {
            throw new RuntimeException('Escribe un usuario de base de datos válido.');
        }
        $urlParts = filter_var($baseUrl, FILTER_VALIDATE_URL) ? parse_url($baseUrl) : false;
        if (!is_array($urlParts) || !in_array(strtolower($urlParts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($urlParts['host']) || isset($urlParts['query']) || isset($urlParts['fragment'])
            || isset($urlParts['user']) || isset($urlParts['pass'])) {
            throw new RuntimeException('Escribe la URL pública de la aplicación, por ejemplo https://gimnasio.example.com.');
        }
        $urlScheme = strtolower($urlParts['scheme']);
        $urlHost = strtolower($urlParts['host']);
        if ($urlScheme !== 'https' && !in_array($urlHost, ['localhost', '127.0.0.1', '[::1]'], true)) {
            throw new RuntimeException('Usa HTTPS para la URL pública; HTTP solo se permite en localhost.');
        }
        if (!in_array($adminType, ['V', 'E', 'J', 'G', 'P'], true)
            || !preg_match('/^\d{6,9}$/', $adminCi)
            || $adminNames === '' || strlen($adminNames) > 50
            || $adminLastNames === '' || strlen($adminLastNames) > 50
            || $adminPhone === false
            || !emailValido($adminEmail)
            || $adminUsername === '' || strlen($adminUsername) > 50
            || strlen($adminPassword) < 10 || strlen($adminPassword) > 72) {
            throw new RuntimeException('Revisa los datos del administrador: cédula, nombres, teléfono, correo y usuario deben ser válidos; la contraseña requiere 10 a 72 bytes.');
        }

        foreach ([
            'DB_HOST' => $dbHost,
            'DB_PORT' => (string) $dbPort,
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASSWORD' => $dbPassword,
            'APP_BASE_URL' => $baseUrl,
        ] as $key => $value) {
            $currentValue = getenv($key);
            if ($currentValue !== false && $currentValue !== $value) {
                throw new RuntimeException('Hay variables de entorno del servidor que no coinciden con el formulario. Alinea esas variables o usa la instalación manual.');
            }
        }

        $db = [
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => $dbName,
            'user' => $dbUser,
            'password' => $dbPassword,
        ];
        $envContents = gggSetupEnvContent($db, $baseUrl);
        $pdo = new PDO(
            'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4',
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );

        $fingerprint = hash('sha256', strtolower($dbHost) . ':' . $dbPort . '/' . $dbName);
        $resume = false;
        if (is_file($progressFile)) {
            $progress = json_decode((string) file_get_contents($progressFile), true);
            if (!is_array($progress) || !hash_equals((string) ($progress['fingerprint'] ?? ''), $fingerprint)) {
                throw new RuntimeException('Hay una instalación incompleta para otra base de datos. Retira .setup-progress después de verificar la base correcta.');
            }
            $resume = true;
        }

        $tables = gggSetupTables($pdo);
        if (!$resume && gggSetupHasApplicationTables($tables)) {
            gggSetupLock($enableFile, 'Se detectaron tablas de una instalación existente en la base seleccionada. El asistente se ha bloqueado y no modificó la base.');
        }
        if (!$resume) {
            gggSetupCheckEmptyDatabase($pdo, $tables, false);
            $progressContents = json_encode(['fingerprint' => $fingerprint]);
            if (file_put_contents($progressFile, $progressContents, LOCK_EX) !== strlen($progressContents)) {
                throw new RuntimeException('No se pudo preparar el estado de instalación.');
            }
            @chmod($progressFile, 0600);
        }

        $completed = false;
        if (in_array('app_settings', $tables, true)) {
            $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'setup_completed' LIMIT 1");
            $stmt->execute();
            $completed = $stmt->fetchColumn() === '1';
        }
        if ($completed) {
            if (!is_file($envFile)) {
                gggSetupWriteEnv($envFile, $envContents);
            }
        } else {
            gggSetupCheckEmptyDatabase($pdo, $tables, $resume);
        }

        if (!$completed) {
            $schemaFile = $projectRoot . '/database/schema.sql';
            $schema = file_get_contents($schemaFile);
            if ($schema === false) {
                throw new RuntimeException('No se pudo leer database/schema.sql.');
            }
            $statements = preg_split('/;\s*(?:\r\n|\n|\r|$)/', trim($schema));
            foreach ($statements as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_settings WHERE setting_key = 'setup_completed' AND setting_value = '1'");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                $completed = true;
            } else {
                $stmt = $pdo->query('SELECT COUNT(*) FROM operator');
                if ((int) $stmt->fetchColumn() > 0) {
                    throw new RuntimeException('Ya existe un operador. Usa la configuración manual para una instalación que ya comenzó.');
                }
                $adminCedula = normalizarCedula($adminCi, $adminType);
                $admin = new Operador(
                    $adminCedula,
                    $adminNames,
                    $adminLastNames,
                    $adminEmail,
                    '',
                    $adminPhone,
                    $adminUsername,
                    $adminPassword,
                    'admin'
                );
                $admin->guardar($pdo);
                $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = '1' WHERE setting_key = 'setup_completed'");
                $stmt->execute();
                $pdo->commit();
                $completed = true;
            }
        }

        if ($completed && !is_file($envFile)) {
            gggSetupWriteEnv($envFile, $envContents);
        }

        if (!$completed) {
            throw new RuntimeException('No se pudo confirmar la instalación. Revisa la conexión y vuelve a intentarlo.');
        }

        $cleanupWarning = '';
        if (is_file($progressFile) && !unlink($progressFile)) {
            $cleanupWarning = 'Borra manualmente .setup-progress después de completar la instalación.';
        }
        if (is_file($enableFile) && !unlink($enableFile)) {
            $cleanupWarning = 'Borra manualmente .setup-enabled para cerrar el asistente.';
        }
        $successMessage = 'Instalación completada. Ya puedes iniciar sesión con el administrador que acabas de crear.';
        if ($cleanupWarning !== '') {
            $successMessage .= ' ' . $cleanupWarning;
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Installation setup failed: ' . $e->getMessage());
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se pudo completar la instalación. Revisa la conexión y los permisos de la base de datos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalar sistema GGG</title>
    <style>
        body { margin: 0; padding: 32px 16px; background: #f1f5f9; color: #172033; font: 16px Arial, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 32px; border-radius: 12px; background: #fff; box-shadow: 0 12px 36px #0f172a18; }
        h1 { margin-top: 0; } h2 { margin-top: 28px; font-size: 1.15rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        label { display: flex; flex-direction: column; gap: 6px; margin: 8px 0; font-weight: 600; }
        input, select { box-sizing: border-box; width: 100%; padding: 11px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        button { margin-top: 20px; padding: 12px 18px; border: 0; border-radius: 6px; background: #1468a8; color: #fff; font-weight: 700; cursor: pointer; }
        .notice { padding: 12px; border-radius: 6px; background: #eff6ff; } .error { background: #fef2f2; color: #991b1b; } .success { background: #f0fdf4; color: #166534; }
        small { color: #475569; font-weight: 400; } a { color: #1468a8; }
    </style>
</head>
<body>
<main>
    <h1>Instalación del sistema GGG</h1>
    <p class="notice">Usa una base de datos nueva y vacía. El asistente crea las tablas actuales, el plan inicial y un administrador. No cambia una instalación existente.</p>
    <?php if ($preflightWarning !== ''): ?><p class="notice error"><?= gggSetupEscape($preflightWarning) ?></p><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><p class="notice error"><?= gggSetupEscape($errorMessage) ?></p><?php endif; ?>
    <?php if ($successMessage !== ''): ?>
        <p class="notice success"><?= gggSetupEscape($successMessage) ?></p>
        <p><a href="../index.php">Ir al inicio de sesión</a></p>
    <?php else: ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= gggSetupEscape($_SESSION['setup_csrf']) ?>">
            <h2>Base de datos MySQL/MariaDB</h2>
            <div class="grid">
                <label>Servidor
                    <input name="db_host" value="<?= gggSetupEscape($form['db_host']) ?>" required>
                </label>
                <label>Puerto
                    <input type="number" name="db_port" min="1" max="65535" value="<?= gggSetupEscape($form['db_port']) ?>" required>
                </label>
                <label>Nombre de la base
                    <input name="db_name" value="<?= gggSetupEscape($form['db_name']) ?>" pattern="[A-Za-z0-9_]+" required>
                </label>
                <label>Usuario de la base
                    <input name="db_user" value="<?= gggSetupEscape($form['db_user']) ?>" required>
                </label>
                <label>Contraseña de la base
                    <input type="password" name="db_password" autocomplete="new-password">
                </label>
                <label>URL pública de la aplicación
                    <input type="url" name="app_base_url" value="<?= gggSetupEscape($form['app_base_url']) ?>" placeholder="https://gimnasio.example.com" required>
                </label>
            </div>
            <h2>Administrador inicial</h2>
            <p><small>La cuenta tendrá el rol de administrador. Podrás completar o editar sus datos desde el sistema.</small></p>
            <div class="grid">
                <label>Tipo de documento
                    <select name="admin_tipo_doc">
                        <?php foreach (['V', 'E', 'J', 'G', 'P'] as $type): ?>
                            <option value="<?= $type ?>" <?= $form['admin_tipo_doc'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Cédula (6 a 9 dígitos)
                    <input name="admin_ci" inputmode="numeric" pattern="[0-9]{6,9}" minlength="6" maxlength="9" value="<?= gggSetupEscape($form['admin_ci']) ?>" required>
                </label>
                <label>Nombres
                    <input name="admin_nombres" maxlength="50" value="<?= gggSetupEscape($form['admin_nombres']) ?>" required>
                </label>
                <label>Apellidos
                    <input name="admin_apellidos" maxlength="50" value="<?= gggSetupEscape($form['admin_apellidos']) ?>" required>
                </label>
                <label>Teléfono (ej. 0412-1234567)
                    <input name="admin_tlf" inputmode="tel" value="<?= gggSetupEscape($form['admin_tlf']) ?>" required>
                </label>
                <label>Correo electrónico
                    <input type="email" name="admin_email" maxlength="254" value="<?= gggSetupEscape($form['admin_email']) ?>" required>
                </label>
                <label>Usuario
                    <input name="admin_username" maxlength="50" value="<?= gggSetupEscape($form['admin_username']) ?>" required>
                </label>
                <label>Contraseña (10 a 72 bytes)
                    <input type="password" name="admin_password" minlength="10" maxlength="72" autocomplete="new-password" required>
                </label>
            </div>
            <button type="submit">Crear instalación</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
