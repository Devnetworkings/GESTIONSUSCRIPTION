<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/auditoria.php';

$currentOperator = requireActiveOperator($pdo);
requireOperatorRole($currentOperator, ['admin', 'soporte_ti']);

$projectRoot = dirname(__DIR__);
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
$productionConfigPath = $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'conexion.prod.php';
$csrfSessionKey = 'configuracion_tecnica_csrf';
$configKeys = [
    'APP_BASE_URL',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'SMTP_HOST',
    'SMTP_PORT',
    'SMTP_SECURE',
    'SMTP_USERNAME',
    'SMTP_PASSWORD',
    'SMTP_FROM_EMAIL',
    'SMTP_FROM_NAME',
];
$storedConfigKeys = array_merge(['APP_ENV'], $configKeys);

function gggTecnicaPostString(string $name, bool $trim = true): string
{
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        throw new RuntimeException('El formulario contiene un valor no válido.');
    }

    return $trim ? trim($value) : $value;
}

function gggTecnicaReadEnv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer el archivo .env.');
    }
    if (!class_exists(\Dotenv\Dotenv::class)) {
        throw new RuntimeException('Instala las dependencias con composer install para editar .env.');
    }

    try {
        return \Dotenv\Dotenv::parse($contents);
    } catch (Throwable $e) {
        error_log('Could not parse .env for technical configuration: ' . $e->getMessage());
        throw new RuntimeException('No se pudo interpretar .env. Corrige el archivo manualmente antes de continuar.');
    }
}

function gggTecnicaEffectiveValue(string $key, array $fileValues, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false && isset($_ENV[$key]) && is_scalar($_ENV[$key])) {
        $value = (string) $_ENV[$key];
    }
    if ($value === false && isset($_SERVER[$key]) && is_scalar($_SERVER[$key])) {
        $value = (string) $_SERVER[$key];
    }
    if ($value === false && array_key_exists($key, $fileValues)) {
        $value = (string) $fileValues[$key];
    }

    return $value === false ? $default : (string) $value;
}

function gggTecnicaExternalOverrides(array $fileValues, array $keys): array
{
    $overrides = [];
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key]) && is_scalar($_ENV[$key])) {
            $value = (string) $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key]) && is_scalar($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
        }
        if ($value !== false
            && (!array_key_exists($key, $fileValues) || (string) $fileValues[$key] !== (string) $value)) {
            $overrides[] = $key;
        }
    }

    return $overrides;
}

function gggTecnicaCurrentConfig(string $envPath, array $keys): array
{
    $fileValues = gggTecnicaReadEnv($envPath);
    $defaults = [
        'APP_ENV' => PHP_OS_FAMILY === 'Windows' ? 'development' : 'production',
        'APP_BASE_URL' => '',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_NAME' => 'ggg',
        'DB_USER' => 'root',
        'DB_PASSWORD' => '',
        'SMTP_HOST' => '',
        'SMTP_PORT' => '587',
        'SMTP_SECURE' => 'tls',
        'SMTP_USERNAME' => '',
        'SMTP_PASSWORD' => '',
        'SMTP_FROM_EMAIL' => '',
        'SMTP_FROM_NAME' => 'Sistema de gestión GGG',
    ];
    $values = [];
    foreach ($keys as $key) {
        $values[$key] = gggTecnicaEffectiveValue($key, $fileValues, $defaults[$key] ?? '');
    }
    foreach ([
        'APP_ENV' => PHP_OS_FAMILY === 'Windows' ? 'development' : 'production',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_NAME' => 'ggg',
        'DB_USER' => 'root',
        'SMTP_PORT' => '587',
        'SMTP_SECURE' => 'tls',
        'SMTP_FROM_NAME' => 'Sistema de gestión GGG',
    ] as $key => $default) {
        if ($values[$key] === '') {
            $values[$key] = $default;
        }
    }

    return [$values, $fileValues];
}

function gggTecnicaEnvValue(string $value): string
{
    if (preg_match('/[\r\n\x00]/', $value)) {
        throw new RuntimeException('Un valor de configuración contiene caracteres no permitidos.');
    }

    return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
}

function gggTecnicaWriteEnv(string $path, array $values): void
{
    $contents = '';
    if (is_file($path)) {
        if (!is_writable($path)) {
            throw new RuntimeException('PHP no tiene permiso para modificar .env.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer .env antes de actualizarlo.');
        }
        if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
            $contents = substr($contents, 3);
        }
    } elseif (!is_writable(dirname($path))) {
        throw new RuntimeException('PHP no tiene permiso para crear .env en la carpeta del proyecto.');
    }

    foreach ($values as $key => $value) {
        $pattern = '/^(?:export[ \t]+)?[ \t]*' . preg_quote($key, '/') . '[ \t]*=.*\r?$/m';
        preg_match_all($pattern, $contents, $matches);
        if (count($matches[0]) > 1) {
            throw new RuntimeException('Hay claves duplicadas en .env. Corrígelas manualmente antes de continuar.');
        }
        $line = $key . '=' . gggTecnicaEnvValue($value);
        if ($matches[0]) {
            $contents = preg_replace_callback(
                $pattern,
                static function () use ($line): string {
                    return $line;
                },
                $contents,
                1
            );
            if ($contents === null) {
                throw new RuntimeException('No se pudo preparar el archivo .env.');
            }
        } else {
            if ($contents !== '' && !preg_match('/(?:\r\n|\n|\r)$/', $contents)) {
                $contents .= PHP_EOL;
            }
            $contents .= $line . PHP_EOL;
        }
    }

    $temporaryPath = tempnam(dirname($path), '.env-config-');
    if ($temporaryPath === false) {
        throw new RuntimeException('No se pudo crear un archivo temporal para .env.');
    }

    try {
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('No se pudo escribir la nueva configuración.');
        }
        if (PHP_OS_FAMILY !== 'Windows' && !chmod($temporaryPath, 0600)) {
            throw new RuntimeException('No se pudieron limitar los permisos de .env.');
        }
        if (!rename($temporaryPath, $path)) {
            throw new RuntimeException('No se pudo reemplazar .env. Revisa los permisos de la carpeta.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

function gggTecnicaValidHost(string $host): bool
{
    return $host !== '' && strlen($host) <= 253 && !preg_match('/[\x00-\x20;]/', $host);
}

function gggTecnicaValidBaseUrl(string $baseUrl): bool
{
    if ($baseUrl === '' || strlen($baseUrl) > 2048) {
        return false;
    }
    $parts = filter_var($baseUrl, FILTER_VALIDATE_URL) ? parse_url($baseUrl) : false;
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])
        || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }

    return strtolower($parts['scheme']) === 'https'
        || in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true);
}

function gggTecnicaLockReason(string $envPath, string $productionConfigPath, array $externalOverrides): string
{
    if (is_file($productionConfigPath)) {
        return 'La conexión se administra desde includes/conexion.prod.php; edita ese archivo de forma segura en el servidor.';
    }
    if ($externalOverrides) {
        return 'Estas variables están definidas fuera de .env y prevalecen sobre él: ' . implode(', ', $externalOverrides) . '. Modifícalas desde la configuración del hosting.';
    }
    if (!is_writable(dirname($envPath)) || (is_file($envPath) && !is_writable($envPath))) {
        return 'PHP no tiene permiso para escribir .env. Ajusta los permisos en el servidor antes de editar la configuración.';
    }

    return '';
}

if (empty($_SESSION[$csrfSessionKey])) {
    $_SESSION[$csrfSessionKey] = bin2hex(random_bytes(32));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

try {
    list($currentConfig, $fileValues) = gggTecnicaCurrentConfig($envPath, $storedConfigKeys);
    $externalOverrides = gggTecnicaExternalOverrides($fileValues, $configKeys);
    $lockReason = gggTecnicaLockReason($envPath, $productionConfigPath, $externalOverrides);

    if ($method === 'GET') {
        $configForDisplay = array_intersect_key($currentConfig, array_flip($configKeys));
        if (is_file($productionConfigPath)) {
            $configForDisplay = [];
        }
        echo json_encode([
            'success' => true,
            'config' => array_diff_key($configForDisplay, ['DB_PASSWORD' => true, 'SMTP_PASSWORD' => true]),
            'editable' => $lockReason === '',
            'message' => $lockReason,
            'csrf_token' => $_SESSION[$csrfSessionKey],
        ]);
        exit;
    }

    if ($lockReason !== '') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $lockReason]);
        exit;
    }
    $csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION[$csrfSessionKey], $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'La sesión de configuración expiró. Recarga el panel.']);
        exit;
    }

    $currentPassword = gggTecnicaPostString('current_password', false);
    $passwordQuery = $pdo->prepare('SELECT PW FROM operator WHERE id = ? LIMIT 1');
    $passwordQuery->execute([$_SESSION['operador_id']]);
    $passwordHash = $passwordQuery->fetchColumn();
    if (strlen($currentPassword) > 72 || !is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'La contraseña actual no es correcta.']);
        exit;
    }

    $dbHost = gggTecnicaPostString('DB_HOST');
    $dbPort = filter_var(gggTecnicaPostString('DB_PORT'), FILTER_VALIDATE_INT);
    $dbName = gggTecnicaPostString('DB_NAME');
    $dbUser = gggTecnicaPostString('DB_USER');
    $postedDbPassword = gggTecnicaPostString('DB_PASSWORD', false);
    $smtpHost = gggTecnicaPostString('SMTP_HOST');
    $smtpPort = filter_var(gggTecnicaPostString('SMTP_PORT'), FILTER_VALIDATE_INT);
    $smtpSecure = strtolower(gggTecnicaPostString('SMTP_SECURE'));
    $smtpUsername = gggTecnicaPostString('SMTP_USERNAME');
    $postedSmtpPassword = gggTecnicaPostString('SMTP_PASSWORD', false);
    $smtpFromEmail = gggTecnicaPostString('SMTP_FROM_EMAIL');
    $smtpFromName = gggTecnicaPostString('SMTP_FROM_NAME');
    $baseUrl = rtrim(gggTecnicaPostString('APP_BASE_URL'), '/');

    $clearDbPassword = ($_POST['clear_db_password'] ?? '') === '1';
    $clearSmtpPassword = ($_POST['clear_smtp_password'] ?? '') === '1';
    $dbPassword = $clearDbPassword
        ? ''
        : ($postedDbPassword !== '' ? $postedDbPassword : $currentConfig['DB_PASSWORD']);
    $smtpPassword = $clearSmtpPassword
        ? ''
        : ($postedSmtpPassword !== '' ? $postedSmtpPassword : $currentConfig['SMTP_PASSWORD']);

    if (!gggTecnicaValidHost($dbHost) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)
        || $dbPort === false || $dbPort < 1 || $dbPort > 65535
        || $dbUser === '' || strlen($dbUser) > 128 || preg_match('/[\r\n\x00]/', $dbUser)
        || strlen($dbPassword) > 4096 || preg_match('/[\r\n\x00]/', $dbPassword)) {
        throw new RuntimeException('Verifica el servidor, puerto, nombre de base de datos y usuario MySQL.');
    }
    if (!gggTecnicaValidBaseUrl($baseUrl)) {
        throw new RuntimeException('La URL debe ser HTTPS; HTTP solo se permite para localhost.');
    }
    if (($smtpHost !== '' && !gggTecnicaValidHost($smtpHost))
        || $smtpPort === false || $smtpPort < 1 || $smtpPort > 65535
        || !in_array($smtpSecure, ['tls', 'ssl'], true)
        || strlen($smtpUsername) > 254 || preg_match('/[\r\n\x00]/', $smtpUsername)
        || strlen($smtpPassword) > 4096 || preg_match('/[\r\n\x00]/', $smtpPassword)
        || mb_strlen($smtpFromName, 'UTF-8') > 100
        || preg_match('/[\r\n\x00]/', $smtpFromName)) {
        throw new RuntimeException('Verifica los datos del servidor de correo.');
    }
    if ($smtpHost !== '' && ($smtpUsername === '' || $smtpPassword === '')) {
        throw new RuntimeException('Para activar SMTP completa el usuario y la contraseña.');
    }
    if ($smtpFromEmail === '' && $smtpUsername !== '') {
        $smtpFromEmail = $smtpUsername;
    }
    if ($smtpFromEmail !== '' && (strlen($smtpFromEmail) > 254 || !filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL))) {
        throw new RuntimeException('El correo remitente de SMTP no es válido.');
    }

    $updatedConfig = [
        'APP_BASE_URL' => $baseUrl,
        'DB_HOST' => $dbHost,
        'DB_PORT' => (string) $dbPort,
        'DB_NAME' => $dbName,
        'DB_USER' => $dbUser,
        'DB_PASSWORD' => $dbPassword,
        'SMTP_HOST' => $smtpHost,
        'SMTP_PORT' => (string) $smtpPort,
        'SMTP_SECURE' => $smtpSecure,
        'SMTP_USERNAME' => $smtpUsername,
        'SMTP_PASSWORD' => $smtpPassword,
        'SMTP_FROM_EMAIL' => $smtpFromEmail,
        'SMTP_FROM_NAME' => $smtpFromName,
    ];

    try {
        $targetPdo = new PDO(
            'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4',
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $targetTables = $targetPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $requiredTables = [
            'clientes',
            'operator',
            'planes',
            'pagos',
            'app_settings',
            'audit_logs',
        ];
        if (array_diff($requiredTables, $targetTables)) {
            throw new RuntimeException('La base seleccionada no contiene el esquema completo de esta aplicación.');
        }
        $targetOperatorQuery = $targetPdo->prepare(
            'SELECT username, operator_role, activo, operator_status FROM operator WHERE id = ? LIMIT 1'
        );
        $targetOperatorQuery->execute([$_SESSION['operador_id']]);
        $targetOperator = $targetOperatorQuery->fetch(PDO::FETCH_ASSOC);
        if (!$targetOperator
            || $targetOperator['username'] !== $currentOperator['username']
            || $targetOperator['operator_role'] !== $currentOperator['operator_role']
            || (int) $targetOperator['activo'] !== 1
            || $targetOperator['operator_status'] !== 'Activo') {
            throw new RuntimeException('La base seleccionada no contiene tu misma cuenta activa; no se guardó el cambio.');
        }
    } catch (PDOException $e) {
        error_log('Could not validate technical database settings: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'No se pudo validar la conexión o el esquema de la base nueva. Verifica las credenciales y que la base esté disponible.']);
        exit;
    }

    $changedKeys = [];
    foreach ($updatedConfig as $key => $value) {
        if ((string) $currentConfig[$key] !== (string) $value) {
            $changedKeys[] = $key;
        }
    }
    if (!$changedKeys) {
        echo json_encode(['success' => true, 'message' => 'No hay cambios que guardar.']);
        exit;
    }

    $envValuesToWrite = ['APP_ENV' => $currentConfig['APP_ENV']] + $updatedConfig;
    gggTecnicaWriteEnv($envPath, $envValuesToWrite);
    foreach ($updatedConfig as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    $auditFailed = false;
    try {
        registrarAuditoria($pdo, 'configuracion_tecnica_actualizada', 'configuracion', 'entorno', [
            'variables_modificadas' => ['anterior' => null, 'nuevo' => implode(', ', $changedKeys)]
        ]);
    } catch (Throwable $e) {
        error_log('Technical configuration was saved but could not be audited: ' . $e->getMessage());
        $auditFailed = true;
    }

    $restartRequired = true;
    $message = $auditFailed
        ? 'Configuración guardada, pero no se pudo registrar el cambio en la auditoría.'
        : 'Configuración guardada.';
    if ($restartRequired) {
        $message .= ' Reinicia PHP/Apache para que todos los procesos apliquen la configuración.';
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'restart_required' => $restartRequired,
    ]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Could not update technical configuration: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar la configuración técnica.']);
}
