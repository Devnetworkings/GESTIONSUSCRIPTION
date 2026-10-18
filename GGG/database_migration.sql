USE ggg;

ALTER TABLE `operator`
    ADD COLUMN `operator_role` VARCHAR(20) NOT NULL DEFAULT 'operador';

UPDATE `operator`
SET `operator_role` = 'admin'
WHERE `username` = 'kakaroto';

ALTER TABLE `pagos`
    ADD COLUMN `metodo_pago` VARCHAR(30) NOT NULL DEFAULT 'no_especificado',
    ADD COLUMN `id_plan` INT NULL;

CREATE TABLE IF NOT EXISTS `planes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(80) NOT NULL,
    `duracion_meses` SMALLINT NOT NULL,
    `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_planes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `planes` (`nombre`, `duracion_meses`, `precio`, `activo`)
VALUES ('Mensual', 1, 0.00, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(80) NOT NULL,
    `setting_value` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `app_settings` (`setting_key`, `setting_value`)
VALUES ('dias_aviso_vencimiento', '7')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `operator_id` VARCHAR(64) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
    KEY `idx_password_reset_operator` (`operator_id`),
    KEY `idx_password_reset_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX `idx_clientes_vencimiento` ON `clientes` (`fecha_vencimiento`);
CREATE INDEX `idx_pagos_fecha` ON `pagos` (`fecha`);
