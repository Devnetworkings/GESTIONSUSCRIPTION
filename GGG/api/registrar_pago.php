<?php
// Archivo: registrar_pago.php
ob_start();
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/pago.php';
require_once __DIR__ . '/../includes/auth_guard.php';
requireActiveOperator($pdo);
require_once __DIR__ . '/../includes/bcv_rate.php';

// Captura de datos enviados por Fetch
$cedula_num = $_POST['cedula_identidad'] ?? '';
$metodo_pago = $_POST['metodo_pago'] ?? '';
$reference = $_POST['reference'] ?? '';
$id_plan = (int) ($_POST['id_plan'] ?? 0);
$codigo_telefono = $_POST['codigo_operadora'] ?? '';
$telefono_local = $_POST['telefono'] ?? '';

$id_operador = $_SESSION['operador_id'];
$coin = ($metodo_pago === 'divisa') ? 'USD' : 'VES';

if (empty($cedula_num) || !in_array($metodo_pago, ['pagomovil', 'divisa', 'transferencia'], true)
    || !preg_match('/^04\d{2}$/', $codigo_telefono) || !preg_match('/^\d{7}$/', $telefono_local)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Faltan campos críticos para procesar el pago.']);
    exit;
}

try {
    require_once __DIR__ . '/../includes/validaciones.php';
    $tipo_documento = strtoupper(trim($_POST['tipo_documento'] ?? ''));
    $ci_cliente = normalizarCedula($cedula_num, $tipo_documento);
    if ($ci_cliente === false) {
        http_response_code(422);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'La cédula debe contener entre 6 y 9 dígitos.']);
        exit;
    }
    $ci_sin_prefijo = preg_replace('/^[VEJGP]-/', '', $ci_cliente);
    $stmt_cliente = $pdo->prepare('SELECT id FROM clientes WHERE ci = ? OR ci = ? LIMIT 1');
    $stmt_cliente->execute([$ci_cliente, $ci_sin_prefijo]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El cliente no está registrado en el sistema.']);
        exit;
    }

    $id_cliente = $cliente['id']; // ¡Esto ahora agarra el UUID dinámico y hermoso!

    $stmt_plan = $pdo->prepare('SELECT id, nombre, precio FROM planes WHERE id = ? AND activo = 1 AND precio > 0 LIMIT 1');
    $stmt_plan->execute([$id_plan]);
    $plan = $stmt_plan->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        http_response_code(422);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Configura y selecciona un plan activo antes de registrar el pago.']);
        exit;
    }
    $tasaBcv = null;
    $montoUsd = (float) $plan['precio'];
    $montoCobrado = $montoUsd;
    $fechaValorBcv = null;
    if ($coin === 'VES') {
        $tasaBcv = obtenerTasaBcv($pdo);
        if (!$tasaBcv['available']) {
            http_response_code(422);
            ob_clean();
            echo json_encode(['success' => false, 'message' => $tasaBcv['message']]);
            exit;
        }
        $montoCobrado = round($montoUsd * (float) $tasaBcv['rate'], 2);
        $fechaValorBcv = $tasaBcv['value_date'];
    }

    $reference = trim($reference);
    if ($reference === '' && $metodo_pago === 'divisa') {
        $reference = 'EFECTIVO-' . strtoupper(bin2hex(random_bytes(8)));
    }
    if ($reference === '' || strlen($reference) > 60 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{2,59}$/', $reference)) {
        http_response_code(422);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'La referencia debe tener entre 3 y 60 caracteres alfanuméricos (se permiten . _ / -).']);
        exit;
    }

    $stmt_referencia = $pdo->prepare('SELECT 1 FROM pagos WHERE reference = ? LIMIT 1');
    $stmt_referencia->execute([$reference]);
    if ($stmt_referencia->fetchColumn()) {
        http_response_code(409);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Esa referencia ya fue registrada. Verifica el comprobante.']);
        exit;
    }

    $nuevoPago = new Pago(
        $id_cliente,
        $reference,
        $montoCobrado,
        $coin,
        $id_operador,
        $metodo_pago,
        (int) $plan['id'],
        1,
        $montoUsd,
        $tasaBcv['rate'] ?? null,
        $fechaValorBcv,
        $plan['nombre']
    );
    $nuevoPago->guardar($pdo);

    ob_clean();
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    ob_clean();
    if ($e->getCode() == 23000) {
        http_response_code(409);
        error_log('Duplicate payment reference rejected by the database unique index.');
        echo json_encode(['success' => false, 'message' => '¡Corte! Esa referencia o número de serie ya fue registrada.']);
    } else {
        http_response_code(500);
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'No se pudo procesar el pago.']);
    }
}
?>