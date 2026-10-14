<?php

// Archivo: buscar_clientes.php
ob_start();
session_start();
header('Content-Type: application/json');
require 'conexion.php';

// Seguridad en el set de grabación
if (!isset($_SESSION['operador_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => '¡Corte! Operador no autorizado.']);
    exit;
}

// 1. Capturamos los filtros de la URL (si existen)
$termino_busqueda = $_GET['q'] ?? '';
$estatus = $_GET['estatus'] ?? ''; // Aquí es donde llega "Deuda" desde tu select del frontend

// 2. Preparamos el escenario base
$sql = "SELECT id, ci, nombres, apellidos, tlf, email, estatus 
        FROM clientes 
        WHERE 1=1";
$parametros = [];

// 3. Añadimos filtros de texto
if (!empty($termino_busqueda)) {
    $sql .= " AND (ci LIKE :q 
                OR nombres LIKE :q 
                OR apellidos LIKE :q 
                OR email LIKE :q 
                OR tlf LIKE :q)";
    $parametros['q'] = '%' . $termino_busqueda . '%';
}

// 4. ⭐ EL GIRO DE LA TRAMA: Evaluamos si el select mandó Activo, Inactivo o Deuda ⭐
if (!empty($estatus)) {
    if (in_array($estatus, ['Activo', 'Inactivo'])) {
        $sql .= " AND estatus = :estatus";
        $parametros['estatus'] = $estatus;
    } elseif ($estatus === 'Deuda') {
        // Cuando el director (tú) selecciona "Deuda" en el combo, aplicamos la condición de saldo
        $sql .= " AND deuda > 0";
    }
}

// 5. Ordenamos y limitamos
$sql .= " ORDER BY nombres ASC LIMIT 50";

try {
    // 6. ¡Acción!
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    echo json_encode(['success' => true, 'data' => $resultados]);

} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error en el set de búsqueda: ' . $e->getMessage()]);
}
?>