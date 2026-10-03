-- e-Karamchari migration: add holiday templates
-- Safe to run on an existing installation. The table is created only when absent.

CREATE TABLE IF NOT EXISTS `holiday_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_name` VARCHAR(100) NOT NULL,
  `holiday_type` ENUM('National', 'Regional', 'Restricted', 'Optional') DEFAULT 'National',
  `date_type` ENUM('Fixed', 'Floating') NOT NULL DEFAULT 'Fixed',
  `fixed_month` TINYINT UNSIGNED DEFAULT NULL,
  `fixed_day` TINYINT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_template_active` (`is_active`),
  INDEX `idx_template_date` (`date_type`, `fixed_month`, `fixed_day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
