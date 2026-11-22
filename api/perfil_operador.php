<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/validaciones.php';
require_once __DIR__ . '/../includes/auditoria.php';

$operator = requireActiveOperator($pdo);
$id = (string) $_SESSION['operador_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT nombres, apellidos, ci, contacto, tlf, direccion, username, operator_role, activo,
                operator_status, vacation_start, vacation_end, contacto_verified_at
         FROM operator WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($perfil) {
        $perfil['contacto_verificado'] = !empty($perfil['contacto_verified_at']);
        unset($perfil['contacto_verified_at']);
    }
    echo json_encode(['success' => true, 'perfil' => $perfil]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

gggRequireCsrfToken();

$email = trim($_POST['contacto'] ?? '');
$telefono = normalizarTelefono($_POST['tlf'] ?? '');
if (!emailValido($email) || $telefono === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Verifica el correo y el teléfono ingresados.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT contacto, tlf FROM operator WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $before = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$before) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No se encontró tu perfil.']);
        exit;
    }

    $after = ['contacto' => $email, 'tlf' => $telefono];
    $changes = registrarCambioAuditoria($before, $after);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'UPDATE operator SET contacto = ?, tlf = ?, contacto_verified_at = IF(contacto <> ?, NULL, contacto_verified_at) WHERE id = ?'
    );
    $stmt->execute([$email, $telefono, $email, $id]);
    if (isset($changes['contacto'])) {
        $stmt = $pdo->prepare('DELETE FROM email_verification_tokens WHERE operator_id = ?');
        $stmt->execute([$id]);
    }
    if ($changes) {
        registrarAuditoria($pdo, 'perfil_actualizado', 'operador', $id, $changes);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Tus datos de contacto fueron actualizados.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($e->getCode() === '23000' ? 409 : 500);
    echo json_encode(['success' => false, 'message' => $e->getCode() === '23000' ? 'Ese teléfono ya está asignado.' : 'No se pudo actualizar tu perfil.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('No se pudo actualizar el perfil del operador: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar tu perfil.']);
}
