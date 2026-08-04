<?php
// Archivo: register.php
ob_start(); // Encendemos la aspiradora para atrapar espacios fantasma[cite: 10]
ini_set('display_errors', 0); //[cite: 10]
error_reporting(0); //[cite: 10]

require 'conexion.php'; //[cite: 10]
require 'Operador.php'; // ¡Llamamos a nuestra nueva estrella!
header('Content-Type: application/json'); //[cite: 10]

// Captura de datos corregida
$tipo_doc = $_POST['tipo_doc'] ?? 'V'; //[cite: 10]
$ci_numero = $_POST['ci'] ?? ''; // ¡Aquí atrapamos el número real!
$ci = $tipo_doc . '-' . $ci_numero;
$nombres = $_POST['nombres'] ?? ''; //[cite: 10]
$apellidos = $_POST['apellidos'] ?? ''; //[cite: 10]
$contacto = $_POST['contacto'] ?? null; //[cite: 10]
$direccion = $_POST['direccion'] ?? null; //[cite: 10]
$tlf = $_POST['tlf'] ?? ''; //[cite: 10]
$username = $_POST['username'] ?? ''; //[cite: 10]
$pw = $_POST['pw'] ?? ''; //[cite: 10]

// Validación
if (empty($ci_numero) || empty($nombres) || empty($apellidos) || empty($tlf) || empty($username) || empty($pw)) {
    ob_clean(); // Limpiamos el escenario antes de enviar[cite: 10]
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios.']); //[cite: 10]
    exit;
}

try {
    // ¡Acción! Instanciamos a nuestro operador y lo guardamos
    $nuevoOperador = new Operador($ci, $nombres, $apellidos, $contacto, $direccion, $tlf, $username, $pw);
    $nuevoOperador->guardar($pdo);

    ob_clean(); // Limpiamos el escenario antes de enviar[cite: 10]
    echo json_encode(['success' => true]); //[cite: 10]

} catch (PDOException $e) {
    ob_clean(); // Limpiamos el escenario antes de enviar[cite: 10]
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => '¡Cédula o teléfono ya registrados!']); //[cite: 10]
    } else {
        // Agregué el mensaje real del error para que te sea más fácil depurar en el futuro
        echo json_encode(['success' => false, 'message' => 'Error interno en la bóveda: ' . $e->getMessage()]);
    }
}
?>