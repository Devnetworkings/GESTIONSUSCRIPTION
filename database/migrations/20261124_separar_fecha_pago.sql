-- Migración: separar la fecha efectiva del pago de su fecha real de registro.
-- Ejecutar una sola vez sobre una instalación que ya tenga la columna pagos.fecha.

ALTER TABLE pagos
    ADD COLUMN fecha_pago DATE NULL AFTER id_operador,
    ADD COLUMN fecha_registro DATETIME NULL AFTER fecha_pago;

UPDATE pagos
SET fecha_pago = DATE(fecha),
    fecha_registro = fecha;

ALTER TABLE pagos
    MODIFY fecha_pago DATE NOT NULL,
    MODIFY fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    DROP INDEX idx_pagos_fecha,
    DROP INDEX idx_pagos_cliente_fecha,
    DROP INDEX idx_pagos_operador_fecha,
    DROP COLUMN fecha,
    ADD INDEX idx_pagos_fecha_pago (fecha_pago),
    ADD INDEX idx_pagos_cliente_fecha_pago (id_cliente, fecha_pago),
    ADD INDEX idx_pagos_operador_fecha_pago (id_operador, fecha_pago);
