USE ggg;

ALTER TABLE `pagos`
    ADD COLUMN `monto_usd` DECIMAL(12,2) NULL,
    ADD COLUMN `tasa_bcv` DECIMAL(18,8) NULL,
    ADD COLUMN `fecha_valor_bcv` DATE NULL,
    ADD COLUMN `plan_nombre_snapshot` VARCHAR(80) NULL,
    ADD COLUMN `plan_duracion_meses_snapshot` SMALLINT NULL;

UPDATE `pagos` p
LEFT JOIN `planes` pl ON pl.`id` = p.`id_plan`
SET p.`plan_nombre_snapshot` = COALESCE(pl.`nombre`, 'Plan anterior'),
    p.`plan_duracion_meses_snapshot` = pl.`duracion_meses`,
    p.`monto_usd` = CASE WHEN p.`coin` = 'USD' THEN p.`monto` ELSE NULL END;

UPDATE `pagos` SET `id_plan` = NULL;
DELETE FROM `planes`;

INSERT INTO `planes` (`nombre`, `duracion_meses`, `precio`, `activo`, `coin`)
VALUES (CONVERT(0x42C3A17369636F USING utf8mb4), 1, 15.00, 1, 'USD');
