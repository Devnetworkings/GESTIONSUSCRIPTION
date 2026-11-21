ALTER TABLE `operator`
    ADD COLUMN `operator_status` VARCHAR(20) NOT NULL DEFAULT 'Activo',
    ADD COLUMN `vacation_start` DATE NULL,
    ADD COLUMN `vacation_end` DATE NULL;

UPDATE `operator`
SET `operator_status` = IF(`activo` = 1, 'Activo', 'Inactivo');

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
