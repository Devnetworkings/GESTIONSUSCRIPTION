ALTER TABLE `clientes`
    ADD COLUMN `tipo_documento` CHAR(1) NULL AFTER `id`;

UPDATE `clientes`
SET `tipo_documento` = UPPER(LEFT(`ci`, 1)),
    `ci` = SUBSTRING(`ci`, 3)
WHERE `ci` REGEXP '^[VEJGP]-[0-9]{6,9}$';

SET @indice_ci_unico = (
    SELECT MIN(index_name)
    FROM (
        SELECT index_name
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
            AND table_name = 'clientes'
            AND non_unique = 0
        GROUP BY index_name
        HAVING COUNT(*) = 1 AND MAX(column_name) = 'ci'
    ) AS indices_ci
);

SET @eliminar_indice_ci = IF(
    @indice_ci_unico IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE `clientes` DROP INDEX `', REPLACE(@indice_ci_unico, '`', '``'), '`')
);
PREPARE migracion_ci FROM @eliminar_indice_ci;
EXECUTE migracion_ci;
DEALLOCATE PREPARE migracion_ci;

ALTER TABLE `clientes`
    ADD UNIQUE KEY `uq_clientes_documento_ci` (`tipo_documento`, `ci`);
