<?php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';

// ¿Hay un operador en el set?
if (!isset($_SESSION['operador_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => '¡Seguridad! Operador no autorizado.']);
    exit;
}

// Captura de datos enviados por Fetch
$cedula_num = $_POST['cedula_identidad'] ?? '';
$monto = $_POST['monto'] ?? 0;
$metodo_pago = $_POST['metodo_pago'] ?? '';
$reference = $_POST['reference'] ?? ''; // Aquí cae el número de serie o la referencia bancaria

$id_operador = $_SESSION['operador_id'];
$coin = ($metodo_pago === 'divisa') ? 'USD' : 'VES';

if (empty($cedula_num) || empty($monto) || empty($reference) || empty($metodo_pago)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Faltan campos críticos para procesar el pago.']);
    exit;
}

try {
    // 🔍 BUSQUEDA: Encontrar al cliente por su número de cédula
    $stmt_cliente = $pdo->prepare("SELECT id FROM clientes WHERE ci = ? LIMIT 1");
    $stmt_cliente->execute([$cedula_num]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El cliente no está registrado en el sistema.']);
        exit;
    }

    $id_cliente = $cliente['id'];

    // 💰 INSERCIÓN: Guardamos la transacción en la tabla pagos
    $stmt_pago = $pdo->prepare("INSERT INTO pagos (id_cliente, reference, monto, coin, id_operador) VALUES (?, ?, ?, ?, ?)");
    $stmt_pago->execute([$id_cliente, $reference, $monto, $coin, $id_operador]);

    ob_clean();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Si meten un número de referencia o serie que ya existe
    if ($e->getCode() == 23000) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => '¡Corte! Esa referencia o número de serie ya fue registrada.']);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error crítico en la base de datos: ' . $e->getMessage()]);
    }
}
?>