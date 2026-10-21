<?php

require_once __DIR__ . '/entorno.php';

$host = 'sql106.infinityfree.com';
$dbname = 'if0_43209397_ggg';
$username = 'if0_43209397';
$password = 'apSNvPgsJkLKTMM';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Error de conexión.']));
}
