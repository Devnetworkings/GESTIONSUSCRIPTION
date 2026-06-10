<?php
// ¡Cero HTML, solo JSON!
header('Content-Type: application/json');

try {
    require 'conexion.php';

    // Captura de datos
    $ci = $_POST['ci'] ?? '';
    $nombres = $_POST['nombres'] ?? '';
    $apellidos = $_POST['apellidos'] ?? '';
    $tlf = $_POST['tlf'] ?? '';
    $email = $_POST['email'] ?? '';
    $direccion = $_POST['direccion'] ?? '';

    if (empty($ci) || empty($nombres)) {
        throw new Exception("Faltan datos obligatorios.");
    }

    $stmt = $pdo->prepare("INSERT INTO clientes (ci, nombres, apellidos, tlf, email, direccion) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ci, $nombres, $apellidos, $tlf, $email, $direccion]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Si algo falla, enviamos el error como JSON, NUNCA como HTML
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>