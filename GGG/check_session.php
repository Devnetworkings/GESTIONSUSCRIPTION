<?php

ob_start();
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['operador_id'])) {
    // Busca cómo guardaste el nombre en auth.php (suele ser 'username', 'operador' o 'nombres')
    $nombre = isset($_SESSION['username']) ? $_SESSION['username'] : (isset($_SESSION['operador_user']) ? $_SESSION['operador_user'] : 'Operador');

    echo json_encode(['success' => true, 'operador' => $nombre]);
} else {
    echo json_encode(['success' => false]);
}
?>