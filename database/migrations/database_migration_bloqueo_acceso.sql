ALTER TABLE `operator`
    ADD COLUMN `login_failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN `login_locked_until` DATETIME NULL;
