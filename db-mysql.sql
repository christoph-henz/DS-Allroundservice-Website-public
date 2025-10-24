-- =============================================
-- MySQL Database Schema for DS-Allroundservice
-- Converted from SQLite schema
-- Date: 2025-10-14
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- Email System Tables
-- =============================================

CREATE TABLE IF NOT EXISTS `email_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_name` VARCHAR(255) NOT NULL,
  `email_address` VARCHAR(255) NOT NULL,
  `imap_server` VARCHAR(255) NOT NULL,
  `imap_port` INT DEFAULT 993,
  `username` VARCHAR(255) NOT NULL,
  `password_encrypted` TEXT NOT NULL,
  `use_ssl` TINYINT(1) DEFAULT 1,
  `protocol` VARCHAR(50) DEFAULT 'imap',
  `is_active` TINYINT(1) DEFAULT 1,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_events` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `event_type` VARCHAR(50) NOT NULL,
  `email_uid` VARCHAR(255) NOT NULL,
  `event_data` LONGTEXT,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `sequence_number` INT,
  INDEX `idx_email_uid` (`email_uid`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `template_key` VARCHAR(100) NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `variables_used` LONGTEXT,
  `status` VARCHAR(50) DEFAULT 'sent',
  `error_message` TEXT,
  `sent_at` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_recipient` (`recipient_email`),
  INDEX `idx_template_key` (`template_key`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_sequence` (
  `counter` INT DEFAULT 1 PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_snapshots` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `snapshot_type` VARCHAR(50) DEFAULT 'full',
  `snapshot_data` LONGTEXT NOT NULL,
  `email_count` INT NOT NULL,
  `last_uid` VARCHAR(255),
  `last_sequence_number` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1,
  `folder` VARCHAR(100) DEFAULT 'INBOX',
  INDEX `idx_snapshot_type` (`snapshot_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_states` (
  `email_uid` VARCHAR(255) PRIMARY KEY,
  `current_state` VARCHAR(100) NOT NULL,
  `last_event_id` BIGINT,
  `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`last_event_id`) REFERENCES `email_events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_template_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject` VARCHAR(200),
  `body_html` LONGTEXT NOT NULL,
  `body_text` LONGTEXT NOT NULL,
  `template_type` VARCHAR(50) DEFAULT 'general',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `template_key` VARCHAR(100),
  `variables` TEXT,
  INDEX `idx_template_key` (`template_key`),
  INDEX `idx_template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Media Tables
-- =============================================

CREATE TABLE IF NOT EXISTS `images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Service Tables
-- =============================================

CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(50),
  `color` VARCHAR(7) DEFAULT '#007cba',
  `sort_order` INT DEFAULT 0,
  `pricing_data` LONGTEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_slug` (`slug`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_pages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT NOT NULL,
  `hero_title` VARCHAR(200),
  `hero_subtitle` TEXT,
  `intro_title` VARCHAR(200),
  `intro_content` LONGTEXT,
  `features_title` VARCHAR(200),
  `features_subtitle` TEXT,
  `features_content` LONGTEXT,
  `process_title` VARCHAR(200),
  `process_subtitle` TEXT,
  `process_content` LONGTEXT,
  `pricing_title` VARCHAR(200),
  `pricing_subtitle` TEXT,
  `faq_title` TEXT,
  `faq_content` LONGTEXT,
  `meta_title` VARCHAR(200),
  `meta_description` TEXT,
  `meta_keywords` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Questionnaire Tables
-- =============================================

CREATE TABLE IF NOT EXISTS `questionnaires` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `service_id` INT,
  `service_types` TEXT,
  `status` VARCHAR(20) DEFAULT 'draft',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL,
  INDEX `idx_service_id` (`service_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `question_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `questionnaire_id` INT NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_fixed` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`questionnaire_id`) REFERENCES `questionnaires`(`id`) ON DELETE CASCADE,
  INDEX `idx_questionnaire_id` (`questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `questions_simple` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `questionnaire_id` INT,
  `group_id` INT,
  `question_text` VARCHAR(500) NOT NULL,
  `question_type` VARCHAR(50) NOT NULL,
  `is_required` TINYINT(1) DEFAULT 0,
  `placeholder_text` VARCHAR(200),
  `help_text` TEXT,
  `options` LONGTEXT,
  `validation_rules` TEXT,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_fixed` TINYINT(1) DEFAULT 0,
  `sort_order_in_group` INT DEFAULT 0,
  FOREIGN KEY (`group_id`) REFERENCES `question_groups`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`questionnaire_id`) REFERENCES `questionnaires`(`id`) ON DELETE CASCADE,
  INDEX `idx_questionnaire_id` (`questionnaire_id`),
  INDEX `idx_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `questionnaire_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `questionnaire_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `group_id` INT DEFAULT NULL,
  FOREIGN KEY (`question_id`) REFERENCES `questions_simple`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`questionnaire_id`) REFERENCES `questionnaires`(`id`) ON DELETE CASCADE,
  INDEX `idx_questionnaire_id` (`questionnaire_id`),
  INDEX `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `questionnaire_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(50) NOT NULL UNIQUE,
  `service_id` INT NOT NULL,
  `template_id` INT NOT NULL,
  `customer_name` VARCHAR(200),
  `customer_email` VARCHAR(255),
  `customer_phone` VARCHAR(50),
  `form_data` LONGTEXT NOT NULL,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `status` VARCHAR(50) DEFAULT 'new',
  `assigned_to` INT,
  `priority` INT DEFAULT 0,
  `pdf_generated` TINYINT(1) DEFAULT 0,
  `pdf_path` VARCHAR(255),
  `email_sent` TINYINT(1) DEFAULT 0,
  `customer_notified` TINYINT(1) DEFAULT 0,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME,
  `completed_at` DATETIME,
  `internal_notes` LONGTEXT,
  `customer_notes` LONGTEXT,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`template_id`) REFERENCES `questionnaires`(`id`) ON DELETE RESTRICT,
  INDEX `idx_reference` (`reference`),
  INDEX `idx_service_id` (`service_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Offers Table
-- =============================================

CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT NOT NULL,
  `offer_number` VARCHAR(20) NOT NULL UNIQUE,
  `customer_name` VARCHAR(255),
  `customer_email` VARCHAR(255),
  `customer_phone` VARCHAR(50),
  `service_id` INT NOT NULL,
  `service_name` VARCHAR(255) NOT NULL,
  `pricing_items` JSON,
  `total_net` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `total_vat` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `total_gross` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `vat_rate` DECIMAL(5, 2) NOT NULL DEFAULT 19.00,
  `notes` TEXT,
  `terms` TEXT,
  `valid_until` DATE,
  `execution_date` DATE,
  `status` INT,
  `pdf_path` VARCHAR(500),
  `created_by` VARCHAR(100) DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL,
  `responded_at` TIMESTAMP NULL,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`submission_id`) REFERENCES `questionnaire_submissions`(`id`) ON DELETE CASCADE,
  INDEX `idx_offer_number` (`offer_number`),
  INDEX `idx_submission_id` (`submission_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Settings Table
-- =============================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` LONGTEXT,
  `setting_type` VARCHAR(20) DEFAULT 'string',
  `description` TEXT,
  `is_public` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- User Management Tables
-- =============================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(50),
  `last_name` VARCHAR(50),
  `role` VARCHAR(20) NOT NULL DEFAULT 'Mitarbeiter',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME,
  `login_attempts` INT DEFAULT 0,
  `locked_until` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_activity_log` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `success` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` VARCHAR(20) NOT NULL,
  `permission_key` VARCHAR(100) NOT NULL,
  `permission_value` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_role_permission` (`role`, `permission_key`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `session_token` VARCHAR(255) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_session_token` (`session_token`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Enable Foreign Key Checks
-- =============================================

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- Default Settings Data
-- =============================================

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'DS Allroundservices', 'string', 'Name der Website', 1, '2025-09-19 14:17:28', '2025-10-14 19:11:16'),
(2, 'site_description', 'Ihr zuverlässiger Partner für Umzüge, Transport und Entrümpelung', 'string', 'Beschreibung der Website', 1, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(3, 'contact_email', 'info@ds-allroundservice.de', 'string', 'Kontakt E-Mail Adresse', 1, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(4, 'contact_phone', '+49 6021 123456', 'string', 'Kontakt Telefonnummer', 1, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(5, 'contact_address', 'Darmstädter Straße 0 63741 Aschaffenburg', 'string', 'Geschäftsadresse', 1, '2025-09-19 14:17:29', '2025-10-01 16:11:14'),
(6, 'business_hours', '{"monday":"8:00-17:00","tuesday":"8:00-18:00","wednesday":"8:00-18:00","thursday":"8:00-18:00","friday":"8:00-18:00","saturday":"9:00-16:00","sunday":"Geschlossen"}', 'json', 'Geschäftszeiten', 1, '2025-09-19 14:17:29', '2025-10-14 19:12:21'),
(7, 'social_facebook', '', 'string', 'Facebook URL', 1, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(8, 'social_instagram', '', 'string', 'Instagram URL', 1, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(9, 'social_twitter', '', 'string', 'Twitter URL', 1, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(15, 'maintenance_mode', '0', 'bool', 'Wartungsmodus aktiviert', 0, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(16, 'google_analytics_id', '', 'string', 'Google Analytics Tracking ID', 0, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(17, 'cookie_consent_required', '1', 'bool', 'Cookie Consent Banner anzeigen', 0, '2025-09-19 14:17:29', '2025-10-03 14:25:15'),
(18, 'max_file_upload_size', '10485760', 'int', 'Maximale Upload-Größe in Bytes (10MB)', 0, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(19, 'default_service_color', '#007cba', 'string', 'Standard Farbe für neue Services', 1, '2025-09-19 14:17:29', '2025-10-03 18:24:09'),
(20, 'admin_items_per_page', '20', 'int', 'Anzahl Einträge pro Seite im Admin', 0, '2025-09-19 14:17:29', '2025-09-19 14:17:29'),
(21, 'office_company_has_VAT', '0', 'bool', 'Wird Umsatzsteuer abgeführt? (DIES HAT AUSWIRKUNGEN AUF DIE RECHNUNGSERSTELLUNG!!)', 1, '2025-09-19 14:48:56', '2025-10-03 18:23:21'),
(23, 'company_website', 'www.ds-allroundservice.de', 'string', 'Website', 1, '2025-10-04 11:39:33', '2025-10-04 11:39:33'),
(24, 'email_imap_server', 'imap.ionos.de', 'string', 'IMAP-Server für E-Mail-Posteingang', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(25, 'email_imap_port', '993', 'int', 'IMAP-Server Port (Standard: 993 für SSL)', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(26, 'email_username', 'info@dionysos-aburg.de', 'string', 'E-Mail-Benutzername für IMAP-Zugang', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(27, 'email_password', 'RGlvbnlzb3MyMDI0ITAxMDkyMDI0', 'string', 'E-Mail-Passwort (verschlüsselt)', 0, '2025-10-04 15:45:36', '2025-10-04 15:52:35'),
(28, 'email_use_ssl', '1', 'bool', 'SSL/TLS für E-Mail-Verbindung verwenden', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(29, 'email_protocol', 'imap', 'string', 'E-Mail-Protokoll (imap oder pop3)', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(30, 'email_inbox_enabled', '1', 'bool', 'E-Mail-Posteingang aktiviert', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(31, 'email_inbox_refresh_interval', '300', 'int', 'Automatische Aktualisierung (Sekunden)', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(32, 'email_inbox_max_emails', '100', 'int', 'Maximale Anzahl E-Mails pro Ladung', 0, '2025-10-04 15:45:36', '2025-10-04 15:45:36'),
(33, 'email_signature', 'Mit freundlichen Grüßen<br>Ihr DS Allroundservice Team', 'text', 'Standard E-Mail-Signatur', 0, '2025-10-04 15:46:47', '2025-10-04 15:46:47'),
(34, 'email_reply_prefix', 'AW: ', 'string', 'Präfix für Antwort-E-Mails', 0, '2025-10-04 15:46:47', '2025-10-04 15:46:47'),
(35, 'email_forward_prefix', 'WG: ', 'string', 'Präfix für weitergeleitete E-Mails', 0, '2025-10-04 15:46:47', '2025-10-04 15:46:47'),
(36, 'email_auto_reply_enabled', '0', 'bool', 'Automatische Antworten aktiviert', 0, '2025-10-04 15:46:47', '2025-10-04 15:46:47'),
(37, 'email_auto_reply_message', 'Vielen Dank für Ihre E-Mail. Wir werden uns schnellstmöglich bei Ihnen melden.', 'text', 'Automatische Antwort-Nachricht', 0, '2025-10-04 15:46:47', '2025-10-04 15:46:47'),
(38, 'company_vat_id', 'DE0123456789', 'string', 'Umsatzsteuer ID', 1, '2025-10-07 09:58:15', '2025-10-07 09:58:15');

-- =============================================
-- Default Users Data
-- =============================================

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `is_active`, `last_login`, `login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@ds-allroundservice.de', '$2y$10$4qDy3eHxIgX99iYLTcaC4umbL9ZUUAxuR5DIxJbXJzp99veLaMDme', 'System', 'Administrator', 'Admin', 1, '2025-10-14 19:13:00', 0, NULL, '2025-10-03 11:24:39', '2025-10-03 11:24:39'),
(2, 'Luisa', 'luisalutz5@gmail.com', '$2y$10$daQPba8LYjfZLmU9287Ps.ilZHbr9SFZ/SyicA/yQLETkV1IpWNAK', 'Luisa', 'Lutz', 'Chef', 1, '2025-10-14 19:08:46', 0, NULL, '2025-10-03 13:41:29', '2025-10-06 16:35:56'),
(3, 'chefos', 'test@test.de', '$2y$10$3IfGRvQe02Zbyihpp/jM2OuLge32.4/vlOcZQsAYJnBGPj7oiX93a', 'Daniel', 'Skopek', 'Mitarbeiter', 1, '2025-10-06 11:28:11', 0, NULL, '2025-10-03 13:42:28', '2025-10-14 19:09:51'),
(4, 'Ioanni', 'ioannig663@gmail.com', '$2y$10$NRzOCKcigzR7fCkhlcV9qeQqxpOx3XUMeLTPLwZpzHQ9NI2y3.Jta', 'Ioanni', 'Gkogkas', 'Moderator', 1, '2025-10-14 19:02:44', 0, NULL, '2025-10-03 17:43:19', '2025-10-06 11:49:45'),
(5, 'Mitarbeiter2', 'mitarbeiter2@ds-allroundservice.de', '$2y$10$3vH7/YSYNJG7HiPPn1aJw.t4ZWbeZKtBsXWSZjIUPTu91hQj7wWlC', 'Mitarbeiter', '2', 'Mitarbeiter', 1, '2025-10-14 19:19:21', 0, NULL, '2025-10-06 11:51:06', '2025-10-14 19:09:09');

-- =============================================
-- Default Services Data
-- =============================================

INSERT INTO `services` (`id`, `slug`, `name`, `title`, `description`, `icon`, `color`, `sort_order`, `pricing_data`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'umzuege', 'Umzüge', 'Professionelle Umzugsservice', 'Komplette Umzugsdienstleistungen für Privatpersonen und Unternehmen', 'umzug.webp', '#007cba', 1, '[{"description":"1-Zimmer Wohnung","value":250,"unit":"€"},{"description":"2-Zimmer Wohnung","value":400,"unit":"€"},{"description":"3-Zimmer Wohnung","value":550,"unit":"€"},{"description":"Hausumzug","value":850,"unit":"€"}]', 1, '2025-09-10 14:59:01', '2025-10-14 18:39:20'),
(2, 'transport', 'Transport', 'Transport & Logistik', 'Sichere Transportdienstleistungen für alle Arten von Gütern', 'shipping.png', '#28a745', 2, '[{"description":"Individuelle Preisvereinbarung","value":50,"unit":"€ / Stunde"},{"description":"Kilometerpauschale","value":50,"unit":"Cent / km"}]', 1, '2025-09-10 14:59:01', '2025-10-01 16:13:05'),
(3, 'entruempelung', 'Entrümpelung', 'Entrümpelung & Entsorgung', 'Professionelle Entrümpelung von Wohnungen, Häusern und Gewerbeobjekten', 'trash.png', '#dc3545', 3, '[{"description":"Individuelle Preisvereinbarung","value":35,"unit":"€ / m²"}]', 1, '2025-09-10 14:59:01', '2025-09-17 11:26:59'),
(4, 'aufloesung', 'Auflösung', 'Haushaltsauflösung', 'Komplette Haushaltsauflösung mit fachgerechter Entsorgung', 'house.png', '#fd7e14', 4, '[]', 0, '2025-09-10 14:59:01', '2025-10-14 18:40:17'),
(5, 'hausmeister-dienste', 'Hausmeister-Dienste', 'Hausmeisterdienstleistungen', '', 'logo.png', '#007cba', 0, '[{"description":"Gartenpflege","value":200,"unit":"€ / Monat"}]', 1, '2025-09-19 01:09:33', '2025-10-14 18:39:53');

-- =============================================
-- Default Images Data
-- =============================================

INSERT INTO `images` (`id`, `name`, `created_at`) VALUES
(8, 'house.png', '2025-09-17 10:49:26'),
(9, 'logo.png', '2025-09-17 10:49:26'),
(10, 'shipping.png', '2025-09-17 10:49:26'),
(11, 'title.jpg', '2025-09-17 10:49:26'),
(12, 'trash.png', '2025-09-17 10:49:26'),
(13, 'umzug.webp', '2025-09-17 10:49:26'),
(14, 'instagram.svg', '2025-09-17 13:32:12'),
(15, 'mail.svg', '2025-09-17 13:32:12'),
(16, 'phone.svg', '2025-09-17 13:32:12'),
(17, 'admin.webp', '2025-10-14 18:50:19');

-- =============================================
-- Default Service Pages Data
-- =============================================

INSERT INTO `service_pages` (`id`, `service_id`, `hero_title`, `hero_subtitle`, `intro_title`, `intro_content`, `features_title`, `features_subtitle`, `features_content`, `process_title`, `process_subtitle`, `process_content`, `pricing_title`, `pricing_subtitle`, `faq_title`, `faq_content`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`) VALUES
(1, 1, 'Professioneller Umzugsservice', 'Komplette Umzugsdienstleistungen für Privatpersonen und Unternehmen', 'Ihr zuverlässiger Partner für Umzüge', 'Komplette Umzugsdienstleistungen für Privatpersonen und Unternehmen', 'Unsere Umzugsleistungen', 'Alles aus einer Hand für Ihren perfekten Service', '[{"icon":"?","title":"Verpackungsservice","description":"Professionelles Verpacken Ihrer Habseligkeiten mit hochwertigem Material. Ihre Gegenstände sind bei uns sicher."},{"icon":"?","title":"Transport","description":"Sichere Beförderung mit modernen Umzugswagen. Unsere Fahrzeuge sind voll ausgestattet und versichert."},{"icon":"?","title":"Möbelmontage","description":"Demontage am alten und Aufbau am neuen Wohnort. Ihre Möbel werden fachgerecht behandelt."}]', 'So läuft Ihr Umzug ab', 'In einfachen Schritten zu Ihrem Ziel', '[{"title":"Beratung","description":"Kostenlose Erstberatung und Bedarfsanalyse"},{"title":"Planung","description":"Detaillierte Planung des Vorgehens"},{"title":"Durchführung","description":"Professionelle Umsetzung durch unser Team"}]', 'Umzugspreise', 'Transparente Preisgestaltung ohne versteckte Kosten', 'Häufige Fragen', '', 'Professioneller Umzugsservice', 'Komplette Umzugsdienstleistungen für Privatpersonen und Unternehmen', '', '2025-09-16 11:53:23', '2025-09-17 13:12:33'),
(2, 2, 'Transport & Logistik', 'Sichere Transportdienstleistungen für alle Arten von Gütern', 'Ihr zuverlässiger Partner für Transport', 'Sichere Transportdienstleistungen für alle Arten von Gütern', 'Unsere Transport-Leistungen', 'Alles aus einer Hand für Ihren perfekten Service', '[{"icon":"?","title":"Möbeltransport","description":"Sicherer Transport von Möbeln aller Art mit professioneller Ausstattung."},{"icon":"?","title":"Klaviertransport","description":"Spezialisierter Transport für Klaviere und andere empfindliche Instrumente."},{"icon":"?","title":"Geräteverlegung","description":"Fachgerechte Verlegung von Haushaltsgeräten und Elektronik."},{"icon":"?","title":"Verpackung","description":"Professionelle Verpackung für sicheren Transport."}]', 'So läuft Ihr Transport ab', 'In einfachen Schritten zu Ihrem Ziel', '[{"title":"Beratung","description":"Kostenlose Erstberatung und Bedarfsanalyse"},{"title":"Planung","description":"Detaillierte Planung des Vorgehens"},{"title":"Durchführung","description":"Professionelle Umsetzung durch unser Team"},{"title":"Abschluss","description":"Finale Kontrolle und Übergabe"}]', 'Transport-Preise', 'Transparente Preisgestaltung ohne versteckte Kosten', 'Häufige Fragen', '', 'Transport & Logistik', 'Sichere Transportdienstleistungen für alle Arten von Gütern', '', '2025-09-16 12:03:08', '2025-09-16 12:06:00'),
(3, 3, 'Entrümpelung & Entsorgung', 'Professionelle Entrümpelung von Wohnungen, Häusern und Gewerbeobjekten', 'Ihr zuverlässiger Partner für Entrümpelung', 'Professionelle Entrümpelung von Wohnungen, Häusern und Gewerbeobjekten', 'Unsere Entrümpelung-Leistungen', 'Alles aus einer Hand für Ihren perfekten Service', '[]', 'So läuft Ihr Entrümpelung ab', 'In einfachen Schritten zu Ihrem Ziel', '[{"title":"Beratung","description":"Kostenlose Erstberatung und Bedarfsanalyse"},{"title":"Planung","description":"Detaillierte Planung des Vorgehens"},{"title":"Durchführung","description":"Professionelle Umsetzung durch unser Team"}]', 'Entrümpelung-Preise', 'Transparente Preisgestaltung ohne versteckte Kosten', 'Häufige Fragen', '', 'Entrümpelung & Entsorgung', 'Professionelle Entrümpelung von Wohnungen, Häusern und Gewerbeobjekten', '', '2025-09-16 12:03:26', '2025-10-14 18:47:58'),
(4, 4, 'Haushaltsauflösung', 'Komplette Haushaltsauflösung mit fachgerechter Entsorgung', 'Ihr zuverlässiger Partner für Auflösung', 'Komplette Haushaltsauflösung mit fachgerechter Entsorgung', 'Unsere Auflösung-Leistungen', 'Alles aus einer Hand für Ihren perfekten Service', '[]', 'So läuft Ihr Auflösung ab', 'In einfachen Schritten zu Ihrem Ziel', '[{"title":"Beratung","description":"Kostenlose Erstberatung und Bedarfsanalyse"},{"title":"Planung","description":"Detaillierte Planung des Vorgehens"},{"title":"Durchführung","description":"Professionelle Umsetzung durch unser Team"},{"title":"Abschluss","description":"Finale Kontrolle und Übergabe"}]', 'Auflösung-Preise', 'Transparente Preisgestaltung ohne versteckte Kosten', 'Häufige Fragen', '', 'Haushaltsauflösung', 'Komplette Haushaltsauflösung mit fachgerechter Entsorgung', '', '2025-09-16 12:03:40', '2025-09-16 12:03:40');

-- ============================================
-- Default User Permissions Data
-- ============================================

INSERT INTO user_permissions (id, role, permission_key, permission_value, created_at) VALUES
-- Admin Role (Full Access)
(128, 'Admin', 'admin_access', 1, '2025-10-03 17:48:27'),
(129, 'Admin', 'dashboard_view', 1, '2025-10-03 17:48:27'),
(130, 'Admin', 'services_view', 1, '2025-10-03 17:48:27'),
(131, 'Admin', 'services_manage', 1, '2025-10-03 17:48:27'),
(132, 'Admin', 'service_pages_view', 1, '2025-10-03 17:48:27'),
(133, 'Admin', 'service_pages_manage', 1, '2025-10-03 17:48:27'),
(134, 'Admin', 'media_view', 1, '2025-10-03 17:48:27'),
(135, 'Admin', 'media_manage', 1, '2025-10-03 17:48:27'),
(136, 'Admin', 'email_templates_view', 1, '2025-10-03 17:48:27'),
(137, 'Admin', 'email_templates_manage', 1, '2025-10-03 17:48:27'),
(138, 'Admin', 'questionnaires_view', 1, '2025-10-03 17:48:27'),
(139, 'Admin', 'questionnaires_manage', 1, '2025-10-03 17:48:27'),
(140, 'Admin', 'questions_view', 1, '2025-10-03 17:48:27'),
(141, 'Admin', 'questions_manage', 1, '2025-10-03 17:48:27'),
(142, 'Admin', 'submissions_view', 1, '2025-10-03 17:48:27'),
(143, 'Admin', 'submissions_manage', 1, '2025-10-03 17:48:27'),
(144, 'Admin', 'submission_archive_view', 1, '2025-10-03 17:48:27'),
(145, 'Admin', 'submission_archive_manage', 1, '2025-10-03 17:48:27'),
(146, 'Admin', 'users_view', 1, '2025-10-03 17:48:27'),
(147, 'Admin', 'users_manage', 1, '2025-10-03 17:48:27'),
(148, 'Admin', 'settings_view', 1, '2025-10-03 17:48:27'),
(149, 'Admin', 'settings_manage', 1, '2025-10-03 17:48:27'),
(220, 'Admin', 'email_inbox_view', 1, '2025-10-04 15:39:34'),
(222, 'Admin', 'email_inbox_manage', 1, '2025-10-04 15:45:46'),
(223, 'Admin', 'email_inbox_delete', 1, '2025-10-04 15:45:46'),
(224, 'Admin', 'email_settings_manage', 1, '2025-10-04 15:45:46'),
(385, 'Admin', 'logs_view', 1, '2025-10-14 15:35:17'),

-- Chef Role (Full Access)
(150, 'Chef', 'admin_access', 1, '2025-10-03 17:48:33'),
(151, 'Chef', 'dashboard_view', 1, '2025-10-03 17:48:33'),
(152, 'Chef', 'services_view', 1, '2025-10-03 17:48:33'),
(153, 'Chef', 'services_manage', 1, '2025-10-03 17:48:33'),
(154, 'Chef', 'service_pages_view', 1, '2025-10-03 17:48:33'),
(155, 'Chef', 'service_pages_manage', 1, '2025-10-03 17:48:33'),
(156, 'Chef', 'media_view', 1, '2025-10-03 17:48:33'),
(157, 'Chef', 'media_manage', 1, '2025-10-03 17:48:33'),
(158, 'Chef', 'email_templates_view', 1, '2025-10-03 17:48:33'),
(159, 'Chef', 'email_templates_manage', 1, '2025-10-03 17:48:33'),
(160, 'Chef', 'questionnaires_view', 1, '2025-10-03 17:48:33'),
(161, 'Chef', 'questionnaires_manage', 1, '2025-10-03 17:48:33'),
(162, 'Chef', 'questions_view', 1, '2025-10-03 17:48:33'),
(163, 'Chef', 'questions_manage', 1, '2025-10-03 17:48:33'),
(164, 'Chef', 'submissions_view', 1, '2025-10-03 17:48:33'),
(165, 'Chef', 'submissions_manage', 1, '2025-10-03 17:48:33'),
(166, 'Chef', 'submission_archive_view', 1, '2025-10-03 17:48:33'),
(167, 'Chef', 'submission_archive_manage', 1, '2025-10-03 17:48:33'),
(168, 'Chef', 'users_view', 1, '2025-10-03 17:48:33'),
(169, 'Chef', 'users_manage', 1, '2025-10-03 17:48:33'),
(170, 'Chef', 'settings_view', 1, '2025-10-03 17:48:33'),
(171, 'Chef', 'settings_manage', 1, '2025-10-03 17:48:33'),
(218, 'Chef', 'email_inbox_view', 1, '2025-10-04 15:37:49'),

-- Moderator Role (Limited Access)
(172, 'Moderator', 'admin_access', 1, '2025-10-03 17:48:37'),
(173, 'Moderator', 'dashboard_view', 1, '2025-10-03 17:48:37'),
(174, 'Moderator', 'services_view', 0, '2025-10-03 17:48:37'),
(175, 'Moderator', 'services_manage', 0, '2025-10-03 17:48:37'),
(176, 'Moderator', 'service_pages_view', 0, '2025-10-03 17:48:37'),
(177, 'Moderator', 'service_pages_manage', 0, '2025-10-03 17:48:37'),
(178, 'Moderator', 'media_view', 0, '2025-10-03 17:48:37'),
(179, 'Moderator', 'media_manage', 0, '2025-10-03 17:48:37'),
(180, 'Moderator', 'email_templates_view', 1, '2025-10-03 17:48:37'),
(181, 'Moderator', 'email_templates_manage', 1, '2025-10-03 17:48:37'),
(182, 'Moderator', 'questionnaires_view', 1, '2025-10-03 17:48:37'),
(183, 'Moderator', 'questionnaires_manage', 1, '2025-10-03 17:48:37'),
(184, 'Moderator', 'questions_view', 1, '2025-10-03 17:48:37'),
(185, 'Moderator', 'questions_manage', 1, '2025-10-03 17:48:37'),
(186, 'Moderator', 'submissions_view', 1, '2025-10-03 17:48:37'),
(187, 'Moderator', 'submissions_manage', 1, '2025-10-03 17:48:37'),
(188, 'Moderator', 'submission_archive_view', 1, '2025-10-03 17:48:37'),
(189, 'Moderator', 'submission_archive_manage', 0, '2025-10-03 17:48:37'),
(190, 'Moderator', 'users_view', 0, '2025-10-03 17:48:37'),
(191, 'Moderator', 'users_manage', 0, '2025-10-03 17:48:37'),
(192, 'Moderator', 'settings_view', 0, '2025-10-03 17:48:37'),
(193, 'Moderator', 'settings_manage', 0, '2025-10-03 17:48:37'),
(219, 'Moderator', 'email_inbox_view', 1, '2025-10-04 15:38:45'),
(226, 'Moderator', 'email_inbox_manage', 1, '2025-10-04 15:46:10'),
(227, 'Moderator', 'email_inbox_delete', 0, '2025-10-04 15:46:10'),
(228, 'Moderator', 'email_settings_manage', 0, '2025-10-04 15:46:10'),

-- Mitarbeiter Role (Basic Access)
(194, 'Mitarbeiter', 'admin_access', 1, '2025-10-03 17:48:41'),
(195, 'Mitarbeiter', 'dashboard_view', 1, '2025-10-03 17:48:41'),
(196, 'Mitarbeiter', 'services_view', 0, '2025-10-03 17:48:41'),
(197, 'Mitarbeiter', 'services_manage', 0, '2025-10-03 17:48:41'),
(198, 'Mitarbeiter', 'service_pages_view', 0, '2025-10-03 17:48:41'),
(199, 'Mitarbeiter', 'service_pages_manage', 0, '2025-10-03 17:48:41'),
(200, 'Mitarbeiter', 'media_view', 0, '2025-10-03 17:48:41'),
(201, 'Mitarbeiter', 'media_manage', 0, '2025-10-03 17:48:41'),
(202, 'Mitarbeiter', 'email_templates_view', 0, '2025-10-03 17:48:41'),
(203, 'Mitarbeiter', 'email_templates_manage', 0, '2025-10-03 17:48:41'),
(204, 'Mitarbeiter', 'questionnaires_view', 0, '2025-10-03 17:48:41'),
(205, 'Mitarbeiter', 'questionnaires_manage', 0, '2025-10-03 17:48:41'),
(206, 'Mitarbeiter', 'questions_view', 0, '2025-10-03 17:48:41'),
(207, 'Mitarbeiter', 'questions_manage', 0, '2025-10-03 17:48:41'),
(208, 'Mitarbeiter', 'submissions_view', 1, '2025-10-03 17:48:41'),
(209, 'Mitarbeiter', 'submissions_manage', 0, '2025-10-03 17:48:41'),
(210, 'Mitarbeiter', 'submission_archive_view', 0, '2025-10-03 17:48:41'),
(211, 'Mitarbeiter', 'submission_archive_manage', 0, '2025-10-03 17:48:41'),
(212, 'Mitarbeiter', 'users_view', 0, '2025-10-03 17:48:41'),
(213, 'Mitarbeiter', 'users_manage', 0, '2025-10-03 17:48:41'),
(214, 'Mitarbeiter', 'settings_view', 0, '2025-10-03 17:48:41'),
(215, 'Mitarbeiter', 'settings_manage', 0, '2025-10-03 17:48:41'),
(216, 'Mitarbeiter', 'email_inbox_view', 1, '2025-10-04 15:37:17'),
(230, 'Mitarbeiter', 'email_inbox_manage', 0, '2025-10-04 15:46:12'),
(231, 'Mitarbeiter', 'email_inbox_delete', 0, '2025-10-04 15:46:12'),
(232, 'Mitarbeiter', 'email_settings_manage', 0, '2025-10-04 15:46:12'),

-- Leseberechtigung Role (Read-only)
(263, 'Leseberechtigung', 'dashboard_view', 1, '2025-10-09 16:24:34'),
(264, 'Leseberechtigung', 'services_view', 1, '2025-10-09 16:24:34'),
(265, 'Leseberechtigung', 'services_manage', 0, '2025-10-09 16:24:34'),
(266, 'Leseberechtigung', 'submissions_view', 1, '2025-10-09 16:24:34'),
(267, 'Leseberechtigung', 'submissions_manage', 0, '2025-10-09 16:24:34'),
(268, 'Leseberechtigung', 'email_templates_view', 0, '2025-10-09 16:24:34'),
(269, 'Leseberechtigung', 'email_templates_manage', 0, '2025-10-09 16:24:34'),
(270, 'Leseberechtigung', 'users_manage', 0, '2025-10-09 16:24:34'),
(271, 'Leseberechtigung', 'settings_view', 0, '2025-10-09 16:24:34'),
(272, 'Leseberechtigung', 'settings_manage', 0, '2025-10-09 16:24:34'),

-- Administrator Role (Full System Access)
(369, 'Administrator', 'dashboard_view', 1, '2025-10-09 18:48:41'),
(370, 'Administrator', 'services_view', 1, '2025-10-09 18:48:41'),
(371, 'Administrator', 'services_manage', 1, '2025-10-09 18:48:41'),
(372, 'Administrator', 'submissions_view', 1, '2025-10-09 18:48:41'),
(373, 'Administrator', 'submissions_manage', 1, '2025-10-09 18:48:41'),
(374, 'Administrator', 'email_templates_view', 1, '2025-10-09 18:48:41'),
(375, 'Administrator', 'email_templates_manage', 1, '2025-10-09 18:48:41'),
(376, 'Administrator', 'settings_view', 1, '2025-10-09 18:48:41'),
(377, 'Administrator', 'settings_manage', 1, '2025-10-09 18:48:41'),
(378, 'Administrator', 'users_view', 1, '2025-10-09 18:48:41'),
(379, 'Administrator', 'users_manage', 1, '2025-10-09 18:48:41'),
(380, 'Administrator', 'questionnaires_view', 1, '2025-10-09 18:48:41'),
(381, 'Administrator', 'questionnaires_manage', 1, '2025-10-09 18:48:41'),
(382, 'Administrator', 'questions_view', 1, '2025-10-09 18:48:41'),
(383, 'Administrator', 'questions_manage', 1, '2025-10-09 18:48:41'),
(384, 'Administrator', 'inbox_view', 1, '2025-10-09 18:48:41');

-- =============================================
-- Default Email Template Categories Data
-- =============================================

INSERT INTO `email_template_categories` (`id`, `category_name`, `description`, `sort_order`, `created_at`) VALUES
(1, 'customer_communication', 'Kundenkommunikation - Allgemeine Nachrichten an Kunden', 1, '2025-10-04 10:48:24'),
(2, 'process_management', 'Prozessverwaltung - E-Mails für interne Abläufe', 2, '2025-10-04 10:48:24'),
(3, 'billing', 'Abrechnung - Rechnungen und Zahlungsaufforderungen', 3, '2025-10-04 10:48:24'),
(4, 'marketing', 'Marketing - Werbemails und Newsletter', 4, '2025-10-04 10:48:24'),
(5, 'support', 'Support - Technische Hilfe und Kundensupport', 5, '2025-10-04 10:48:24');

-- =============================================
-- Default Email Templates Data
-- =============================================

INSERT INTO `email_templates` (`id`, `subject`, `body_html`, `body_text`, `template_type`, `is_active`, `created_at`, `updated_at`, `template_key`, `variables`) VALUES
(2, 'Bestätigung Ihrer {{service_type}}-Anfrage - Referenz: {{reference}}', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #2c3e50; margin: 0;">{{company_name}}</h1>
                <p style="color: #7f8c8d; margin: 5px 0 0 0;">Ihr zuverlässiger Partner für alle Dienstleistungen</p>
            </div>
            
            <h2 style="color: #27ae60; margin-bottom: 20px;">Vielen Dank für Ihre Anfrage!</h2>
            
            <p>Liebe/r {{customer_name}},</p>
            
            <p>wir haben Ihre Anfrage bezüglich <strong>{{service_name}}</strong> erfolgreich erhalten und danken Ihnen für Ihr Vertrauen in unsere Dienstleistungen.</p>
            
            <div style="background-color: #ecf0f1; padding: 20px; border-radius: 6px; margin: 20px 0;">
                <h3 style="color: #2c3e50; margin-top: 0;">Ihre Anfrage im Überblick:</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;"><strong>Referenznummer:</strong></td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;">{{reference}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;"><strong>Service:</strong></td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;">{{service_name}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;"><strong>Gewünschter Termin:</strong></td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;">{{appointment_date}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0;"><strong>Eingangsdatum:</strong></td>
                        <td style="padding: 8px 0;">{{submitted_at}}</td>
                    </tr>
                </table>
            </div>
            
            <div style="background-color: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #27ae60; margin: 20px 0;">
                <h4 style="color: #27ae60; margin-top: 0;">Ihre Angaben:</h4>
                {{answers_html}}
            </div>
            
            <p><strong>Nächste Schritte:</strong><br>
            Ein Mitarbeiter unseres Teams wird sich innerhalb von <strong>24 Stunden</strong> bei Ihnen melden, um weitere Details zu besprechen und einen konkreten Termin zu vereinbaren.</p>
            
            <div style="background-color: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107; margin: 20px 0;">
                <p style="margin: 0;"><strong>Wichtiger Hinweis:</strong> Bitte halten Sie Ihre Referenznummer <strong>{{reference}}</strong> für Rückfragen bereit.</p>
            </div>
            
            <hr style="border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;">
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px;">
                <h4 style="color: #2c3e50; margin-top: 0;">Kontakt für Rückfragen:</h4>
                <p style="margin: 5px 0;"><strong>Telefon:</strong> {{company_phone}}</p>
                <p style="margin: 5px 0;"><strong>E-Mail:</strong> {{company_email}}</p>
                <p style="margin: 5px 0;"><strong>Website:</strong> {{company_website}}</p>
            </div>
            
            <p style="margin-top: 30px;">Mit freundlichen Grüßen<br>
            <strong>Ihr {{company_name}} Team</strong></p>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 12px;">
                <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht direkt auf diese E-Mail.</p>
            </div>
        </div>
    </div>', 'Liebe/r {{customer_name}},\n\nwir haben Ihre Anfrage bezüglich {{service_name}} erfolgreich erhalten und danken Ihnen für Ihr Vertrauen.\n\nIhre Anfrage im Überblick:\n- Referenznummer: {{reference}}\n- Service: {{service_name}}\n- Gewünschter Termin: {{appointment_date}}\n- Eingangsdatum: {{submitted_at}}\n\nIhre Angaben:\n{{answers_text}}\n\nNächste Schritte:\nEin Mitarbeiter unseres Teams wird sich innerhalb von 24 Stunden bei Ihnen melden.\n\nWichtiger Hinweis: Bitte halten Sie Ihre Referenznummer {{reference}} für Rückfragen bereit.\n\nKontakt für Rückfragen:\nTelefon: {{company_phone}}\nE-Mail: {{company_email}}\nWebsite: {{company_website}}\n\nMit freundlichen Grüßen\nIhr {{company_name}} Team', 'general', 1, '2025-10-04 10:47:24', '2025-10-04 10:47:24', 'request_confirmation', '["customer_name", "service_name", "service_type", "reference", "appointment_date", "submitted_at", "answers_html", "answers_text", "company_name", "company_phone", "company_email", "company_website"]'),
(3, 'Rückmeldung zu Ihrer {{service_type}}-Anfrage - Referenz: {{reference}}', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #2c3e50; margin: 0;">{{company_name}}</h1>
                <p style="color: #7f8c8d; margin: 5px 0 0 0;">Ihr zuverlässiger Partner für alle Dienstleistungen</p>
            </div>
            
            <h2 style="color: #e74c3c; margin-bottom: 20px;">Rückmeldung zu Ihrer Anfrage</h2>
            
            <p>Liebe/r {{customer_name}},</p>
            
            <p>vielen Dank für Ihre Anfrage bezüglich <strong>{{service_name}}</strong> mit der Referenznummer <strong>{{reference}}</strong>.</p>
            
            <div style="background-color: #fdf2f2; padding: 20px; border-radius: 6px; border-left: 4px solid #e74c3c; margin: 20px 0;">
                <p style="margin: 0;">Nach sorgfältiger Prüfung Ihrer Anfrage müssen wir Ihnen leider mitteilen, dass wir den gewünschten Auftrag zu den angegebenen Konditionen nicht ausführen können.</p>
            </div>
            
            <p><strong>Mögliche Gründe:</strong></p>
            <ul style="padding-left: 20px;">
                <li>Terminkapazitäten bereits ausgelastet</li>
                <li>Örtliche Gegebenheiten erschweren die Ausführung</li>
                <li>Aufwand übersteigt wirtschaftliche Grenzen</li>
                <li>Spezielle Anforderungen außerhalb unseres Leistungsspektrums</li>
            </ul>
            
            <div style="background-color: #e8f4fd; padding: 20px; border-radius: 6px; border-left: 4px solid #3498db; margin: 20px 0;">
                <h4 style="color: #2980b9; margin-top: 0;">Alternative Lösungen</h4>
                <p style="margin-bottom: 10px;">Gerne können Sie:</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Ihre Anfrage mit geänderten Parametern erneut stellen</li>
                    <li>Einen alternativen Zeitraum vorschlagen</li>
                    <li>Sich telefonisch über Anpassungsmöglichkeiten informieren</li>
                </ul>
            </div>
            
            <p>Wir bedauern, dass wir Ihnen diesmal nicht behilflich sein können, und hoffen auf Ihr Verständnis.</p>
            
            <hr style="border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;">
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px;">
                <h4 style="color: #2c3e50; margin-top: 0;">Kontakt für Rückfragen:</h4>
                <p style="margin: 5px 0;"><strong>Telefon:</strong> {{company_phone}}</p>
                <p style="margin: 5px 0;"><strong>E-Mail:</strong> {{company_email}}</p>
                <p style="margin: 5px 0;"><strong>Website:</strong> {{company_website}}</p>
            </div>
            
            <p style="margin-top: 30px;">Mit freundlichen Grüßen<br>
            <strong>Ihr {{company_name}} Team</strong></p>
        </div>
    </div>', 'Liebe/r {{customer_name}},\n\nvielen Dank für Ihre Anfrage bezüglich {{service_name}} mit der Referenznummer {{reference}}.\n\nNach sorgfältiger Prüfung Ihrer Anfrage müssen wir Ihnen leider mitteilen, dass wir den gewünschten Auftrag zu den angegebenen Konditionen nicht ausführen können.\n\nMögliche Gründe:\n- Terminkapazitäten bereits ausgelastet\n- Örtliche Gegebenheiten erschweren die Ausführung\n- Aufwand übersteigt wirtschaftliche Grenzen\n- Spezielle Anforderungen außerhalb unseres Leistungsspektrums\n\nAlternative Lösungen:\nGerne können Sie:\n- Ihre Anfrage mit geänderten Parametern erneut stellen\n- Einen alternativen Zeitraum vorschlagen\n- Sich telefonisch über Anpassungsmöglichkeiten informieren\n\nWir bedauern, dass wir Ihnen diesmal nicht behilflich sein können.\n\nKontakt für Rückfragen:\nTelefon: {{company_phone}}\nE-Mail: {{company_email}}\nWebsite: {{company_website}}\n\nMit freundlichen Grüßen\nIhr {{company_name}} Team', 'general', 1, '2025-10-04 10:47:35', '2025-10-04 10:47:35', 'request_declined', '["customer_name", "service_name", "service_type", "reference", "company_name", "company_phone", "company_email", "company_website"]'),
(4, 'Ihr Angebot für {{service_type}} - Referenz: {{reference}}', '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #2c3e50; margin: 0;">{{company_name}}</h1>
                <p style="color: #7f8c8d; margin: 5px 0 0 0;">Ihr zuverlässiger Partner für alle Dienstleistungen</p>
            </div>
            
            <h2 style="color: #27ae60; margin-bottom: 20px;">Ihr individuelles Angebot</h2>
            
            <p>Liebe/r {{customer_name}},</p>
            
            <p>vielen Dank für Ihr Interesse an unseren Dienstleistungen. Gerne übersenden wir Ihnen hiermit unser <strong>kostenloses und unverbindliches Angebot</strong> für Ihre {{service_name}}-Anfrage.</p>
            
            <div style="background-color: #e8f5e8; padding: 20px; border-radius: 6px; border-left: 4px solid #27ae60; margin: 20px 0;">
                <h3 style="color: #27ae60; margin-top: 0;">Angebot im Überblick:</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;"><strong>Angebotsnummer:</strong></td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;">{{quote_number}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;"><strong>Gesamtsumme:</strong></td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7; font-weight: bold; color: #27ae60;">{{total_price}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;"><strong>Gültig bis:</strong></td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #bdc3c7;">{{quote_valid_until}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0;"><strong>Geplante Ausführung:</strong></td>
                        <td style="padding: 8px 0;">{{execution_date}}</td>
                    </tr>
                </table>
            </div>
            
            <div style="background-color: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107; margin: 20px 0;">
                <p style="margin: 0;"><strong>📎 Wichtig:</strong> Das detaillierte Angebot finden Sie als <strong>PDF-Datei im Anhang</strong> dieser E-Mail.</p>
            </div>
            
            <h4 style="color: #2c3e50;">Nächste Schritte:</h4>
            <ol style="padding-left: 20px;">
                <li><strong>Angebot prüfen:</strong> Schauen Sie sich das PDF-Angebot in Ruhe an</li>
                <li><strong>Rückfragen:</strong> Kontaktieren Sie uns bei Fragen gerne telefonisch</li>
                <li><strong>Beauftragung:</strong> Teilen Sie uns Ihre Entscheidung mit</li>
                <li><strong>Terminvereinbarung:</strong> Nach Auftragserteilung vereinbaren wir den konkreten Ausführungstermin</li>
            </ol>
            
            <div style="background-color: #e3f2fd; padding: 20px; border-radius: 6px; border-left: 4px solid #2196f3; margin: 20px 0;">
                <h4 style="color: #1976d2; margin-top: 0;">Warum {{company_name}}?</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>✓ Faire und transparente Preise</li>
                    <li>✓ Erfahrenes und zuverlässiges Team</li>
                    <li>✓ Vollständig versichert</li>
                    <li>✓ Pünktliche und professionelle Ausführung</li>
                    <li>✓ Zufriedenheitsgarantie</li>
                </ul>
            </div>
            
            <p>Wir freuen uns auf Ihre Rückmeldung und hoffen, Sie bald als Kunden begrüßen zu dürfen!</p>
            
            <hr style="border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;">
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px;">
                <h4 style="color: #2c3e50; margin-top: 0;">Kontakt für Rückfragen:</h4>
                <p style="margin: 5px 0;"><strong>Telefon:</strong> {{company_phone}}</p>
                <p style="margin: 5px 0;"><strong>E-Mail:</strong> {{company_email}}</p>
                <p style="margin: 5px 0;"><strong>Website:</strong> {{company_website}}</p>
                <p style="margin: 5px 0;"><strong>Ansprechpartner:</strong> {{assigned_user_name}}</p>
            </div>
            
            <p style="margin-top: 30px;">Mit freundlichen Grüßen<br>
            <strong>{{assigned_user_name}}<br>{{company_name}}</strong></p>
        </div>
    </div>', 'Liebe/r {{customer_name}},\n\nvielen Dank für Ihr Interesse an unseren Dienstleistungen. Gerne übersenden wir Ihnen hiermit unser kostenloses und unverbindliches Angebot für Ihre {{service_name}}-Anfrage.\n\nAngebot im Überblick:\n- Angebotsnummer: {{quote_number}}\n- Gesamtsumme: {{total_price}}\n- Gültig bis: {{quote_valid_until}}\n- Geplante Ausführung: {{execution_date}}\n\nWichtig: Das detaillierte Angebot finden Sie als PDF-Datei im Anhang dieser E-Mail.\n\nNächste Schritte:\n1. Angebot prüfen: Schauen Sie sich das PDF-Angebot in Ruhe an\n2. Rückfragen: Kontaktieren Sie uns bei Fragen gerne telefonisch\n3. Beauftragung: Teilen Sie uns Ihre Entscheidung mit\n4. Terminvereinbarung: Nach Auftragserteilung vereinbaren wir den konkreten Ausführungstermin\n\nWarum {{company_name}}?\n✓ Faire und transparente Preise\n✓ Erfahrenes und zuverlässiges Team\n✓ Vollständig versichert\n✓ Pünktliche und professionelle Ausführung\n✓ Zufriedenheitsgarantie\n\nWir freuen uns auf Ihre Rückmeldung!\n\nKontakt für Rückfragen:\nTelefon: {{company_phone}}\nE-Mail: {{company_email}}\nWebsite: {{company_website}}\nAnsprechpartner: {{assigned_user_name}}\n\nMit freundlichen Grüßen\n{{assigned_user_name}}\n{{company_name}}', 'general', 1, '2025-10-04 10:47:40', '2025-10-04 10:47:40', 'quote_delivery', '["customer_name", "service_name", "service_type", "reference", "quote_number", "total_price", "quote_valid_until", "execution_date", "company_name", "company_phone", "company_email", "company_website", "assigned_user_name"]'),
(5, 'Besichtigungstermin für Ihre {{service_type}}-Anfrage - Referenz: {{reference}}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\">
        <div style=\"background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);\">
            <div style=\"text-align: center; margin-bottom: 30px;\">
                <h1 style=\"color: #2c3e50; margin: 0;\">{{company_name}}</h1>
                <p style=\"color: #7f8c8d; margin: 5px 0 0 0;\">Ihr zuverlässiger Partner für alle Dienstleistungen</p>
            </div>
            
            <h2 style=\"color: #3498db; margin-bottom: 20px;\">Besichtigungstermin erforderlich</h2>
            
            <p>Liebe/r {{customer_name}},</p>
            
            <p>vielen Dank für Ihre Anfrage bezüglich <strong>{{service_name}}</strong>. Nach Prüfung Ihrer Angaben benötigen wir für ein präzises Angebot eine <strong>Vor-Ort-Besichtigung</strong>.</p>
            
            <div style=\"background-color: #e3f2fd; padding: 20px; border-radius: 6px; border-left: 4px solid #2196f3; margin: 20px 0;\">
                <h3 style=\"color: #1976d2; margin-top: 0;\">Warum eine Besichtigung?</h3>
                <ul style=\"margin: 0; padding-left: 20px;\">
                    <li>📏 Genaue Einschätzung des Aufwands</li>
                    <li>📐 Vermessung der örtlichen Gegebenheiten</li>
                    <li>🚪 Prüfung der Zugangsmöglichkeiten</li>
                    <li>🔍 Identifikation besonderer Anforderungen</li>
                    <li>💰 Exakte Kostenberechnung für Ihr Projekt</li>
                </ul>
            </div>
            
            <div style=\"background-color: #fff3cd; padding: 20px; border-radius: 6px; border-left: 4px solid #ffc107; margin: 20px 0;\">
                <h4 style=\"color: #b8860b; margin-top: 0;\">📅 Terminvorschläge</h4>
                <p style=\"margin-bottom: 10px;\">Bitte teilen Sie uns mit, welcher Zeitraum für Sie am besten geeignet ist:</p>
                <table style=\"width: 100%; border-collapse: collapse; margin-top: 15px;\">
                    <tr style=\"background-color: #fefefe;\">
                        <td style=\"padding: 10px; border: 1px solid #ddd; font-weight: bold;\">Wochentag</td>
                        <td style=\"padding: 10px; border: 1px solid #ddd; font-weight: bold;\">Uhrzeiten</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px; border: 1px solid #ddd;\">Montag - Freitag</td>
                        <td style=\"padding: 8px; border: 1px solid #ddd;\">08:00 - 17:00 Uhr</td>
                    </tr>
                    <tr style=\"background-color: #fefefe;\">
                        <td style=\"padding: 8px; border: 1px solid #ddd;\">Samstag</td>
                        <td style=\"padding: 8px; border: 1px solid #ddd;\">09:00 - 15:00 Uhr</td>
                    </tr>
                </table>
            </div>
            
            <div style=\"background-color: #f0f9ff; padding: 20px; border-radius: 6px; margin: 20px 0;\">
                <h4 style=\"color: #2c3e50; margin-top: 0;\">Ablauf der Besichtigung:</h4>
                <ol style=\"margin: 0; padding-left: 20px;\">
                    <li><strong>Terminvereinbarung</strong> - Anruf zur Bestätigung</li>
                    <li><strong>Vor-Ort-Termin</strong> - Dauer ca. 30-60 Minuten</li>
                    <li><strong>Beratung</strong> - Kostenlose Fachberatung inklusive</li>
                    <li><strong>Angebotserstellung</strong> - Binnen 24-48 Stunden</li>
                    <li><strong>Angebotszusendung</strong> - Per E-Mail als PDF</li>
                </ol>
            </div>
            
            <div style=\"background-color: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #4caf50; margin: 20px 0;\">
                <p style=\"margin: 0;\"><strong>✅ Kostenlos & Unverbindlich:</strong> Die Besichtigung und Angebotserstellung sind für Sie selbstverständlich kostenlos und unverbindlich.</p>
            </div>
            
            <p><strong>So erreichen Sie uns für die Terminvereinbarung:</strong></p>
            
            <div style=\"background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0;\">
                <div style=\"display: flex; align-items: center; margin-bottom: 15px;\">
                    <div style=\"background-color: #4caf50; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-weight: bold;\">📞</div>
                    <div>
                        <p style=\"margin: 0; font-weight: bold;\">Telefonisch (bevorzugt):</p>
                        <p style=\"margin: 0; color: #2c3e50; font-size: 18px;\"><strong>{{company_phone}}</strong></p>
                    </div>
                </div>
                <div style=\"display: flex; align-items: center;\">
                    <div style=\"background-color: #2196f3; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-weight: bold;\">✉️</div>
                    <div>
                        <p style=\"margin: 0; font-weight: bold;\">Per E-Mail:</p>
                        <p style=\"margin: 0; color: #2c3e50;\">{{company_email}}</p>
                    </div>
                </div>
            </div>
            
            <p>Wir freuen uns darauf, Sie persönlich kennenzulernen und Ihr Projekt gemeinsam zu planen!</p>
            
            <p style=\"margin-top: 30px;\">Mit freundlichen Grüßen<br>
            <strong>{{assigned_user_name}}<br>{{company_name}}</strong></p>
            
            <div style=\"text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 12px;\">
                <p>Referenznummer: {{reference}} | Anfrage vom: {{submitted_at}}</p>
            </div>
        </div>
    </div>', 'Liebe/r {{customer_name}},\n\n\n\nvielen Dank für Ihre Anfrage bezüglich {{service_name}}. Nach Prüfung Ihrer Angaben benötigen wir für ein präzises Angebot eine Vor-Ort-Besichtigung.\n\n\n\nWarum eine Besichtigung?\n\n📏 Genaue Einschätzung des Aufwands\n\n📐 Vermessung der örtlichen Gegebenheiten\n\n🚪 Prüfung der Zugangsmöglichkeiten\n\n🔍 Identifikation besonderer Anforderungen\n\n💰 Exakte Kostenberechnung für Ihr Projekt\n\n\n\nTerminvorschläge:\n\nBitte teilen Sie uns mit, welcher Zeitraum für Sie am besten geeignet ist:\n\n\n\nMontag - Freitag: 08:00 - 17:00 Uhr\n\nSamstag: 09:00 - 15:00 Uhr\n\n\n\nAblauf der Besichtigung:\n\n1. Terminvereinbarung - Anruf zur Bestätigung\n\n2. Vor-Ort-Termin - Dauer ca. 30-60 Minuten\n\n3. Beratung - Kostenlose Fachberatung inklusive\n\n4. Angebotserstellung - Binnen 24-48 Stunden\n\n5. Angebotszusendung - Per E-Mail als PDF\n\n\n\n✅ Kostenlos & Unverbindlich: Die Besichtigung und Angebotserstellung sind für Sie selbstverständlich kostenlos und unverbindlich.\n\n\n\nSo erreichen Sie uns für die Terminvereinbarung:\n\n📞 Telefonisch (bevorzugt): {{company_phone}}\n\n✉️ Per E-Mail: {{company_email}}\n\n\n\nWir freuen uns darauf, Sie persönlich kennenzulernen!\n\n\n\nMit freundlichen Grüßen\n\n{{assigned_user_name}}\n\n{{company_name}}\n\n\n\nReferenznummer: {{reference}} | Anfrage vom: {{submitted_at}}', 'general', 1, '2025-10-04 10:47:53', '2025-10-04 10:47:53', 'site_visit_request', '[\"customer_name\", \"service_name\", \"service_type\", \"reference\", \"submitted_at\", \"company_name\", \"company_phone\", \"company_email\", \"assigned_user_name\"]'),
(6, 'Abschluss Ihres {{service_type}}-Auftrags - Rechnung {{invoice_number}}', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\">
        <div style=\"background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);\">
            <div style=\"text-align: center; margin-bottom: 30px;\">
                <h1 style=\"color: #2c3e50; margin: 0;\">{{company_name}}</h1>
                <p style=\"color: #7f8c8d; margin: 5px 0 0 0;\">Ihr zuverlässiger Partner für alle Dienstleistungen</p>
            </div>
            
            <h2 style=\"color: #27ae60; margin-bottom: 20px;\">✅ Auftrag erfolgreich abgeschlossen!</h2>
            
            <p>Liebe/r {{customer_name}},</p>
            
            <p>wir freuen uns, Ihnen mitteilen zu können, dass Ihr <strong>{{service_name}}-Auftrag</strong> erfolgreich abgeschlossen wurde!</p>
            
            <div style=\"background-color: #e8f5e8; padding: 20px; border-radius: 6px; border-left: 4px solid #27ae60; margin: 20px 0;\">
                <h3 style=\"color: #27ae60; margin-top: 0;\">Auftrag im Überblick:</h3>
                <table style=\"width: 100%; border-collapse: collapse;\">
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #bdc3c7;\"><strong>Auftragsnummer:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #bdc3c7;\">{{reference}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #bdc3c7;\"><strong>Ausführungsdatum:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #bdc3c7;\">{{completion_date}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #bdc3c7;\"><strong>Durchführende Team:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #bdc3c7;\">{{team_size}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0;\"><strong>Dauer:</strong></td>
                        <td style=\"padding: 8px 0;\">{{estimated_duration}}</td>
                    </tr>
                </table>
            </div>
            
            <div style=\"background-color: #f0f9ff; padding: 20px; border-radius: 6px; margin: 20px 0;\">
                <h4 style=\"color: #2c3e50; margin-top: 0;\">Zusammenfassung der durchgeführten Arbeiten:</h4>
                <p style=\"margin: 10px 0;\">{{service_summary}}</p>
            </div>
            
            <div style=\"background-color: #fff3cd; padding: 20px; border-radius: 6px; border-left: 4px solid #ffc107; margin: 20px 0;\">
                <h3 style=\"color: #b8860b; margin-top: 0;\">🧾 Rechnung</h3>
                <p style=\"margin-bottom: 15px;\">Anbei erhalten Sie die <strong>Rechnung</strong> für die erbrachten Leistungen:</p>
                <table style=\"width: 100%; border-collapse: collapse;\">
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Rechnungsnummer:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{invoice_number}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Rechnungsdatum:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{current_date}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Gesamtbetrag:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd; font-weight: bold; color: #27ae60; font-size: 16px;\">{{total_price}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0;\"><strong>Zahlungsziel:</strong></td>
                        <td style=\"padding: 8px 0;\">14 Tage ohne Abzug</td>
                    </tr>
                </table>
                <p style=\"margin: 15px 0 0 0;\"><strong>📎 Die detaillierte Rechnung finden Sie als PDF im Anhang.</strong></p>
            </div>
            
            <div style=\"background-color: #e3f2fd; padding: 20px; border-radius: 6px; border-left: 4px solid #2196f3; margin: 20px 0;\">
                <h4 style=\"color: #1976d2; margin-top: 0;\">💳 Zahlungsmöglichkeiten</h4>
                <ul style=\"margin: 0; padding-left: 20px;\">
                    <li><strong>Banküberweisung:</strong> Verwendung der Rechnungsnummer als Verwendungszweck</li>
                    <li><strong>PayPal:</strong> Zahlung über unsere E-Mail {{company_email}}</li>
                    <li><strong>EC-Karte:</strong> Bei Abholung vor Ort möglich</li>
                    <li><strong>Bar:</strong> Bei Abholung vor Ort möglich</li>
                </ul>
            </div>
            
            <div style=\"background-color: #f8f5f0; padding: 20px; border-radius: 6px; border-left: 4px solid #ff9800; margin: 20px 0;\">
                <h4 style=\"color: #e65100; margin-top: 0;\">⭐ Ihre Meinung ist uns wichtig!</h4>
                <p style=\"margin-bottom: 10px;\">Wir würden uns sehr über Ihr Feedback freuen:</p>
                <ul style=\"margin: 0; padding-left: 20px;\">
                    <li>🌟 <strong>Google-Bewertung:</strong> <a href=\"{{review_link_google}}\" style=\"color: #1976d2;\">Bewerten Sie uns auf Google</a></li>
                    <li>💬 <strong>Direktes Feedback:</strong> Antworten Sie einfach auf diese E-Mail</li>
                    <li>📞 <strong>Telefonisch:</strong> {{company_phone}}</li>
                </ul>
            </div>
            
            <div style=\"background-color: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #4caf50; margin: 20px 0;\">
                <p style=\"margin: 0;\"><strong>🔄 Weitere Aufträge:</strong> Gerne stehen wir Ihnen auch in Zukunft für weitere Projekte zur Verfügung. Sprechen Sie uns jederzeit an!</p>
            </div>
            
            <p>Vielen Dank für Ihr Vertrauen und die angenehme Zusammenarbeit!</p>
            
            <hr style=\"border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;\">
            
            <div style=\"background-color: #f8f9fa; padding: 20px; border-radius: 6px;\">
                <h4 style=\"color: #2c3e50; margin-top: 0;\">Kontakt bei Fragen:</h4>
                <p style=\"margin: 5px 0;\"><strong>Telefon:</strong> {{company_phone}}</p>
                <p style=\"margin: 5px 0;\"><strong>E-Mail:</strong> {{company_email}}</p>
                <p style=\"margin: 5px 0;\"><strong>Website:</strong> {{company_website}}</p>
                <p style=\"margin: 5px 0;\"><strong>Ansprechpartner:</strong> {{assigned_user_name}}</p>
            </div>
            
            <p style=\"margin-top: 30px;\">Mit freundlichen Grüßen<br>
            <strong>{{assigned_user_name}}<br>{{company_name}}</strong></p>
            
            <div style=\"text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 12px;\">
                <p>Vielen Dank, dass Sie sich für {{company_name}} entschieden haben!</p>
            </div>
        </div>
    </div>', 'Liebe/r {{customer_name}},\n\n\n\nwir freuen uns, Ihnen mitteilen zu können, dass Ihr {{service_name}}-Auftrag erfolgreich abgeschlossen wurde!\n\n\n\nAuftrag im Überblick:\n\n- Auftragsnummer: {{reference}}\n\n- Ausführungsdatum: {{completion_date}}\n\n- Durchführende Team: {{team_size}}\n\n- Dauer: {{estimated_duration}}\n\n\n\nZusammenfassung der durchgeführten Arbeiten:\n\n{{service_summary}}\n\n\n\n🧾 Rechnung:\n\nAnbei erhalten Sie die Rechnung für die erbrachten Leistungen:\n\n\n\n- Rechnungsnummer: {{invoice_number}}\n\n- Rechnungsdatum: {{current_date}}\n\n- Gesamtbetrag: {{total_price}}\n\n- Zahlungsziel: 14 Tage ohne Abzug\n\n\n\n📎 Die detaillierte Rechnung finden Sie als PDF im Anhang.\n\n\n\n💳 Zahlungsmöglichkeiten:\n\n- Banküberweisung: Verwendung der Rechnungsnummer als Verwendungszweck\n\n- PayPal: Zahlung über unsere E-Mail {{company_email}}\n\n- EC-Karte: Bei Abholung vor Ort möglich\n\n- Bar: Bei Abholung vor Ort möglich\n\n\n\n⭐ Ihre Meinung ist uns wichtig!\n\nWir würden uns sehr über Ihr Feedback freuen:\n\n- Google-Bewertung: {{review_link_google}}\n\n- Direktes Feedback: Antworten Sie einfach auf diese E-Mail\n\n- Telefonisch: {{company_phone}}\n\n\n\n🔄 Weitere Aufträge: Gerne stehen wir Ihnen auch in Zukunft für weitere Projekte zur Verfügung.\n\n\n\nVielen Dank für Ihr Vertrauen und die angenehme Zusammenarbeit!\n\n\n\nKontakt bei Fragen:\n\nTelefon: {{company_phone}}\n\nE-Mail: {{company_email}}\n\nWebsite: {{company_website}}\n\nAnsprechpartner: {{assigned_user_name}}\n\n\n\nMit freundlichen Grüßen\n\n{{assigned_user_name}}\n\n{{company_name}}', 'general', 1, '2025-10-04 10:47:59', '2025-10-04 10:47:59', 'completion_invoice', '[\"customer_name\", \"service_name\", \"service_type\", \"reference\", \"completion_date\", \"team_size\", \"estimated_duration\", \"service_summary\", \"invoice_number\", \"current_date\", \"total_price\", \"review_link_google\", \"company_name\", \"company_phone\", \"company_email\", \"company_website\", \"assigned_user_name\"]'),
(7, 'Empfangsbestätigung - Ihre {{service_type}}-Anfrage wurde erhalten', '<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\">
        <div style=\"background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);\">
            <div style=\"text-align: center; margin-bottom: 30px;\">
                <h1 style=\"color: #2c3e50; margin: 0;\">{{company_name}}</h1>
                <p style=\"color: #7f8c8d; margin: 5px 0 0 0;\">Ihr zuverlässiger Partner für alle Dienstleistungen</p>
            </div>
            
            <div style=\"background-color: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 30px;\">
                <h2 style=\"margin: 0; font-size: 24px;\">✅ Anfrage erfolgreich eingegangen!</h2>
                <p style=\"margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;\">Vielen Dank für Ihr Vertrauen</p>
            </div>
            
            <p>Liebe/r {{customer_name}},</p>
            
            <p>wir haben Ihre <strong>{{service_name}}-Anfrage</strong> soeben erhalten und bestätigen hiermit den erfolgreichen Eingang.</p>
            
            <div style=\"background-color: #e8f4fd; padding: 20px; border-radius: 6px; border-left: 4px solid #2196f3; margin: 20px 0;\">
                <h3 style=\"color: #1976d2; margin-top: 0;\">📋 Ihre Anfrage-Details:</h3>
                <table style=\"width: 100%; border-collapse: collapse;\">
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Referenznummer:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd; font-weight: bold; color: #2196f3;\">{{reference}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Service:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{service_name}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Eingangsdatum:</strong></td>
                        <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{submitted_at}}</td>
                    </tr>
                    <tr>
                        <td style=\"padding: 8px 0;\"><strong>Gewünschter Termin:</strong></td>
                        <td style=\"padding: 8px 0;\">{{appointment_date}}</td>
                    </tr>
                </table>
            </div>
            
            <div style=\"background-color: #fff3cd; padding: 20px; border-radius: 6px; border-left: 4px solid #ffc107; margin: 20px 0;\">
                <h3 style=\"color: #b8860b; margin-top: 0;\">🔄 Wie geht es weiter?</h3>
                <ol style=\"margin: 0; padding-left: 20px;\">
                    <li><strong>Automatische Bestätigung</strong> - Diese E-Mail (✅ erhalten)</li>
                    <li><strong>Bearbeitung</strong> - Ein Mitarbeiter prüft Ihre Anfrage</li>
                    <li><strong>Persönlicher Kontakt</strong> - Anruf durch unseren Mitarbeiter</li>
                    <li><strong>Beratung & Angebot</strong> - Kostenloses und unverbindliches Angebot</li>
                </ol>
            </div>
            
            <div style=\"background-color: #e8f5e8; padding: 20px; border-radius: 6px; border-left: 4px solid #4caf50; margin: 20px 0;\">
                <h3 style=\"color: #2e7d32; margin-top: 0;\">🤝 Unser Versprechen:</h3>
                <p style=\"margin: 10px 0;\"><strong>Ein Mitarbeiter unseres Teams wird sich in Kürze zu unseren Geschäftszeiten bei Ihnen melden.</strong></p>
                
                <div style=\"background-color: #f1f8e9; padding: 15px; border-radius: 4px; margin-top: 15px;\">
                    <h4 style=\"color: #2e7d32; margin: 0 0 10px 0;\">🕐 Unsere Geschäftszeiten:</h4>
                    {{business_hours}}
                </div>
                
                <p style=\"margin: 15px 0 5px 0; font-size: 14px; color: #555;\">
                    <strong>⚡ Dringend?</strong> Bei eiligen Anfragen kontaktieren Sie uns direkt telefonisch.
                </p>
            </div>
            
            <div style=\"background-color: #f0f9ff; padding: 20px; border-radius: 6px; margin: 20px 0;\">
                <h3 style=\"color: #2c3e50; margin-top: 0;\">📌 Wichtige Hinweise:</h3>
                <ul style=\"margin: 0; padding-left: 20px;\">
                    <li><strong>Referenznummer notieren:</strong> <span style=\"background-color: #e3f2fd; padding: 2px 6px; border-radius: 3px; font-weight: bold;\">{{reference}}</span></li>
                    <li><strong>E-Mail aufbewahren:</strong> Für Ihre Unterlagen</li>
                    <li><strong>Änderungen möglich:</strong> Teilen Sie uns Anpassungen gerne mit</li>
                    <li><strong>Kostenlos & unverbindlich:</strong> Beratung und Kostenvoranschlag</li>
                </ul>
            </div>
            
            <hr style=\"border: none; border-top: 1px solid #ecf0f1; margin: 30px 0;\">
            
            <div style=\"background-color: #f8f9fa; padding: 20px; border-radius: 6px;\">
                <h4 style=\"color: #2c3e50; margin-top: 0;\">📞 Direktkontakt (für dringende Fälle):</h4>
                <div style=\"display: flex; flex-wrap: wrap; gap: 20px;\">
                    <div style=\"flex: 1; min-width: 200px;\">
                        <p style=\"margin: 5px 0; font-weight: bold; color: #4caf50;\">📱 Telefon:</p>
                        <p style=\"margin: 0; font-size: 18px; color: #2c3e50;\"><strong>{{company_phone}}</strong></p>
                    </div>
                    <div style=\"flex: 1; min-width: 200px;\">
                        <p style=\"margin: 5px 0; font-weight: bold; color: #2196f3;\">✉️ E-Mail:</p>
                        <p style=\"margin: 0; color: #2c3e50;\">{{company_email}}</p>
                    </div>
                </div>
                <p style=\"margin: 15px 0 5px 0; color: #666; font-size: 14px;\">
                    🌐 Website: {{company_website}}
                </p>
            </div>
            
            <div style=\"background-color: #e1f5fe; padding: 15px; border-radius: 6px; margin: 20px 0; text-align: center;\">
                <p style=\"margin: 0; color: #0277bd; font-weight: bold;\">
                    💙 Vielen Dank für Ihr Vertrauen in {{company_name}}!
                </p>
                <p style=\"margin: 5px 0 0 0; color: #0288d1; font-size: 14px;\">
                    Wir freuen uns darauf, Ihnen helfen zu können.
                </p>
            </div>
            
            <p style=\"margin-top: 30px;\">Mit freundlichen Grüßen<br>
            <strong>Ihr {{company_name}} Team</strong></p>
            
            <div style=\"text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 12px;\">
                <p><strong>🤖 Diese E-Mail wurde automatisch generiert</strong></p>
                <p>Bitte antworten Sie nicht direkt auf diese E-Mail.<br>
                Für Rückfragen nutzen Sie bitte die oben angegebenen Kontaktdaten.</p>
            </div>
        </div>
    </div>', '', 'general', 1, '2025-10-04 11:10:39', '2025-10-04 14:30:02', 'auto_receipt_confirmation', '[]'),
(8, '🔔 Neue {{service_type}}-Anfrage eingegangen - {{reference}}', '<div style=\"font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;\">
    <div style=\"background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);\">
        
        <!-- Header -->
        <div style=\"background-color: #2196f3; color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 30px;\">
            <h2 style=\"margin: 0; font-size: 24px;\">🔔 Neue Kundenanfrage</h2>
            <p style=\"margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;\">Eingegangen am {{submitted_at}}</p>
        </div>
        
        <!-- Alert Box -->
        <div style=\"background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;\">
            <p style=\"margin: 0; color: #856404; font-weight: bold;\">⚡ Bitte zeitnah bearbeiten und Kunde kontaktieren!</p>
        </div>
        
        <!-- Reference & Service -->
        <div style=\"background-color: #e3f2fd; padding: 20px; border-radius: 6px; border-left: 4px solid #2196f3; margin-bottom: 20px;\">
            <h3 style=\"color: #1976d2; margin-top: 0;\">📋 Anfrage-Details</h3>
            <table style=\"width: 100%; border-collapse: collapse;\">
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd; width: 40%;\"><strong>Referenznummer:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd; font-weight: bold; color: #2196f3; font-size: 16px;\">{{reference}}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Service-Art:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{service_type}}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Service-Name:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{service_name}}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\"><strong>Eingangsdatum:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #ddd;\">{{submitted_at}}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0;\"><strong>Submission ID:</strong></td>
                    <td style=\"padding: 8px 0; font-family: monospace;\">{{submission_id}}</td>
                </tr>
            </table>
        </div>
        
        <!-- Customer Information -->
        <div style=\"background-color: #e8f5e9; padding: 20px; border-radius: 6px; border-left: 4px solid #4caf50; margin-bottom: 20px;\">
            <h3 style=\"color: #2e7d32; margin-top: 0;\">👤 Kundendaten</h3>
            <table style=\"width: 100%; border-collapse: collapse;\">
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #c8e6c9; width: 40%;\"><strong>Name:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #c8e6c9; font-size: 16px;\">{{customer_name}}</td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #c8e6c9;\"><strong>📧 E-Mail:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #c8e6c9;\"><a href=\"mailto:{{customer_email}}\" style=\"color: #2196f3;\">{{customer_email}}</a></td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #c8e6c9;\"><strong>📱 Telefon:</strong></td>
                    <td style=\"padding: 8px 0; border-bottom: 1px solid #c8e6c9;\"><a href=\"tel:{{customer_phone}}\" style=\"color: #2196f3;\">{{customer_phone}}</a></td>
                </tr>
                <tr>
                    <td style=\"padding: 8px 0;\"><strong>🗓️ Wunschtermin:</strong></td>
                    <td style=\"padding: 8px 0;\">{{appointment_date}}</td>
                </tr>
            </table>
        </div>
        
        <!-- Additional Details -->
        <div style=\"background-color: #f3e5f5; padding: 20px; border-radius: 6px; border-left: 4px solid #9c27b0; margin-bottom: 20px;\">
            <h3 style=\"color: #7b1fa2; margin-top: 0;\">📝 Weitere Details</h3>
            <div style=\"background-color: white; padding: 15px; border-radius: 4px; margin-top: 10px;\">
                <p style=\"margin: 0; color: #666; white-space: pre-wrap;\">{{additional_details}}</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style=\"background-color: #fafafa; padding: 20px; border-radius: 6px; text-align: center; margin-bottom: 20px;\">
            <h3 style=\"color: #2c3e50; margin-top: 0;\">⚡ Nächste Schritte</h3>
            <div style=\"margin: 20px 0;\">
                <a href=\"{{admin_url}}/submissions/view/{{submission_id}}\" style=\"display: inline-block; background-color: #2196f3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold;\">📄 Anfrage anzeigen</a>
                <a href=\"mailto:{{customer_email}}\" style=\"display: inline-block; background-color: #4caf50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold;\">✉️ Kunde kontaktieren</a>
                <a href=\"tel:{{customer_phone}}\" style=\"display: inline-block; background-color: #ff9800; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 5px; font-weight: bold;\">📞 Anrufen</a>
            </div>
        </div>
        
        <!-- Checklist -->
        <div style=\"background-color: #fff; border: 2px solid #2196f3; padding: 20px; border-radius: 6px;\">
            <h3 style=\"color: #2196f3; margin-top: 0;\">✅ Bearbeitungs-Checkliste</h3>
            <ul style=\"list-style: none; padding-left: 0; margin: 0;\">
                <li style=\"padding: 8px 0; border-bottom: 1px solid #e0e0e0;\">☐ Anfrage in System erfasst/überprüft</li>
                <li style=\"padding: 8px 0; border-bottom: 1px solid #e0e0e0;\">☐ Kunde telefonisch kontaktiert</li>
                <li style=\"padding: 8px 0; border-bottom: 1px solid #e0e0e0;\">☐ Besichtigungstermin vereinbart (falls nötig)</li>
                <li style=\"padding: 8px 0; border-bottom: 1px solid #e0e0e0;\">☐ Kostenvoranschlag erstellt</li>
                <li style=\"padding: 8px 0;\">☐ Bestätigung an Kunde versendet</li>
            </ul>
        </div>
        
        <!-- Footer -->
        <div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;\">
            <p><strong>⚙️ Automatisch generierte Team-Benachrichtigung</strong></p>
            <p>Diese E-Mail wurde automatisch vom {{company_name}} Anfrage-System erstellt.<br>
            Zeitstempel: {{submitted_at}}</p>
        </div>
        
    </div>
</div>', '=================================================\nNEUE KUNDENANFRAGE EINGEGANGEN\n=================================================\n\n\n\nREFERENZ: {{reference}}\nService: {{service_type}} - {{service_name}}\nEingegangen: {{submitted_at}}\n\n\n\n-------------------------------------------------\nKUNDENDATEN\n-------------------------------------------------\nName: {{customer_name}}\nE-Mail: {{customer_email}}\nTelefon: {{customer_phone}}\nWunschtermin: {{appointment_date}}\n\n\n\n-------------------------------------------------\nWEITERE DETAILS\n-------------------------------------------------\n{{additional_details}}\n\n\n\n-------------------------------------------------\nNÄCHSTE SCHRITTE\n-------------------------------------------------\n1. Anfrage im System anzeigen: {{admin_url}}/submissions/view/{{submission_id}}\n2. Kunde zeitnah kontaktieren\n3. Besichtigungstermin vereinbaren (falls nötig)\n4. Kostenvoranschlag erstellen\n5. Bestätigung an Kunde senden\n\n\n\nBitte zeitnah bearbeiten!\n\n\n\n---\nAutomatisch generierte Team-Benachrichtigung\n{{company_name}} Anfrage-System\n{{submitted_at}}', 'internal', 1, '2025-10-12 01:00:56', '2025-10-12 01:00:56', 'team_new_request_notification', '{\"reference\":\"Referenznummer der Anfrage\",\"service_type\":\"Art des Service (z.B. Umzüge, Transport, Entrümpelung)\",\"service_name\":\"Name des Service\",\"submitted_at\":\"Zeitpunkt der Einreichung\",\"submission_id\":\"Interne ID der Submission\",\"customer_name\":\"Name des Kunden\",\"customer_email\":\"E-Mail-Adresse des Kunden\",\"customer_phone\":\"Telefonnummer des Kunden\",\"appointment_date\":\"Gewünschter Termin\",\"additional_details\":\"Zusätzliche Informationen aus dem Formular\",\"admin_url\":\"URL zum Admin-Bereich\",\"company_name\":\"Firmenname\"}');

-- =============================================
-- Default Questionnaires Data
-- =============================================

-- Questionnaires (5 rows)
INSERT INTO `questionnaires` (`id`, `title`, `description`, `service_id`, `service_types`, `status`, `created_at`, `updated_at`) VALUES
(2, 'Umzug Anfrage', 'Teilen Sie uns die Details zu Ihrem geplanten Umzug mit, damit wir Ihnen ein maßgeschneidertes Angebot erstellen können.', 1, NULL, 'active', '2025-09-10 15:29:46', '2025-09-14 22:39:00'),
(3, 'Transport-Anfrage', 'Beschreiben Sie Ihren Transportbedarf, damit wir Ihnen den besten Service bieten können.', 2, NULL, 'active', '2025-09-11 16:04:34', '2025-09-17 00:29:49'),
(4, 'Entrümmelungsanfrage', 'Lassen Sie uns wissen, welche Räume entrümpelt werden sollen, damit wir Ihnen ein kostenloses Angebot erstellen können.', 3, NULL, 'active', '2025-09-11 16:05:15', '2025-09-17 00:48:58'),
(5, 'Wohnungsauflösung', 'Für eine professionelle Haushaltsauflösung benötigen wir einige Informationen, um Ihnen ein faires Angebot mit Wertanrechnung zu erstellen.', 1, NULL, 'active', '2025-09-11 16:05:57', '2025-10-14 14:01:08'),
(8, 'Hausmeister leistungen', 'Beschreibung', 5, NULL, 'active', '2025-10-14 18:52:02', '2025-10-14 18:52:02');

-- Question Groups (18 rows)
INSERT INTO `question_groups` (`id`, `questionnaire_id`, `name`, `description`, `sort_order`, `is_active`, `created_at`, `updated_at`, `is_fixed`) VALUES
(13, 2, 'Aktuelle Adresse', '', 2, 1, '2025-09-14 09:10:29', '2025-09-14 09:10:29', 0),
(14, 2, 'Neue Adresse', '', 3, 1, '2025-09-14 22:31:34', '2025-09-14 22:31:34', 0),
(15, 2, 'Angaben zur Wohnung', '', 4, 1, '2025-09-14 22:34:28', '2025-09-14 22:34:28', 0),
(16, 2, 'Sonstige Angaben', '', 5, 1, '2025-09-14 22:37:40', '2025-09-14 22:37:40', 0),
(18, 3, 'Abholungs-Adresse', '', 2, 1, '2025-09-17 00:19:47', '2025-09-17 00:19:47', 0),
(19, 3, 'Ziel-Adresse', '', 3, 1, '2025-09-17 00:22:36', '2025-09-17 00:22:36', 0),
(20, 3, 'Sonstige Angaben', '', 4, 1, '2025-09-17 00:27:08', '2025-09-17 00:27:08', 0),
(22, 4, 'Objekt-Adresse', '', 2, 1, '2025-09-17 00:41:49', '2025-09-17 00:41:49', 0),
(23, 4, 'Bereichbeschreibung', '', 3, 1, '2025-09-17 00:43:17', '2025-09-17 00:43:17', 0),
(24, 4, 'Sonstige Angaben', '', 4, 1, '2025-09-17 00:48:12', '2025-09-17 00:48:12', 0),
(25, 5, 'Objekt', '', 2, 1, '2025-09-17 00:57:58', '2025-09-17 00:57:58', 0),
(27, 5, 'Zustand der Räume', '', 3, 1, '2025-09-17 01:07:38', '2025-09-17 01:07:38', 0),
(28, 5, 'Sonstige Angaben', '', 4, 1, '2025-09-17 01:10:29', '2025-09-17 01:10:29', 0),
(29, 2, 'Kontaktinformationen', 'Bitte geben Sie Ihre Kontaktdaten ein, damit wir Sie erreichen können.', -1, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1),
(30, 3, 'Kontaktinformationen', 'Bitte geben Sie Ihre Kontaktdaten ein, damit wir Sie erreichen können.', -1, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1),
(31, 4, 'Kontaktinformationen', 'Bitte geben Sie Ihre Kontaktdaten ein, damit wir Sie erreichen können.', -1, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1),
(32, 5, 'Kontaktinformationen', 'Bitte geben Sie Ihre Kontaktdaten ein, damit wir Sie erreichen können.', -1, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1),
(34, 8, 'Kontaktinformationen', 'Bitte geben Sie Ihre Kontaktdaten ein, damit wir Sie erreichen können.', 1, 1, '2025-10-14 18:52:02', '2025-10-14 18:52:02', 1);

-- Questions Simple (58 rows)
INSERT INTO `questions_simple` (`id`, `questionnaire_id`, `group_id`, `question_text`, `question_type`, `is_required`, `placeholder_text`, `help_text`, `options`, `validation_rules`, `sort_order`, `created_at`, `updated_at`, `is_fixed`, `sort_order_in_group`) VALUES
(4, NULL, 18, 'Aktuelle Straße und Hausnummer', 'text', 1, '', '', '', '', 1, '2025-09-12 00:24:05', '2025-09-12 00:24:05', 0, 0),
(5, NULL, 18, 'Aktuelle PLZ und Ort', 'text', 1, '', '', '', '', 2, '2025-09-12 00:24:42', '2025-09-12 00:24:42', 0, 0),
(6, NULL, NULL, 'Aktuelles Stockwerk', 'select', 1, '', '', 'Erdgeschoss\n1. Stock\n2. Stock\n3. Stock\n4. Stock\n5+ Stock', '', 3, '2025-09-12 00:25:32', '2025-09-12 00:25:32', 0, 0),
(7, NULL, NULL, 'Aktuell Aufzug vorhanden?', 'radio', 1, '', '', 'Ja, Personenaufzug\nJa, Lastenaufzug\nNein', '', 4, '2025-09-12 00:27:54', '2025-09-12 00:27:54', 0, 0),
(8, NULL, 14, 'Neue Straße und Hausnummer', 'text', 1, '', '', '', '', 0, '2025-09-12 00:31:02', '2025-09-12 01:47:39', 0, 0),
(9, NULL, 14, 'Neue PLZ und Ort', 'text', 1, '', '', '', '', 0, '2025-09-12 00:31:07', '2025-09-12 00:31:56', 0, 0),
(10, NULL, 15, 'Wohnungsgröße (aktuell)', 'select', 1, '', '', '1-Zimmer Wohnung\n2-Zimmer Wohnung\n3-Zimmer Wohnung\n4-Zimmer Wohnung\n5+ Zimmer Wohnung\nEinfamilienhaus', '', 0, '2025-09-12 00:33:27', '2025-09-12 00:33:27', 0, 0),
(11, NULL, NULL, 'Neu Aufzug vorhanden?', 'radio', 1, '', '', 'Ja, Personenaufzug\nJa, Lastenaufzug\nNein', '', 0, '2025-09-12 00:34:28', '2025-09-12 00:34:28', 0, 0),
(12, NULL, NULL, 'Neues Stockwerk', 'select', 1, '', '', 'Erdgeschoss\n1. Stock\n2. Stock\n3. Stock\n4. Stock\n5+ Stock', '', 0, '2025-09-12 00:35:09', '2025-09-12 00:35:09', 0, 0),
(13, NULL, 15, 'Wohnfläche in m² (aktuell)', 'number', 1, '', 'z.B. 75', '', '', 0, '2025-09-12 00:35:53', '2025-09-12 00:35:53', 0, 0),
(14, NULL, NULL, 'Geschätzte Menge an Möbeln/Umzugskartons', 'select', 1, '', '', 'Wenig (1-2 Zimmer, ca. 20-40 Kartons)\nMittel (3-4 Zimmer, ca. 40-80 Kartons)\nViel (5+ Zimmer, ca. 80+ Kartons)\nEinfamilienhaus (100+ Kartons)', '', 0, '2025-09-12 00:36:37', '2025-09-12 00:36:37', 0, 0),
(15, NULL, 16, 'Ab- und Aufbau der Möbel gewünscht?', 'select', 0, '', '', 'Ja, alle Möbel\nNur große Möbel\nNur Küche\nNein', '', 3, '2025-09-12 01:34:08', '2025-09-12 01:34:08', 0, 0),
(16, NULL, 16, 'Gewünschter Umzugstermin', 'date', 0, '', 'Format: TT.MM.JJJJ', '', '', 4, '2025-09-12 01:35:35', '2025-09-12 01:35:35', 0, 0),
(17, NULL, NULL, 'Benötigte Zusatzleistungen', 'checkbox', 0, '', '', 'Verpackungsservice\nEntrümpelung\nReinigung\nMöbellagerung\nKüchenmontage', '', 0, '2025-09-12 01:36:54', '2025-09-12 01:36:54', 0, 0),
(18, NULL, 28, 'Besondere Wünsche oder Anmerkungen', 'textarea', 0, '', 'Z.B. schwere Gegenstände, enge Treppenhäuser, Parkplatz-Situation', '', '', 1, '2025-09-12 01:38:06', '2025-09-12 01:38:06', 0, 0),
(19, NULL, 19, 'Ziel PLZ und Ort', 'text', 1, '', '', '', '', 3, '2025-09-12 01:39:17', '2025-09-12 01:39:51', 0, 0),
(20, NULL, 19, 'Ziel Straße und Hausnummer', 'text', 1, '', '', '', '', 1, '2025-09-12 01:39:31', '2025-09-12 01:40:07', 0, 0),
(21, NULL, 20, 'Art des Transports', 'select', 1, '', '', 'Möbeltransport\nAppatetransport\nKlavierTransport\nEinzeltransport\nKleintransport', '', 2, '2025-09-12 01:41:18', '2025-09-12 01:41:18', 0, 0),
(22, NULL, NULL, 'Gewünschter Transporttermin', 'date', 1, '', 'Format: TT.MM.JJJJ', '', '', 3, '2025-09-12 01:42:41', '2025-09-12 01:42:41', 0, 0),
(24, NULL, NULL, 'Was soll transportiert werden?', 'textarea', 1, '', 'Z.B. Sofa, Waschmaschine, Klavier', '', '', 2, '2025-09-12 01:44:31', '2025-09-12 01:44:31', 0, 0),
(25, NULL, 25, 'Objekt Straße und Hausnummer', 'text', 1, '', '', '', '', 0, '2025-09-12 01:47:29', '2025-09-12 01:47:54', 0, 0),
(26, NULL, 25, 'Objekt PLZ und Ort', 'text', 1, '', '', '', '', 0, '2025-09-12 01:47:59', '2025-09-12 01:48:12', 0, 0),
(27, NULL, 23, 'Art der Räume', 'checkbox', 0, '', '', 'Wohnung/Wohnräume\nKeller\nDachboden\nGarage\nLagerraum\nGarten/Außenbereich', '', 0, '2025-09-12 01:49:36', '2025-09-12 01:49:36', 0, 0),
(28, NULL, 23, 'Größe des zu entrümpelnden Bereichs', 'select', 0, '', '', 'Klein (bis 20m²)\nMittel (20-50m²)\nGroß (50-100m²)\nSehr groß (über 100m²)', '', 0, '2025-09-12 01:50:32', '2025-09-12 01:50:32', 0, 0),
(29, NULL, NULL, 'Zustand der Räume', 'radio', 0, '', '', 'Normal gefüllt\nStark gefüllt\nMessie-Wohnung', '', 0, '2025-09-12 01:51:54', '2025-09-12 01:51:54', 0, 0),
(30, NULL, NULL, 'Art des Mülls/Materials', 'checkbox', 0, '', '', 'Sperrmüll (Möbel, Matratzen)\nHausmüll\nElektroschrott\nSchrott/Metall\nPapier/Karton\nSondermüll\nBaumischabfall\nGrünschnitt\nBauabfall\nGefährliche Abfälle\nAnderes', '', 0, '2025-09-12 01:52:47', '2025-09-12 01:52:47', 0, 0),
(31, NULL, 28, 'Sind wertvolle Gegenstände dabei?', 'radio', 0, '', '', 'Ja, bitte sichten und separieren\nNein, alles entsorgen', '', 0, '2025-09-12 01:54:22', '2025-09-12 01:54:22', 0, 0),
(32, NULL, NULL, 'Art des Haushalts', 'select', 1, '', '', '1-Zimmer Wohnung\n2-Zimmer Wohnung\n3-Zimmer Wohnung\n4-Zimmer Wohnung\n5+ Zimmer Wohnung\nEinfamilienhaus', '', 0, '2025-09-12 01:55:41', '2025-09-12 01:55:41', 0, 0),
(33, NULL, NULL, 'Grund der Auflösung', 'radio', 0, '', '', 'Erbfall\nUmzug ins Pflegeheim\nWohnungswechsel\nTodesfall\nAnderes', '', 0, '2025-09-12 01:56:42', '2025-09-12 01:56:42', 0, 0),
(34, NULL, 27, 'Zusätzliche Räume', 'checkbox', 0, '', '', 'Keller\nDachboden\nGarage\nGarten', '', 0, '2025-09-12 01:57:56', '2025-09-12 01:57:56', 0, 0),
(35, NULL, NULL, 'Gewünschter Termin für Besichtigung', 'date', 1, '', 'Format: TT.MM.JJJJ', '', '', 0, '2025-09-17 00:46:49', '2025-09-17 00:47:16', 0, 0),
(36, NULL, 27, 'Ist eine Küche vorhanden?', 'radio', 0, '', '', 'Ja, Einbauküche (fest installiert)\nJa, mobile Küche\nNein', '', 0, '2025-09-17 01:05:10', '2025-09-17 01:05:10', 0, 0),
(37, 2, 29, 'Vorname', 'text', 1, 'Ihr Vorname', '', NULL, NULL, 0, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 0),
(38, 2, 29, 'Nachname', 'text', 1, 'Ihr Nachname', '', NULL, NULL, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 1),
(39, 2, 29, 'E-Mail Adresse', 'email', 1, 'ihre.email@beispiel.de', '', NULL, NULL, 2, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 2),
(40, 2, 29, 'Telefonnummer', 'phone', 0, '+49 123 456789', 'Ihre Festnetznummer (optional)', NULL, NULL, 3, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 3),
(41, 2, 29, 'Mobilnummer', 'phone', 0, '+49 170 1234567', 'Ihre Mobilnummer (optional)', NULL, NULL, 4, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 4),
(42, 3, 30, 'Vorname', 'text', 1, 'Ihr Vorname', '', NULL, NULL, 0, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 0),
(43, 3, 30, 'Nachname', 'text', 1, 'Ihr Nachname', '', NULL, NULL, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 1),
(44, 3, 30, 'E-Mail Adresse', 'email', 1, 'ihre.email@beispiel.de', '', NULL, NULL, 2, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 2),
(45, 3, 30, 'Telefonnummer', 'phone', 0, '+49 123 456789', 'Ihre Festnetznummer (optional)', NULL, NULL, 3, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 3),
(46, 3, 30, 'Mobilnummer', 'phone', 0, '+49 170 1234567', 'Ihre Mobilnummer (optional)', NULL, NULL, 4, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 4),
(47, 4, 31, 'Vorname', 'text', 1, 'Ihr Vorname', '', NULL, NULL, 0, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 0),
(48, 4, 31, 'Nachname', 'text', 1, 'Ihr Nachname', '', NULL, NULL, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 1),
(49, 4, 31, 'E-Mail Adresse', 'email', 1, 'ihre.email@beispiel.de', '', NULL, NULL, 2, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 2),
(50, 4, 31, 'Telefonnummer', 'phone', 0, '+49 123 456789', 'Ihre Festnetznummer (optional)', NULL, NULL, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 3),
(51, 4, 31, 'Mobilnummer', 'phone', 0, '+49 170 1234567', 'Ihre Mobilnummer (optional)', NULL, NULL, 4, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 4),
(52, 5, 32, 'Vorname', 'text', 1, 'Ihr Vorname', '', NULL, NULL, 0, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 0),
(53, 5, 32, 'Nachname', 'text', 1, 'Ihr Nachname', '', NULL, NULL, 1, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 1),
(54, 5, 32, 'E-Mail Adresse', 'email', 1, 'ihre.email@beispiel.de', '', NULL, NULL, 2, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 2),
(55, 5, 32, 'Telefonnummer', 'phone', 0, '+49 123 456789', 'Ihre Festnetznummer (optional)', NULL, NULL, 3, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 3),
(56, 5, 32, 'Mobilnummer', 'phone', 0, '+49 170 1234567', 'Ihre Mobilnummer (optional)', NULL, NULL, 4, '2025-10-14 13:16:07', '2025-10-14 13:16:07', 1, 4),
(62, 8, 34, 'Vorname', 'text', 1, 'Ihr Vorname', '', NULL, NULL, 0, '2025-10-14 18:52:02', '2025-10-14 18:52:02', 1, 0),
(63, 8, 34, 'Nachname', 'text', 1, 'Ihr Nachname', '', NULL, NULL, 1, '2025-10-14 18:52:02', '2025-10-14 18:52:02', 1, 1),
(64, 8, 34, 'E-Mail Adresse', 'email', 1, 'ihre.email@beispiel.de', '', NULL, NULL, 2, '2025-10-14 18:52:02', '2025-10-14 18:52:02', 1, 2),
(65, 8, 34, 'Telefonnummer', 'phone', 0, '+49 123 456789', 'Ihre Festnetznummer (optional)', NULL, NULL, 3, '2025-10-14 18:52:02', '2025-10-14 18:52:02', 1, 3),
(66, 8, 34, 'Mobilnummer', 'phone', 0, '+49 170 1234567', 'Ihre Mobilnummer (optional)', NULL, NULL, 4, '2025-10-14 18:52:02', '2025-10-14 18:52:02', 1, 4),
(67, NULL, NULL, 'Haben Sie Tinder', 'radio', 1, '', 'Haben Sie TINDER ?!', 'ja\nnein', '', 0, '2025-10-14 18:57:16', '2025-10-14 18:57:16', 0, 0);

-- Questionnaire Questions (71 rows)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `question_id`, `sort_order`, `created_at`, `group_id`) VALUES
(9, 2, 4, 0, '2025-09-14 09:08:59', 13),
(10, 2, 5, 1, '2025-09-14 09:09:10', 13),
(11, 2, 6, 2, '2025-09-14 09:09:20', 13),
(12, 2, 7, 3, '2025-09-14 09:09:31', 13),
(13, 2, 8, 0, '2025-09-14 22:30:38', 14),
(14, 2, 9, 1, '2025-09-14 22:30:50', 14),
(15, 2, 12, 2, '2025-09-14 22:30:56', 14),
(16, 2, 11, 3, '2025-09-14 22:31:03', 14),
(17, 2, 10, 0, '2025-09-14 22:33:55', 15),
(18, 2, 13, 1, '2025-09-14 22:34:11', 15),
(19, 2, 14, 2, '2025-09-14 22:35:38', 15),
(21, 2, 18, 2, '2025-09-14 22:36:54', 16),
(22, 2, 16, 1, '2025-09-14 22:37:07', 16),
(23, 2, 17, 0, '2025-09-14 22:37:25', 16),
(27, 3, 4, 0, '2025-09-17 00:17:51', 18),
(28, 3, 5, 2, '2025-09-17 00:18:01', 18),
(31, 3, 20, 0, '2025-09-17 00:21:08', 19),
(32, 3, 19, 1, '2025-09-17 00:21:15', 19),
(33, 3, 21, 2, '2025-09-17 00:25:01', 20),
(34, 3, 24, 0, '2025-09-17 00:25:30', 20),
(35, 3, 22, 2, '2025-09-17 00:25:37', 20),
(36, 3, 18, 2, '2025-09-17 00:25:46', 20),
(37, 3, 12, 2, '2025-09-17 00:28:50', 19),
(38, 3, 6, 2, '2025-09-17 00:29:06', 18),
(42, 4, 25, 0, '2025-09-17 00:40:56', 22),
(43, 4, 26, 1, '2025-09-17 00:41:09', 22),
(44, 4, 27, 1, '2025-09-17 00:42:34', 23),
(45, 4, 28, 1, '2025-09-17 00:42:48', 23),
(46, 4, 29, 2, '2025-09-17 00:42:54', 23),
(47, 4, 30, 3, '2025-09-17 00:44:27', 23),
(48, 4, 31, 0, '2025-09-17 00:44:55', 24),
(49, 4, 35, 1, '2025-09-17 00:47:37', 24),
(50, 4, 18, 2, '2025-09-17 00:47:51', 24),
(54, 5, 25, 0, '2025-09-17 00:56:08', 25),
(55, 5, 26, 1, '2025-09-17 00:56:14', 25),
(56, 5, 32, 2, '2025-09-17 00:57:00', 25),
(57, 5, 13, 3, '2025-09-17 00:57:23', 25),
(58, 5, 29, 0, '2025-09-17 01:03:55', 27),
(59, 5, 36, 1, '2025-09-17 01:06:27', 27),
(60, 5, 33, 2, '2025-09-17 01:06:47', 27),
(61, 5, 34, 3, '2025-09-17 01:07:10', 27),
(62, 5, 35, 1, '2025-09-17 01:08:48', 28),
(63, 5, 31, 0, '2025-09-17 01:08:53', 28),
(64, 5, 18, 2, '2025-09-17 01:09:15', 28),
(65, 2, 37, 0, '2025-10-14 13:16:07', 29),
(66, 2, 38, 1, '2025-10-14 13:16:07', 29),
(67, 2, 39, 2, '2025-10-14 13:16:07', 29),
(68, 2, 40, 3, '2025-10-14 13:16:07', 29),
(69, 2, 41, 4, '2025-10-14 13:16:07', 29),
(70, 3, 42, 0, '2025-10-14 13:16:07', 30),
(71, 3, 43, 1, '2025-10-14 13:16:07', 30),
(72, 3, 44, 2, '2025-10-14 13:16:07', 30),
(73, 3, 45, 3, '2025-10-14 13:16:07', 30),
(74, 3, 46, 4, '2025-10-14 13:16:07', 30),
(75, 4, 47, 0, '2025-10-14 13:16:07', 31),
(76, 4, 48, 1, '2025-10-14 13:16:07', 31),
(77, 4, 49, 2, '2025-10-14 13:16:07', 31),
(78, 4, 50, 3, '2025-10-14 13:16:07', 31),
(79, 4, 51, 4, '2025-10-14 13:16:07', 31),
(80, 5, 52, 0, '2025-10-14 13:16:07', 32),
(81, 5, 53, 1, '2025-10-14 13:16:07', 32),
(82, 5, 54, 2, '2025-10-14 13:16:07', 32),
(83, 5, 55, 3, '2025-10-14 13:16:07', 32),
(84, 5, 56, 4, '2025-10-14 13:16:08', 32),
(90, 8, 62, 0, '2025-10-14 18:52:02', 34),
(91, 8, 63, 1, '2025-10-14 18:52:02', 34),
(92, 8, 64, 2, '2025-10-14 18:52:02', 34),
(93, 8, 65, 3, '2025-10-14 18:52:02', 34),
(94, 8, 66, 4, '2025-10-14 18:52:02', 34),
(95, 8, 67, 0, '2025-10-14 18:57:27', NULL),
(96, 8, 50, 0, '2025-10-14 18:57:46', NULL);


-- =============================================
-- Notes:
-- =============================================
-- 1. All BOOLEAN types converted to TINYINT(1)
-- 2. All AUTOINCREMENT converted to AUTO_INCREMENT
-- 3. All INTEGER converted to INT or BIGINT (for large IDs)
-- 4. TEXT fields converted to TEXT or LONGTEXT where appropriate
-- 5. JSON type used for `offers.pricing_items` (MySQL 5.7+)
-- 6. Added proper indexes for foreign keys and frequently queried fields
-- 7. Using InnoDB engine for foreign key support
-- 8. UTF8MB4 charset for full Unicode support (including emoji)
-- 9. DATETIME with ON UPDATE CURRENT_TIMESTAMP for auto-update
-- 10. Removed sqlite_stat4 table (SQLite-specific)
-- 11. Settings data includes all configuration for the application
