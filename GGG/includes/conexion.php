<?php

require_once __DIR__ . '/entorno.php';

if (GGG_ES_PRODUCCION) {
    require_once __DIR__ . '/conexion.prod.php';
} else {
    require_once __DIR__ . '/conexion.local.php';
}
