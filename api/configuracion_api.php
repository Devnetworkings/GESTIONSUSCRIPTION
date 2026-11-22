<?php
require_once __DIR__ . '/../includes/entorno.php';
session_start();
require_once __DIR__ . '/../includes/csrf.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/email_verification.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/bcv_rate.php';
require_once __DIR__ . '/../includes/auditoria.php';

$currentOperator = requireActiveOperator($pdo);
if ($currentOperator['operator_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo un administrador puede gestionar la configuración.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $planes = $pdo->query("SELECT id, nombre, 1 AS duracion_meses, precio, 'USD' AS coin, activo FROM planes ORDER BY activo DESC, nombre")->fetchAll(PDO::FETCH_ASSOC);
    $operadores = $pdo->query('SELECT id, username, nombres, apellidos, ci, operator_role, contacto, contacto_verified_at, tlf, direccion, activo, operator_status, vacation_start, vacation_end FROM operator ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $aviso = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'dias_aviso_vencimiento'")->fetchColumn();
    echo json_encode([
        'success' => true,
        'planes' => $planes,
        'operadores' => $operadores,
        'dias_aviso' => (int) ($aviso ?: 7),
        'current_operator_id' => $_SESSION['operador_id'],
        'tasa_bcv' => obtenerTasaBcv($pdo)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

gggRequireCsrfToken();

$action = $_POST['action'] ?? '';
try {
    if ($action === 'plan') {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $precio = filter_var($_POST['precio'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 80 || $precio === false || $precio <= 0 || $precio > 99999999.99) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Ingresa un nombre y un precio USD válidos.']);
            exit;
        }
        if ($id === 0) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO planes (nombre, duracion_meses, precio, activo, coin) VALUES (?, 1, ?, 1, 'USD')");
            $stmt->execute([$nombre, $precio]);
            $id = (int) $pdo->lastInsertId();
            registrarAuditoria($pdo, 'plan_creado', 'plan', (string) $id, ['nombre' => $nombre, 'precio' => $precio]);
            $pdo->commit();
        } else {
            $stmt = $pdo->prepare('SELECT nombre, precio FROM planes WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'No se encontró el plan.']);
                exit;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE planes SET nombre = ?, duracion_meses = 1, precio = ?, coin = 'USD' WHERE id = ?");
            $stmt->execute([$nombre, $precio, $id]);
            $changes = registrarCambioAuditoria($before, ['nombre' => $nombre, 'precio' => $precio]);
            if ($changes) {
                registrarAuditoria($pdo, 'plan_actualizado', 'plan', (string) $id, $changes);
            }
            $pdo->commit();
        }
    } elseif ($action === 'plan_estado') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $activo = filter_var($_POST['activo'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0 || !in_array($activo, [0, 1], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Plan o estado no válidos.']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT activo FROM planes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $before = $stmt->fetchColumn();
        if ($before === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No se encontró el plan.']);
            exit;
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE planes SET activo = ? WHERE id = ?');
        $stmt->execute([$activo, $id]);
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare('SELECT 1 FROM planes WHERE id = ?');
            $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'No se encontró el plan.']);
                exit;
            }
        }
        if ($before !== false && (int) $before !== $activo) {
            registrarAuditoria($pdo, 'estado_plan_actualizado', 'plan', (string) $id, [
                'activo' => ['anterior' => (int) $before, 'nuevo' => $activo]
            ]);
        }
        $pdo->commit();
    } elseif ($action === 'tasa_manual') {
        $tasa = trim($_POST['tasa_bcv'] ?? '');
        $normalized = str_replace(',', '.', $tasa);
        if (!is_numeric($normalized) || (float) $normalized <= 0 || (float) $normalized > 9999999999.99999999) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'La tasa manual debe ser un número positivo válido.']);
            exit;
        }
        $before = obtenerTasaBcv($pdo);
        $pdo->beginTransaction();
        establecerTasaManualBcv($pdo, $normalized);
        registrarAuditoria($pdo, 'tasa_manual_actualizada', 'configuracion', 'tasa_bcv', [
            'tasa' => ['anterior' => $before['rate'] ?? null, 'nuevo' => $normalized]
        ]);
        $pdo->commit();
    } elseif ($action === 'aviso') {
        $dias = filter_var($_POST['dias_aviso'] ?? null, FILTER_VALIDATE_INT);
        if ($dias === false || $dias < 0 || $dias > 90) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'El aviso debe ser de 0 a 90 días.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'dias_aviso_vencimiento'");
        $stmt->execute();
        $before = $stmt->fetchColumn();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('dias_aviso_vencimiento', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([(string) $dias]);
        if ((string) $before !== (string) $dias) {
            registrarAuditoria($pdo, 'aviso_actualizado', 'configuracion', 'dias_aviso_vencimiento', [
                'dias' => ['anterior' => $before === false ? null : (int) $before, 'nuevo' => $dias]
            ]);
        }
        $pdo->commit();
    } elseif ($action === 'role') {
        $id = trim($_POST['id'] ?? '');
        $role = $_POST['role'] ?? '';
        if ($id === '' || !in_array($role, ['admin', 'operador', 'soporte_ti'], true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Rol o cuenta no válidos.']);
            exit;
        }
        if ($id === (string) $_SESSION['operador_id'] && $role !== 'admin') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No puedes quitarte tu propio rol de administrador.']);
            exit;
        }
        if ($role !== 'admin') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM operator WHERE operator_role = 'admin' AND activo = 1 AND operator_status = 'Activo' AND id <> ?");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() === 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'No puedes quitar el rol al último administrador activo.']);
                exit;
            }
        }
        $stmt = $pdo->prepare('SELECT operator_role FROM operator WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $previousRole = $stmt->fetchColumn();
        if ($previousRole === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No se encontró esa cuenta de operador.']);
            exit;
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE operator SET operator_role = ? WHERE id = ?');
        $stmt->execute([$role, $id]);
        if ($previousRole !== $role) {
            registrarAuditoria($pdo, 'rol_operador_actualizado', 'operador', $id, [
                'operator_role' => ['anterior' => $previousRole, 'nuevo' => $role]
            ]);
        }
        $pdo->commit();
    } elseif ($action === 'estado_operador') {
        $id = trim($_POST['id'] ?? '');
        $estado = $_POST['estado'] ?? '';
        $vacationStart = trim($_POST['vacation_start'] ?? '');
        $vacationEnd = trim($_POST['vacation_end'] ?? '');
        $estadosValidos = ['Activo', 'Inactivo', 'Vacaciones', 'Despedido'];
        if ($id === '' || !in_array($estado, $estadosValidos, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Cuenta o estado no válidos.']);
            exit;
        }
        if ($id === (string) $_SESSION['operador_id'] && $estado !== 'Activo') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No puedes cambiar el estado de tu propia cuenta.']);
            exit;
        }
        if ($estado === 'Vacaciones') {
            $inicio = DateTime::createFromFormat('!Y-m-d', $vacationStart);
            $fin = DateTime::createFromFormat('!Y-m-d', $vacationEnd);
            if (!$inicio || $inicio->format('Y-m-d') !== $vacationStart || !$fin
                || $fin->format('Y-m-d') !== $vacationEnd || $vacationEnd < $vacationStart) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Indica un período de vacaciones válido.']);
                exit;
            }
        } else {
            $vacationStart = '';
            $vacationEnd = '';
        }
        $stmt = $pdo->prepare('SELECT operator_role, activo, operator_status, vacation_start, vacation_end FROM operator WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No se encontró esa cuenta de operador.']);
            exit;
        }
        if ($estado !== 'Activo' && $target['operator_role'] === 'admin' && (int) $target['activo'] === 1) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM operator WHERE operator_role = 'admin' AND activo = 1 AND operator_status = 'Activo' AND id <> ?");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() === 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'No puedes desactivar al último administrador activo.']);
                exit;
            }
        }
        $pdo->beginTransaction();
        $activo = $estado === 'Activo' ? 1 : 0;
        $stmt = $pdo->prepare(
            'UPDATE operator SET activo = ?, operator_status = ?, vacation_start = ?, vacation_end = ? WHERE id = ?'
        );
        $stmt->execute([$activo, $estado, $vacationStart !== '' ? $vacationStart : null, $vacationEnd !== '' ? $vacationEnd : null, $id]);
        $changes = registrarCambioAuditoria($target, [
            'operator_status' => $estado,
            'vacation_start' => $vacationStart !== '' ? $vacationStart : null,
            'vacation_end' => $vacationEnd !== '' ? $vacationEnd : null
        ]);
        if ($changes) {
            registrarAuditoria($pdo, 'estado_operador_actualizado', 'operador', $id, $changes);
        }
        $pdo->commit();
    } elseif ($action === 'datos_operador') {
        require_once __DIR__ . '/../includes/validaciones.php';
        $id = trim($_POST['id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $cedula = normalizarCedula($_POST['ci'] ?? '');
        $tlf = normalizarTelefono($_POST['tlf'] ?? '');
        $email = trim($_POST['contacto'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        if ($id === '' || $username === '' || mb_strlen($username, 'UTF-8') > 50 || $nombres === ''
            || mb_strlen($nombres, 'UTF-8') > 50 || mb_strlen($apellidos, 'UTF-8') > 50
            || $cedula === false || $tlf === false || !emailValido($email) || mb_strlen($direccion, 'UTF-8') > 255) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Verifica los datos del operador.']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT username, nombres, apellidos, ci, tlf, contacto, direccion FROM operator WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No se encontró esa cuenta de operador.']);
            exit;
        }
        $after = ['username' => $username, 'nombres' => $nombres, 'apellidos' => $apellidos, 'ci' => $cedula, 'tlf' => $tlf, 'contacto' => $email, 'direccion' => $direccion];
        $changes = registrarCambioAuditoria($before, $after);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'UPDATE operator SET username = ?, nombres = ?, apellidos = ?, ci = ?, tlf = ?, contacto = ?, direccion = ?,
             contacto_verified_at = IF(contacto <> ?, NULL, contacto_verified_at) WHERE id = ?'
        );
        $stmt->execute([$username, $nombres, $apellidos, $cedula, $tlf, $email, $direccion, $email, $id]);
        if (isset($changes['contacto'])) {
            $stmt = $pdo->prepare('DELETE FROM email_verification_tokens WHERE operator_id = ?');
            $stmt->execute([$id]);
        }
        if ($changes) {
            registrarAuditoria($pdo, 'datos_operador_actualizados', 'operador', $id, $changes);
        }
        $pdo->commit();
    } elseif ($action === 'verificar_email') {
        $id = trim($_POST['id'] ?? '');
        $stmt = $pdo->prepare('SELECT username, contacto, contacto_verified_at FROM operator WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $operator = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$operator || !filter_var($operator['contacto'] ?? '', FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'La cuenta no tiene un correo válido para verificar.']);
            exit;
        }
        if (!empty($operator['contacto_verified_at'])) {
            echo json_encode(['success' => true, 'message' => 'Ese correo ya fue verificado.']);
            exit;
        }
        if (!enviarVerificacionContacto($pdo, $id, $operator['username'], $operator['contacto'])) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'No se pudo enviar el enlace. Revisa APP_BASE_URL y el correo saliente del servidor.']);
            exit;
        }
        registrarAuditoria($pdo, 'verificacion_correo_solicitada', 'operador', $id, ['correo' => $operator['contacto']]);
    } elseif ($action === 'guardar_email') {
        require_once __DIR__ . '/../includes/validaciones.php';
        $id = trim($_POST['id'] ?? '');
        $email = trim($_POST['contacto'] ?? '');
        if ($id === '' || !emailValido($email)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Escribe un correo con formato válido (máximo 254 caracteres).']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT contacto FROM operator WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $before = $stmt->fetchColumn();
        if ($before === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No se encontró esa cuenta de operador.']);
            exit;
        }
        $pdo->beginTransaction();
        if ((string) $before !== $email) {
            $stmt = $pdo->prepare('UPDATE operator SET contacto = ?, contacto_verified_at = NULL WHERE id = ?');
            $stmt->execute([$email, $id]);
            $stmt = $pdo->prepare('DELETE FROM email_verification_tokens WHERE operator_id = ?');
            $stmt->execute([$id]);
            registrarAuditoria($pdo, 'correo_operador_actualizado', 'operador', $id, [
                'contacto' => ['anterior' => $before, 'nuevo' => $email]
            ]);
        }
        $pdo->commit();
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Acción de configuración desconocida.']);
        exit;
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($e->getCode() === '23000' ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getCode() === '23000'
            ? 'Ya existe un registro con esos datos (nombre, correo, cédula o teléfono).'
            : 'No se pudo guardar la configuración.'
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('No se pudo guardar la configuración: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar la configuración.']);
}
