<?php
ob_start();
session_start();
require 'conexion.php';
ob_clean();
require 'auth_guard.php';
$currentOperator = requireActiveOperator($pdo);
if ($currentOperator['operator_role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede consultar los reportes financieros.']);
    exit;
}

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$fechaDesde = DateTime::createFromFormat('!Y-m-d', $desde);
$fechaHasta = DateTime::createFromFormat('!Y-m-d', $hasta);
if (!$fechaDesde || $fechaDesde->format('Y-m-d') !== $desde || !$fechaHasta || $fechaHasta->format('Y-m-d') !== $hasta || $desde > $hasta) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Rango de fechas no válido.']);
    exit;
}

function excelXmlCell($value, string $type = 'String'): string
{
    $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<Cell><Data ss:Type="' . $type . '">' . $escaped . '</Data></Cell>';
}

function excelXmlRow(array $values, bool $header = false): string
{
    $cells = '';
    foreach ($values as $value) {
        if (is_array($value)) {
            $cells .= excelXmlCell($value['value'], $value['type']);
        } else {
            $cells .= excelXmlCell($value);
        }
    }
    return '<Row' . ($header ? ' ss:StyleID="Header"' : '') . '>' . $cells . '</Row>';
}

function excelXmlWorksheet(string $name, array $rows): string
{
    return '<Worksheet ss:Name="' . htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"><Table>'
        . implode('', $rows)
        . '</Table></Worksheet>';
}

try {
    $stmt = $pdo->prepare("
        SELECT p.coin, p.metodo_pago, o.username AS operador,
               COUNT(*) AS cantidad, SUM(p.monto) AS total
        FROM pagos p
        LEFT JOIN operator o ON p.id_operador = o.id
        WHERE p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY p.coin, p.metodo_pago, o.username
        ORDER BY p.coin, p.metodo_pago, o.username
    ");
    $stmt->execute([$desde, $hasta]);
    $totales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (($_GET['exportar'] ?? '') !== 'excel') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'desde' => $desde, 'hasta' => $hasta, 'totales' => $totales]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT p.id AS pago_id, p.fecha, c.ci,
               TRIM(CONCAT(COALESCE(c.nombres, ''), ' ', COALESCE(c.apellidos, ''))) AS cliente,
               p.reference, COALESCE(p.plan_nombre_snapshot, pl.nombre) AS plan,
               COALESCE(p.plan_duracion_meses_snapshot, pl.duracion_meses) AS duracion_meses,
               p.monto, p.coin, p.monto_usd, p.tasa_bcv, p.fecha_valor_bcv,
               p.metodo_pago, o.username AS operador
        FROM pagos p
        LEFT JOIN clientes c ON p.id_cliente = c.id
        LEFT JOIN planes pl ON p.id_plan = pl.id
        LEFT JOIN operator o ON p.id_operador = o.id
        WHERE p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY p.fecha, p.id
    ");
    $stmt->execute([$desde, $hasta]);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT DATE(p.fecha) AS fecha, p.coin,
               COUNT(*) AS cantidad, SUM(p.monto) AS total
        FROM pagos p
        WHERE p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(p.fecha), p.coin
        ORDER BY fecha, p.coin
    ");
    $stmt->execute([$desde, $hasta]);
    $totalesDiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filasResumen = [
        excelXmlRow(['Moneda', 'Método de pago', 'Operador', 'Cantidad de pagos', 'Ingresos totales'], true)
    ];
    foreach ($totales as $total) {
        $filasResumen[] = excelXmlRow([
            $total['coin'],
            $total['metodo_pago'],
            $total['operador'] ?? 'Operador desconocido',
            ['value' => $total['cantidad'], 'type' => 'Number'],
            ['value' => $total['total'], 'type' => 'Number']
        ]);
    }

    $filasPagos = [
        excelXmlRow(['ID de pago', 'Fecha', 'Cédula', 'Cliente', 'Referencia', 'Plan', 'Duración (meses)', 'Monto cobrado', 'Moneda', 'Precio del plan (USD)', 'Tasa BCV (Bs/USD)', 'Fecha valor BCV', 'Método de pago', 'Operador'], true)
    ];
    foreach ($pagos as $pago) {
        $filasPagos[] = excelXmlRow([
            $pago['pago_id'],
            $pago['fecha'],
            $pago['ci'] ?? '',
            $pago['cliente'],
            $pago['reference'],
            $pago['plan'] ?? '',
            ['value' => $pago['duracion_meses'] ?? '', 'type' => $pago['duracion_meses'] === null ? 'String' : 'Number'],
            ['value' => $pago['monto'], 'type' => 'Number'],
            $pago['coin'],
            ['value' => $pago['monto_usd'] ?? '', 'type' => $pago['monto_usd'] === null ? 'String' : 'Number'],
            ['value' => $pago['tasa_bcv'] ?? '', 'type' => $pago['tasa_bcv'] === null ? 'String' : 'Number'],
            $pago['fecha_valor_bcv'] ?? '',
            $pago['metodo_pago'],
            $pago['operador'] ?? 'Operador desconocido'
        ]);
    }

    $filasDiarias = [
        excelXmlRow(['Fecha', 'Moneda', 'Cantidad de pagos', 'Ingresos totales'], true)
    ];
    foreach ($totalesDiarios as $totalDiario) {
        $filasDiarias[] = excelXmlRow([
            $totalDiario['fecha'],
            $totalDiario['coin'],
            ['value' => $totalDiario['cantidad'], 'type' => 'Number'],
            ['value' => $totalDiario['total'], 'type' => 'Number']
        ]);
    }

    $workbook = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<?mso-application progid="Excel.Sheet"?>'
        . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
        . '<Styles><Style ss:ID="Header"><Font ss:Bold="1"/></Style></Styles>'
        . excelXmlWorksheet('Resumen', $filasResumen)
        . excelXmlWorksheet('Pagos', $filasPagos)
        . excelXmlWorksheet('Totales diarios', $filasDiarias)
        . '</Workbook>';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ingresos_' . $desde . '_' . $hasta . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $workbook;
} catch (PDOException $e) {
    ob_clean();
    error_log('No se pudo generar el reporte de ingresos: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el reporte.']);
}
