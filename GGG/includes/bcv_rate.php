<?php

const BCV_TIMEZONE = 'America/Caracas';
const BCV_REFRESH_INTERVAL = 900;

function fechaHoraBcv(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(BCV_TIMEZONE));
}

function leerConfiguracionBcv(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'bcv_%'");
    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[substr($row['setting_key'], 4)] = $row['setting_value'];
    }
    return $settings;
}

function guardarConfiguracionBcv(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("
        INSERT INTO app_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    foreach ($settings as $key => $value) {
        $stmt->execute(['bcv_' . $key, (string) $value]);
    }
}

function parsearRespuestaBcv(string $html): array
{
    $dom = new DOMDocument();
    $previousSetting = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previousSetting);
    if (!$loaded) {
        throw new RuntimeException('El BCV no devolvió una página HTML válida.');
    }

    $xpath = new DOMXPath($dom);
    $usdValue = null;
    foreach ($xpath->query("//*[normalize-space(.)='USD']") as $currencyNode) {
        for ($ancestor = $currencyNode->parentNode; $ancestor instanceof DOMElement; $ancestor = $ancestor->parentNode) {
            if (!preg_match('/(?:^|\s)recuadrotsmc(?:\s|$)/', $ancestor->getAttribute('class'))) {
                continue;
            }
            $rateNode = $xpath->query('.//strong', $ancestor)->item(0);
            if ($rateNode) {
                $usdValue = trim($rateNode->textContent);
                break 2;
            }
        }
    }
    if ($usdValue === null) {
        throw new RuntimeException('No se encontró la tasa USD en la página oficial del BCV.');
    }

    $numericValue = preg_replace('/\s+/', '', $usdValue);
    if (str_contains($numericValue, ',')) {
        $numericValue = str_replace('.', '', $numericValue);
        $numericValue = str_replace(',', '.', $numericValue);
    }
    if (!is_numeric($numericValue) || (float) $numericValue <= 0 || (float) $numericValue > 9999999999.99999999) {
        throw new RuntimeException('La tasa USD publicada por el BCV no tiene un formato válido.');
    }

    $body = $xpath->query('//body')->item(0);
    $bodyText = $body ? preg_replace('/\s+/u', ' ', $body->textContent) : '';
    $months = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12
    ];
    if (!preg_match('/Fecha\s+Valor:\s*(?:[[:alpha:]áéíóúÁÉÍÓÚñÑ]+,\s*)?(\d{1,2})\s+([[:alpha:]áéíóúÁÉÍÓÚñÑ]+)\s+(\d{4})/u', $bodyText, $matches)) {
        throw new RuntimeException('No se encontró la Fecha Valor de la tasa USD del BCV.');
    }
    $month = $months[mb_strtolower($matches[2], 'UTF-8')] ?? null;
    if ($month === null || !checkdate($month, (int) $matches[1], (int) $matches[3])) {
        throw new RuntimeException('La Fecha Valor publicada por el BCV no es válida.');
    }

    return [
        'rate' => number_format((float) $numericValue, 8, '.', ''),
        'value_date' => sprintf('%04d-%02d-%02d', (int) $matches[3], $month, (int) $matches[1])
    ];
}

function consultarTasaOficialBcv(): array
{
    if (!extension_loaded('curl') || !class_exists('DOMDocument')) {
        throw new RuntimeException('El servidor requiere las extensiones PHP cURL y DOM para consultar el BCV.');
    }

    $curl = curl_init('https://www.bcv.org.ve/');
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'GGG-Gestion/1.0 (BCV reference rate)',
        CURLOPT_HTTPHEADER => ['Accept: text/html']
    ];
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        $curlOptions[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    curl_setopt_array($curl, $curlOptions);
    $html = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($html === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('No se pudo consultar la página oficial del BCV' . ($error !== '' ? ': ' . $error : '.'));
    }
    if (strlen($html) > 3000000) {
        throw new RuntimeException('La respuesta del BCV supera el tamaño esperado.');
    }

    return parsearRespuestaBcv($html);
}

function obtenerTasaBcv(PDO $pdo, bool $forzarConsulta = false): array
{
    $ahora = fechaHoraBcv();
    $settings = leerConfiguracionBcv($pdo);
    $ultimaConsulta = isset($settings['last_attempt_at'])
        ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $settings['last_attempt_at'], new DateTimeZone(BCV_TIMEZONE))
        : false;
    $consultaReciente = $ultimaConsulta !== false
        && $ahora->getTimestamp() - $ultimaConsulta->getTimestamp() < BCV_REFRESH_INTERVAL;

    if ($forzarConsulta || !$consultaReciente) {
        try {
            $oficial = consultarTasaOficialBcv();
        } catch (RuntimeException $e) {
            error_log('Consulta de tasa BCV fallida: ' . $e->getMessage());
            guardarConfiguracionBcv($pdo, ['last_attempt_at' => $ahora->format('Y-m-d H:i:s')]);
            $settings = leerConfiguracionBcv($pdo);
        }
        if (isset($oficial)) {
            $settings = [
                'rate' => $oficial['rate'],
                'value_date' => $oficial['value_date'],
                'source' => 'bcv',
                'fetched_at' => $ahora->format('Y-m-d H:i:s'),
                'last_attempt_at' => $ahora->format('Y-m-d H:i:s')
            ];
            guardarConfiguracionBcv($pdo, $settings);
        }
    }

    $fuente = $settings['source'] ?? '';
    $fechaConsulta = $settings['fetched_at'] ?? '';
    $tasaVigente = in_array($fuente, ['bcv', 'manual'], true)
        && $fechaConsulta !== ''
        && substr($fechaConsulta, 0, 10) === $ahora->format('Y-m-d');
    if (!$tasaVigente || empty($settings['rate']) || empty($settings['value_date'])) {
        return [
            'available' => false,
            'source' => null,
            'rate' => null,
            'value_date' => null,
            'fetched_at' => $fechaConsulta ?: null,
            'message' => 'No hay una tasa BCV consultada hoy. Un administrador debe ingresar una tasa manual para aceptar pagos en bolívares.'
        ];
    }

    return [
        'available' => true,
        'source' => $fuente,
        'rate' => $settings['rate'],
        'value_date' => $settings['value_date'],
        'fetched_at' => $fechaConsulta ?: null,
        'message' => $fuente === 'manual'
            ? 'Se está usando una tasa manual; se reemplazará cuando el BCV pueda consultarse correctamente.'
            : 'Tasa USD publicada por el BCV.'
    ];
}

function establecerTasaManualBcv(PDO $pdo, string $rate): void
{
    $normalized = str_replace(',', '.', trim($rate));
    if (!is_numeric($normalized) || (float) $normalized <= 0 || (float) $normalized > 9999999999.99999999) {
        throw new InvalidArgumentException('La tasa manual debe ser un número positivo válido.');
    }

    $ahora = fechaHoraBcv();
    $settings = leerConfiguracionBcv($pdo);
    $tasaBcvActual = ($settings['source'] ?? '') === 'bcv'
        && !empty($settings['fetched_at'])
        && substr($settings['fetched_at'], 0, 10) === $ahora->format('Y-m-d');
    if ($tasaBcvActual) {
        throw new RuntimeException('El BCV ya tiene una tasa consultada hoy. La tasa oficial no se puede reemplazar manualmente.');
    }

    guardarConfiguracionBcv($pdo, [
        'rate' => number_format((float) $normalized, 8, '.', ''),
        'value_date' => $ahora->format('Y-m-d'),
        'source' => 'manual',
        'fetched_at' => $ahora->format('Y-m-d H:i:s')
    ]);
}
