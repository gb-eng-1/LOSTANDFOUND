-- Migration 009: Create process_guides table
-- This table stores step-by-step process guides for students

CREATE TABLE IF NOT EXISTS `process_guides` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `section` varchar(100) NOT NULL COMMENT 'report_lost, search_found, claim_item',
  `step_number` int(11) NOT NULL,
  `instruction` text NOT NULL,
  `estimated_time_minutes` int(11) DEFAULT NULL,
  `faq` json DEFAULT NULL COMMENT 'Array of {question, answer}',
  `troubleshooting` json DEFAULT NULL COMMENT 'Array of {issue, solution}',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_section` (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
