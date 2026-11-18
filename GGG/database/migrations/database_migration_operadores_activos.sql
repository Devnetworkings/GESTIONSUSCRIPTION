USE ggg;

ALTER TABLE `operator`
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1;
