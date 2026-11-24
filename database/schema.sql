CREATE TABLE IF NOT EXISTS `clientes` (
    `id` CHAR(36) NOT NULL,
    `tipo_documento` CHAR(1) NULL,
    `ci` VARCHAR(9) NOT NULL,
    `nombres` VARCHAR(50) NOT NULL,
    `apellidos` VARCHAR(50) NOT NULL DEFAULT '',
    `tlf` VARCHAR(12) NOT NULL,
    `email` VARCHAR(254) NOT NULL DEFAULT '',
    `direccion` VARCHAR(255) NULL,
    `estatus` VARCHAR(20) NOT NULL DEFAULT 'Activo',
    `fecha_vencimiento` DATE NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_clientes_documento_ci` (`tipo_documento`, `ci`),
    KEY `idx_clientes_vencimiento` (`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operator` (
    `id` CHAR(36) NOT NULL,
    `ci` VARCHAR(15) NOT NULL,
    `nombres` VARCHAR(50) NOT NULL,
    `apellidos` VARCHAR(50) NOT NULL,
    `contacto` VARCHAR(254) NULL,
    `contacto_verified_at` DATETIME NULL,
    `direccion` VARCHAR(255) NULL,
    `PW` VARCHAR(255) NOT NULL,
    `TLF` VARCHAR(12) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `operator_role` VARCHAR(20) NOT NULL DEFAULT 'operador',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `operator_status` VARCHAR(20) NOT NULL DEFAULT 'Activo',
    `vacation_start` DATE NULL,
    `vacation_end` DATE NULL,
    `login_failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `login_locked_until` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_operator_ci` (`ci`),
    UNIQUE KEY `uq_operator_tlf` (`TLF`),
    UNIQUE KEY `uq_operator_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `planes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(80) NOT NULL,
    `duracion_meses` SMALLINT NOT NULL DEFAULT 1,
    `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `coin` CHAR(3) NOT NULL DEFAULT 'USD',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_planes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pagos` (
    `id` CHAR(36) NOT NULL,
    `id_cliente` CHAR(36) NOT NULL,
    `reference` VARCHAR(60) NOT NULL,
    `monto` DECIMAL(12,2) NOT NULL,
    `coin` CHAR(3) NOT NULL,
    `id_operador` CHAR(36) NOT NULL,
    `fecha_pago` DATE NOT NULL,
    `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `metodo_pago` VARCHAR(30) NOT NULL DEFAULT 'no_especificado',
    `id_plan` INT NULL,
    `monto_usd` DECIMAL(12,2) NULL,
    `tasa_bcv` DECIMAL(18,8) NULL,
    `fecha_valor_bcv` DATE NULL,
    `plan_nombre_snapshot` VARCHAR(80) NULL,
    `plan_duracion_meses_snapshot` SMALLINT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pagos_reference` (`reference`),
    KEY `idx_pagos_fecha_pago` (`fecha_pago`),
    KEY `idx_pagos_cliente_fecha_pago` (`id_cliente`, `fecha_pago`),
    KEY `idx_pagos_operador_fecha_pago` (`id_operador`, `fecha_pago`),
    KEY `idx_pagos_plan` (`id_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(80) NOT NULL,
    `setting_value` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `actor_operator_id` CHAR(36) NULL,
    `actor_username` VARCHAR(50) NOT NULL,
    `action` VARCHAR(80) NOT NULL,
    `entity_type` VARCHAR(60) NOT NULL,
    `entity_id` VARCHAR(64) NULL,
    `changes` LONGTEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_created_at` (`created_at`),
    KEY `idx_audit_actor` (`actor_operator_id`, `created_at`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `operator_id` CHAR(36) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email_verification_hash` (`token_hash`),
    KEY `idx_email_verification_operator` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

INSERT INTO `app_settings` (`setting_key`, `setting_value`)
VALUES ('dias_aviso_vencimiento', '7')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

INSERT INTO `planes` (`nombre`, `duracion_meses`, `precio`, `activo`, `coin`)
VALUES (CONVERT(0x42C3A17369636F USING utf8mb4), 1, 15.00, 1, 'USD')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);
