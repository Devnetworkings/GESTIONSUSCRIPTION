<?php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
require_once __DIR__ . '/../includes/conexion.php';
ob_clean();
require_once __DIR__ . '/../includes/auth_guard.php';
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

function xlsxEscape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xlsxColumnName(int $column): string
{
    $name = '';
    while ($column > 0) {
        $remainder = ($column - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $column = intdiv($column - 1, 26);
    }
    return $name;
}

function xlsxCellXml($cell, string $reference, bool $header = false): string
{
    if (!is_array($cell)) {
        $cell = ['value' => $cell, 'type' => 'String'];
    }
    $value = $cell['value'] ?? '';
    $type = $cell['type'] ?? 'String';
    $style = $header ? 1 : (int) ($cell['style'] ?? ($type === 'String' ? 6 : 0));
    $styleAttribute = $style === 0 ? '' : ' s="' . $style . '"';

    if ($type === 'Number') {
        if ($value === '') {
            return '<c r="' . $reference . '"' . $styleAttribute . '/>';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('Invalid numeric value in Excel report.');
        }
        return '<c r="' . $reference . '"' . $styleAttribute . '><v>' . xlsxEscape((string) $value) . '</v></c>';
    }

    $escaped = xlsxEscape((string) $value);
    if ($type === 'DateTime') {
        $date = new DateTimeImmutable((string) $value);
        $excelEpoch = new DateTimeImmutable('1899-12-30 00:00:00');
        $serial = ((float) $date->format('U') - (float) $excelEpoch->format('U')) / 86400;
        return '<c r="' . $reference . '"' . $styleAttribute . '><v>' . sprintf('%.10F', $serial) . '</v></c>';
    }
    return '<c r="' . $reference . '"' . $styleAttribute . ' t="inlineStr"><is><t xml:space="preserve">'
        . $escaped . '</t></is></c>';
}

function xlsxAdjustedWidths(array $rows, array $minimumWidths): array
{
    $widths = $minimumWidths;
    foreach ($rows as $cells) {
        foreach ($cells as $columnIndex => $cell) {
            if (is_array($cell) && array_key_exists('fitWidth', $cell) && !$cell['fitWidth']) {
                continue;
            }
            $value = is_array($cell) ? ($cell['value'] ?? '') : $cell;
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $value = (string) $value;
            $characterCount = preg_match_all('/./us', $value);
            if ($characterCount === false) {
                $characterCount = strlen($value);
            }
            $widths[$columnIndex] = min(
                60,
                max((float) ($widths[$columnIndex] ?? 10), $characterCount + 2)
            );
        }
    }
    return $widths;
}

function xlsxWorksheetXml(array $rows, array $widths, array $options = []): string
{
    $widths = xlsxAdjustedWidths($rows, $widths);
    $lastColumn = xlsxColumnName(max(1, count($widths)));
    $lastRow = max(1, count($rows));
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
        . '<cols>';
    foreach ($widths as $index => $width) {
        $column = $index + 1;
        $xml .= '<col min="' . $column . '" max="' . $column . '" width="' . (float) $width
            . '" bestFit="1" customWidth="1"/>';
    }
    $xml .= '</cols><sheetData>';
    foreach ($rows as $rowIndex => $cells) {
        $rowNumber = $rowIndex + 1;
        $xml .= '<row r="' . $rowNumber . '"' . ($rowNumber === 1 ? ' ht="32" customHeight="1"' : '') . '>';
        foreach ($cells as $columnIndex => $cell) {
            $xml .= xlsxCellXml($cell, xlsxColumnName($columnIndex + 1) . $rowNumber, $rowNumber === 1);
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData>';
    if (!empty($options['autoFilter'])) {
        $xml .= '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>';
    }
    $xml .= '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>';
    if (!empty($options['drawing'])) {
        $xml .= '<drawing r:id="rId1"/>';
    }
    return $xml . '</worksheet>';
}

function xlsxChartTitle(string $title): string
{
    return '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr lang="es-VE"/></a:pPr>'
        . '<a:r><a:rPr lang="es-VE" sz="1400" b="1"><a:solidFill><a:srgbClr val="17365D"/></a:solidFill>'
        . '<a:latin typeface="Aptos"/></a:rPr><a:t>' . xlsxEscape($title) . '</a:t></a:r>'
        . '<a:endParaRPr lang="es-VE"/></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>';
}

function xlsxBarChartXml(
    string $title,
    string $categoryRange,
    string $valueRange,
    string $color,
    int $axisId,
    int $labelSkip
): string
{
    $formulaCategories = xlsxEscape("'Balance'!" . $categoryRange);
    $formulaValues = xlsxEscape("'Balance'!" . $valueRange);
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"'
        . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<c:lang val="es-VE"/><c:chart>' . xlsxChartTitle($title)
        . '<c:plotArea><c:layout/><c:barChart><c:barDir val="col"/><c:grouping val="clustered"/>'
        . '<c:varyColors val="0"/><c:ser><c:idx val="0"/><c:order val="0"/>'
        . '<c:spPr><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill>'
        . '<a:ln><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill></a:ln></c:spPr>'
        . '<c:cat><c:strRef><c:f>' . $formulaCategories . '</c:f></c:strRef></c:cat>'
        . '<c:val><c:numRef><c:f>' . $formulaValues . '</c:f></c:numRef></c:val>'
        . '</c:ser><c:gapWidth val="60"/>'
        . '<c:axId val="' . $axisId . '"/><c:axId val="' . ($axisId + 1) . '"/></c:barChart>'
        . '<c:catAx><c:axId val="' . $axisId . '"/><c:scaling><c:orientation val="minMax"/></c:scaling>'
        . '<c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="' . ($axisId + 1)
        . '"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/>'
        . '<c:tickLblSkip val="' . $labelSkip . '"/><c:tickMarkSkip val="' . $labelSkip . '"/></c:catAx>'
        . '<c:valAx><c:axId val="' . ($axisId + 1) . '"/><c:scaling><c:orientation val="minMax"/></c:scaling>'
        . '<c:delete val="0"/><c:axPos val="l"/><c:majorGridlines/><c:numFmt formatCode="#,##0.00" sourceLinked="0"/>'
        . '<c:tickLblPos val="nextTo"/><c:crossAx val="' . $axisId . '"/><c:crosses val="autoZero"/>'
        . '<c:crossBetween val="between"/></c:valAx></c:plotArea><c:plotVisOnly val="1"/>'
        . '<c:dispBlanksAs val="gap"/></c:chart>'
        . '<c:printSettings><c:headerFooter/><c:pageMargins l="0.25" r="0.25" t="0.5" b="0.5"'
        . ' header="0.2" footer="0.2"/><c:pageSetup/></c:printSettings></c:chartSpace>';
}

function xlsxPieChartXml(string $categoryRange, string $valueRange): string
{
    $formulaCategories = xlsxEscape("'Balance'!" . $categoryRange);
    $formulaValues = xlsxEscape("'Balance'!" . $valueRange);
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"'
        . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<c:lang val="es-VE"/><c:chart>' . xlsxChartTitle('Cantidad de pagos por moneda')
        . '<c:plotArea><c:layout/><c:pieChart><c:varyColors val="1"/><c:ser><c:idx val="0"/><c:order val="0"/>'
        . '<c:cat><c:strRef><c:f>' . $formulaCategories . '</c:f></c:strRef></c:cat>'
        . '<c:val><c:numRef><c:f>' . $formulaValues . '</c:f></c:numRef></c:val></c:ser>'
        . '<c:dLbls><c:showLegendKey val="0"/><c:showVal val="1"/><c:showPercent val="1"/>'
        . '<c:showCatName val="1"/></c:dLbls><c:firstSliceAng val="0"/></c:pieChart></c:plotArea>'
        . '<c:legend><c:legendPos val="b"/><c:layout/></c:legend><c:plotVisOnly val="1"/>'
        . '<c:dispBlanksAs val="gap"/></c:chart><c:printSettings><c:headerFooter/>'
        . '<c:pageMargins l="0.25" r="0.25" t="0.5" b="0.5" header="0.2" footer="0.2"/>'
        . '<c:pageSetup/></c:printSettings></c:chartSpace>';
}

function xlsxDrawingXml(array $charts): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
        . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
        . ' xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">';
    foreach ($charts as $index => $chart) {
        $column = $chart['column'];
        $row = $chart['row'];
        $lastColumn = $chart['lastColumn'] ?? ($column + 8);
        $xml .= '<xdr:twoCellAnchor><xdr:from><xdr:col>' . $column . '</xdr:col><xdr:colOff>0</xdr:colOff>'
            . '<xdr:row>' . $row . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to>'
            . '<xdr:col>' . $lastColumn . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . ($row + 16)
            . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to><xdr:graphicFrame macro="">'
            . '<xdr:nvGraphicFramePr><xdr:cNvPr id="' . ($index + 2) . '" name="Chart ' . ($index + 1)
            . '"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr><xdr:xfrm><a:off x="0" y="0"/>'
            . '<a:ext cx="0" cy="0"/></xdr:xfrm><a:graphic><a:graphicData '
            . 'uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart r:id="rId' . ($index + 1)
            . '"/></a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>';
    }
    return $xml . '</xdr:wsDr>';
}

function xlsxZip(array $files): string
{
    $body = '';
    $directory = '';
    $offset = 0;
    foreach ($files as $name => $contents) {
        $name = str_replace('\\', '/', $name);
        $compressed = gzdeflate($contents, 6);
        if ($compressed === false) {
            throw new RuntimeException('Could not compress the Excel workbook.');
        }
        $crc = crc32($contents);
        $nameLength = strlen($name);
        $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 33, $crc, strlen($compressed), strlen($contents), $nameLength, 0);
        $body .= $localHeader . $name . $compressed;
        $directory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50, 20, 20, 0, 8, 0, 33, $crc, strlen($compressed), strlen($contents),
            $nameLength, 0, 0, 0, 0, 0, $offset
        ) . $name;
        $offset = strlen($body);
    }
    $entryCount = count($files);
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($directory), strlen($body), 0);
    return $body . $directory . $end;
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
        SELECT p.id AS pago_id, p.fecha, c.ci, c.tlf AS telefono_cliente, c.email AS email_cliente,
               TRIM(CONCAT(COALESCE(c.nombres, ''), ' ', COALESCE(c.apellidos, ''))) AS cliente,
               p.reference, COALESCE(p.plan_nombre_snapshot, pl.nombre) AS plan,
               COALESCE(p.plan_duracion_meses_snapshot, pl.duracion_meses) AS duracion_meses,
               p.monto, p.coin, p.monto_usd, p.tasa_bcv, p.fecha_valor_bcv,
               p.metodo_pago, o.username AS operador_usuario,
               TRIM(CONCAT(COALESCE(o.nombres, ''), ' ', COALESCE(o.apellidos, ''))) AS operador_nombre
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

    $stmt = $pdo->prepare("
        SELECT p.coin, COUNT(*) AS cantidad, SUM(p.monto) AS total
        FROM pagos p
        WHERE p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY p.coin
        ORDER BY p.coin
    ");
    $stmt->execute([$desde, $hasta]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filasResumen = [
        ['Moneda', 'Método de pago', 'Operador', 'Cantidad de pagos', 'Ingresos totales']
    ];
    foreach ($totales as $total) {
        $filasResumen[] = [
            $total['coin'],
            $total['metodo_pago'],
            $total['operador'] ?? 'Operador desconocido',
            ['value' => $total['cantidad'], 'type' => 'Number', 'style' => 3],
            ['value' => $total['total'], 'type' => 'Number', 'style' => 2]
        ];
    }

    $filasPagos = [
        ['ID de pago', 'Fecha y hora', 'Cédula', 'Cliente', 'Teléfono', 'Correo', 'Referencia', 'Plan', 'Duración (meses)', 'Monto cobrado', 'Moneda', 'Precio del plan (USD)', 'Tasa BCV (Bs/USD)', 'Fecha valor BCV', 'Método de pago', 'Usuario operador', 'Nombre del operador']
    ];
    foreach ($pagos as $pago) {
        $filasPagos[] = [
            $pago['pago_id'],
            ['value' => $pago['fecha'], 'type' => 'DateTime', 'style' => 4],
            $pago['ci'] ?? '',
            $pago['cliente'],
            $pago['telefono_cliente'] ?? '',
            $pago['email_cliente'] ?? '',
            $pago['reference'],
            $pago['plan'] ?? '',
            ['value' => $pago['duracion_meses'] ?? '', 'type' => $pago['duracion_meses'] === null ? 'String' : 'Number', 'style' => 3],
            ['value' => $pago['monto'], 'type' => 'Number', 'style' => 2],
            $pago['coin'],
            ['value' => $pago['monto_usd'] ?? '', 'type' => $pago['monto_usd'] === null ? 'String' : 'Number', 'style' => 2],
            ['value' => $pago['tasa_bcv'] ?? '', 'type' => $pago['tasa_bcv'] === null ? 'String' : 'Number', 'style' => 5],
            $pago['fecha_valor_bcv'] ?? '',
            $pago['metodo_pago'],
            $pago['operador_usuario'] ?? 'Operador desconocido',
            $pago['operador_nombre'] ?? ''
        ];
    }

    $filasDiarias = [
        ['Fecha', 'Moneda', 'Cantidad de pagos', 'Ingresos totales']
    ];
    foreach ($totalesDiarios as $totalDiario) {
        $filasDiarias[] = [
            $totalDiario['fecha'],
            $totalDiario['coin'],
            ['value' => $totalDiario['cantidad'], 'type' => 'Number', 'style' => 3],
            ['value' => $totalDiario['total'], 'type' => 'Number', 'style' => 2]
        ];
    }

    $filasBalance = [
        ['Balance de ingresos brutos'],
        ['Período', $desde . ' a ' . $hasta],
        [[
            'value' => 'Los importes de monedas diferentes se presentan por separado; no se suman ni se descuentan gastos.',
            'fitWidth' => false
        ]],
        [],
        [
            ['value' => 'Moneda', 'style' => 1],
            ['value' => 'Cantidad de pagos', 'style' => 1],
            ['value' => 'Total cobrado', 'style' => 1],
            ['value' => 'Promedio por pago', 'style' => 1]
        ]
    ];
    $dailyAmounts = [];
    foreach ($balances as $balance) {
        $filasBalance[] = [
            $balance['coin'],
            ['value' => $balance['cantidad'], 'type' => 'Number', 'style' => 3],
            ['value' => $balance['total'], 'type' => 'Number', 'style' => 2],
            ['value' => (float) $balance['total'] / max(1, (int) $balance['cantidad']), 'type' => 'Number', 'style' => 2]
        ];
        $dailyAmounts[$balance['coin']] = 0.0;
    }

    foreach ($totalesDiarios as $totalDiario) {
        if (!isset($dailyAmounts[$totalDiario['coin']])) {
            $dailyAmounts[$totalDiario['coin']] = 0.0;
        }
        $dailyAmounts[$totalDiario['coin']] += (float) $totalDiario['total'];
    }

    $chartCoins = [];
    foreach (['VES', 'USD'] as $coin) {
        if (($dailyAmounts[$coin] ?? 0) > 0) {
            $chartCoins[] = $coin;
        }
    }
    if (!$chartCoins) {
        foreach ($dailyAmounts as $coin => $total) {
            if ($total > 0) {
                $chartCoins[] = $coin;
            }
        }
    }

    $filasBalance[] = [];
    $filasBalance[] = ['Ingresos diarios por moneda'];
    $filasBalance[] = [];
    $chartDataStartRow = 70;
    $filasBalance = array_pad($filasBalance, $chartDataStartRow - 1, []);
    $filasBalance[] = [
        'Fecha VES', ['value' => 'Ingresos VES', 'type' => 'String', 'style' => 1], '',
        'Fecha USD', ['value' => 'Ingresos USD', 'type' => 'String', 'style' => 1], '',
        '', '', '', ['value' => 'Moneda', 'type' => 'String', 'style' => 1],
        ['value' => 'Pagos', 'type' => 'String', 'style' => 1]
    ];

    $dailyByDate = [];
    foreach ($totalesDiarios as $totalDiario) {
        $dailyByDate[$totalDiario['fecha']][$totalDiario['coin']] = (float) $totalDiario['total'];
    }
    $dailyPeriod = new DatePeriod(
        new DateTimeImmutable($desde),
        new DateInterval('P1D'),
        (new DateTimeImmutable($hasta))->modify('+1 day')
    );
    $dailyChartRows = 0;
    foreach ($dailyPeriod as $day) {
        $date = $day->format('Y-m-d');
        $filasBalance[] = [
            $date, ['value' => $dailyByDate[$date]['VES'] ?? 0, 'type' => 'Number', 'style' => 2], '',
            $date, ['value' => $dailyByDate[$date]['USD'] ?? 0, 'type' => 'Number', 'style' => 2], '',
            '', '', '', '', ''
        ];
        $dailyChartRows++;
    }

    $charts = [];
    $chartFiles = [];
    $chartRelationships = [];
    $nextChartRow = 11;
    $labelSkip = max(1, (int) ceil($dailyChartRows / 8));
    if ($dailyChartRows > 0 && in_array('VES', $chartCoins, true)) {
        $chartFiles['xl/charts/chart' . (count($charts) + 1) . '.xml'] = xlsxBarChartXml(
            'Ingresos diarios en VES',
            '$A$' . $chartDataStartRow . ':$A$' . ($chartDataStartRow + $dailyChartRows),
            '$B$' . $chartDataStartRow . ':$B$' . ($chartDataStartRow + $dailyChartRows),
            '4472C4',
            100000,
            $labelSkip
        );
        $charts[] = ['column' => 0, 'lastColumn' => 10, 'row' => $nextChartRow];
        $nextChartRow += 18;
    }
    if ($dailyChartRows > 0 && in_array('USD', $chartCoins, true)) {
        $chartFiles['xl/charts/chart' . (count($charts) + 1) . '.xml'] = xlsxBarChartXml(
            'Ingresos diarios en USD',
            '$D$' . $chartDataStartRow . ':$D$' . ($chartDataStartRow + $dailyChartRows),
            '$E$' . $chartDataStartRow . ':$E$' . ($chartDataStartRow + $dailyChartRows),
            '70AD47',
            200000,
            $labelSkip
        );
        $charts[] = ['column' => 0, 'lastColumn' => 10, 'row' => $nextChartRow];
        $nextChartRow += 18;
    }
    if (count($balances) > 1) {
        $firstBalanceRow = 6;
        $lastBalanceRow = 5 + count($balances);
        $chartFiles['xl/charts/chart' . (count($charts) + 1) . '.xml'] = xlsxPieChartXml(
            '$A$' . $firstBalanceRow . ':$A$' . $lastBalanceRow,
            '$B$' . $firstBalanceRow . ':$B$' . $lastBalanceRow
        );
        $charts[] = ['column' => 0, 'lastColumn' => 8, 'row' => $nextChartRow];
    }

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="4"><numFmt numFmtId="164" formatCode="#,##0.00"/>'
        . '<numFmt numFmtId="165" formatCode="#,##0"/><numFmt numFmtId="166" formatCode="dd/mm/yyyy hh:mm:ss"/>'
        . '<numFmt numFmtId="167" formatCode="0.00000000"/></numFmts>'
        . '<fonts count="2"><font><sz val="10"/><name val="Aptos"/></font>'
        . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Aptos"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF17365D"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right>'
        . '<top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
        . '<alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
        . '<xf numFmtId="167" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
        . '<alignment vertical="top" wrapText="1"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    $workbookSheets = [
        ['Pagos', $filasPagos, [38, 21, 13, 30, 17, 31, 24, 19, 16, 18, 11, 23, 23, 18, 20, 22, 30], ['autoFilter' => true]],
        ['Resumen', $filasResumen, [12, 22, 25, 19, 20], ['autoFilter' => true]],
        ['Totales diarios', $filasDiarias, [16, 13, 20, 22], ['autoFilter' => true]],
        ['Balance', $filasBalance, [16, 20, 3, 16, 20, 3, 3, 3, 3, 14, 19], ['drawing' => count($charts) > 0]]
    ];

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . implode('', array_map(
                fn($index) => '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                range(1, count($workbookSheets))
            ))
            . ($charts ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
                . implode('', array_map(
                    fn($index) => '<Override PartName="/xl/charts/chart' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>',
                    range(1, count($charts))
                )) : '')
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>'
            . implode('', array_map(
                fn($index, $sheet) => '<sheet name="' . xlsxEscape($sheet[0]) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>',
                array_keys($workbookSheets),
                $workbookSheets
            ))
            . '</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', array_map(
                fn($index) => '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>',
                range(1, count($workbookSheets))
            ))
            . '<Relationship Id="rId' . (count($workbookSheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $styles
    ];

    foreach ($workbookSheets as $index => $sheet) {
        $sheetId = $index + 1;
        $files['xl/worksheets/sheet' . $sheetId . '.xml'] = xlsxWorksheetXml($sheet[1], $sheet[2], $sheet[3]);
    }

    if ($charts) {
        $files['xl/worksheets/_rels/sheet4.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
        $files['xl/drawings/drawing1.xml'] = xlsxDrawingXml($charts);
        $files['xl/drawings/_rels/drawing1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', array_map(
                fn($index) => '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart' . $index . '.xml"/>',
                range(1, count($charts))
            ))
            . '</Relationships>';
    }
    $files += $chartFiles;

    $workbook = xlsxZip($files);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="ingresos_' . $desde . '_' . $hasta . '.xlsx"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $workbook;
} catch (PDOException $e) {
    ob_clean();
    error_log('No se pudo generar el reporte de ingresos: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el reporte.']);
} catch (Throwable $e) {
    ob_clean();
    error_log('No se pudo preparar el archivo Excel: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No se pudo preparar el archivo Excel.']);
}
