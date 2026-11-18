<?php
function normalizarCedula($valor, $tipo = '')
{
    $valor = strtoupper(trim((string) $valor));
    if (preg_match('/^([VEJGP])-?(\d{6,9})$/', $valor, $coincidencias)) {
        return $coincidencias[1] . '-' . $coincidencias[2];
    }

    $tipo = strtoupper(trim((string) $tipo));
    if (preg_match('/^\d{6,9}$/', $valor) && ($tipo === '' || in_array($tipo, ['V', 'E', 'J', 'G', 'P'], true))) {
        return $tipo === '' ? $valor : $tipo . '-' . $valor;
    }

    return false;
}

function descomponerCedula($valor, $tipo = '')
{
    $cedula = normalizarCedula($valor, $tipo);
    if ($cedula === false) {
        return false;
    }

    if (preg_match('/^([VEJGP])-(\d{6,9})$/', $cedula, $coincidencias)) {
        return ['tipo_documento' => $coincidencias[1], 'ci' => $coincidencias[2]];
    }

    return ['tipo_documento' => null, 'ci' => $cedula];
}

function normalizarTelefono($valor)
{
    $valor = trim((string) $valor);
    if (!preg_match('/^04\d{2}-?\d{7}$/', $valor)) {
        return false;
    }
    $digitos = str_replace('-', '', $valor);
    return substr($digitos, 0, 4) . '-' . substr($digitos, 4);
}

function emailValido($valor)
{
    return filter_var(trim((string) $valor), FILTER_VALIDATE_EMAIL) !== false
        && strlen(trim((string) $valor)) <= 254;
}
