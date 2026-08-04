<?php
// Archivo: registrar_cliente.php (Actualizado con POO)
header('Content-Type: application/json');

try {
    require 'conexion.php'; // Tu conexión PDO que ya funciona perfecto[cite: 5]
    require 'Cliente.php';  // Llamamos al camerino de nuestra clase

    // Captura de datos[cite: 2]
    $ci = $_POST['ci'] ?? '';
    $nombres = $_POST['nombres'] ?? '';
    $apellidos = $_POST['apellidos'] ?? '';
    $tlf = $_POST['tlf'] ?? '';
    $email = $_POST['email'] ?? '';
    $direccion = $_POST['direccion'] ?? '';

    if (empty($ci) || empty($nombres)) {
        throw new Exception("Faltan datos obligatorios."); // Mantenemos tu lógica de error[cite: 2]
    }

    // ¡Aquí ocurre la magia de la POO! Instanciamos el objeto.
    $nuevoCliente = new Cliente($ci, $nombres, $apellidos, $tlf, $email, $direccion);

    // Le pedimos al objeto que se guarde a sí mismo
    $nuevoCliente->guardar($pdo);

    echo json_encode(['success' => true]); // Retornamos éxito como antes[cite: 2]

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); // Retornamos error[cite: 2]
}
?>