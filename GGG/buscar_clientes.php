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
$termino_busqueda = $_GET['q'] ?? ''; // Para buscar texto libre
$estatus = $_GET['estatus'] ?? '';     // Para buscar Activo/Inactivo

// 2. Preparamos el escenario base (WHERE 1=1 nos permite añadir condiciones fácilmente)
$sql = "SELECT id, ci, nombres, apellidos, tlf, email, estatus 
        FROM clientes 
        WHERE 1=1";
$parametros = [];

// 3. Añadimos filtros de texto (Busca en múltiples columnas a la vez)
if (!empty($termino_busqueda)) {
    // Usamos LIKE para que encuentre coincidencias parciales
    $sql .= " AND (ci LIKE :q 
                OR nombres LIKE :q 
                OR apellidos LIKE :q 
                OR email LIKE :q 
                OR tlf LIKE :q)";
    $parametros['q'] = '%' . $termino_busqueda . '%';
}

// 4. Añadimos el filtro de estatus si el operador seleccionó uno
if (!empty($estatus) && in_array($estatus, ['Activo', 'Inactivo'])) {
    $sql .= " AND estatus = :estatus";
    $parametros['estatus'] = $estatus;
}

// 5. Ordenamos y limitamos para no saturar la pantalla
$sql .= " ORDER BY nombres ASC LIMIT 50";

try {
    // 6. ¡Acción! Preparamos y ejecutamos
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