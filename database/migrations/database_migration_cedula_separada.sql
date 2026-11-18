ALTER TABLE `clientes`
    ADD COLUMN `tipo_documento` CHAR(1) NULL AFTER `id`;

UPDATE `clientes`
SET `tipo_documento` = UPPER(LEFT(`ci`, 1)),
    `ci` = SUBSTRING(`ci`, 3)
WHERE `ci` REGEXP '^[VEJGP]-[0-9]{6,9}$';

ALTER TABLE `clientes`
    DROP INDEX `uq_clientes_ci`,
    ADD UNIQUE KEY `uq_clientes_documento_ci` (`tipo_documento`, `ci`);
