<?php

function registrarAuditoria(
    PDO $pdo,
    string $accion,
    string $entidad,
    ?string $entidadId = null,
    array $cambios = [],
    ?string $actorId = null,
    ?string $actorUsuario = null
): void {
    $actorId = $actorId ?? ($_SESSION['operador_id'] ?? null);
    $actorUsuario = $actorUsuario ?? ($_SESSION['operador_nombre'] ?? ($actorId ? '' : 'Anónimo'));

    $cambiosSeguros = [];
    foreach ($cambios as $campo => $valores) {
        if (preg_match('/password|passwd|secret|token|credential|^pw$/i', (string) $campo)) {
            continue;
        }
        $cambiosSeguros[$campo] = $valores;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (actor_operator_id, actor_username, action, entity_type, entity_id, changes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $changesJson = $cambiosSeguros
        ? json_encode($cambiosSeguros, JSON_UNESCAPED_UNICODE)
        : null;
    if ($cambiosSeguros && $changesJson === false) {
        throw new RuntimeException('No se pudieron preparar los datos de auditoría.');
    }

    $stmt->execute([
        $actorId,
        $actorUsuario,
        $accion,
        $entidad,
        $entidadId,
        $changesJson
    ]);
}

function registrarCambioAuditoria(array $antes, array $despues): array
{
    $cambios = [];
    foreach ($despues as $campo => $valor) {
        if (preg_match('/password|passwd|secret|token|credential|^pw$/i', (string) $campo)) {
            continue;
        }
        $valorAnterior = $antes[$campo] ?? null;
        if ((string) $valorAnterior !== (string) $valor) {
            $cambios[$campo] = ['anterior' => $valorAnterior, 'nuevo' => $valor];
        }
    }
    return $cambios;
}

function reactivarOperadoresConVacacionesVencidas(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT id, username, vacation_start, vacation_end
         FROM operator
         WHERE operator_status = 'Vacaciones'
           AND vacation_end IS NOT NULL
           AND vacation_end < CURDATE()"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $operador) {
        $pdo->beginTransaction();
        try {
            $actualizar = $pdo->prepare(
                "UPDATE operator
                 SET operator_status = 'Activo', activo = 1, vacation_start = NULL, vacation_end = NULL
                 WHERE id = ? AND operator_status = 'Vacaciones' AND vacation_end < CURDATE()"
            );
            $actualizar->execute([$operador['id']]);
            if ($actualizar->rowCount() > 0) {
                registrarAuditoria(
                    $pdo,
                    'vacaciones_finalizadas',
                    'operador',
                    $operador['id'],
                    [
                        'operator_status' => ['anterior' => 'Vacaciones', 'nuevo' => 'Activo'],
                        'vacation_start' => ['anterior' => $operador['vacation_start'], 'nuevo' => null],
                        'vacation_end' => ['anterior' => $operador['vacation_end'], 'nuevo' => null]
                    ],
                    null,
                    'Sistema'
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
