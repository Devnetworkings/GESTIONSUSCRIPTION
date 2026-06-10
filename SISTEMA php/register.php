<?php
// 🎬 Encendemos la aspiradora para atrapar espacios fantasma
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require 'conexion.php';
header('Content-Type: application/json');
$tipo_doc = $_POST['tipo_doc'] ?? 'V';
$ci = $_POST['ci'] ?? '';
$ci = $tipo_doc . '-' . $ci_numero;
$nombres = $_POST['nombres'] ?? '';
$apellidos = $_POST['apellidos'] ?? '';
$contacto = $_POST['contacto'] ?? null;
$direccion = $_POST['direccion'] ?? null;
$tlf = $_POST['tlf'] ?? '';
$username = $_POST['username'] ?? '';
$pw = $_POST['pw'] ?? '';

if (empty($ci) || empty($nombres) || empty($apellidos) || empty($tlf) || empty($username) || empty($pw)) {
    ob_clean(); // 🧹 Limpiamos el escenario antes de enviar
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios.']);
    exit;
}

$password_encriptada = password_hash($pw, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO operator (ci, nombres, apellidos, contacto, direccion, PW, TLF, username) 
                           VALUES (:ci, :nombres, :apellidos, :contacto, :direccion, :pw, :tlf, :username)");

    $stmt->execute([
        'ci' => $ci,
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'contacto' => $contacto,
        'direccion' => $direccion,
        'pw' => $password_encriptada,
        'tlf' => $tlf,
        'username' => $username
    ]);

    ob_clean(); // 🧹 Limpiamos el escenario antes de enviar
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    ob_clean(); // 🧹 Limpiamos el escenario antes de enviar
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => '¡Cédula o teléfono ya registrados!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error interno en la bóveda.']);
    }
}