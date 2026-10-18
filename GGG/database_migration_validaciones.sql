USE ggg;

ALTER TABLE `clientes`
    MODIFY COLUMN `email` VARCHAR(254) NOT NULL;

ALTER TABLE `operator`
    MODIFY COLUMN `contacto` VARCHAR(254) NULL,
    ADD COLUMN `contacto_verified_at` DATETIME NULL;

ALTER TABLE `planes`
    ADD COLUMN `coin` CHAR(3) NOT NULL DEFAULT 'VES';

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
