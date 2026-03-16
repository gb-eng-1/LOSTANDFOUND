-- Migration 004: Create matches table
-- This table tracks automated and manual matches between lost reports and found items

CREATE TABLE IF NOT EXISTS `matches` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `lost_report_id` varchar(50) NOT NULL COMMENT 'Reference ID of lost report',
  `found_item_id` varchar(50) NOT NULL COMMENT 'Barcode ID of found item',
  `confidence_score` decimal(5,2) DEFAULT 0 COMMENT 'Match confidence 0-100',
  `matching_criteria` json DEFAULT NULL COMMENT 'Which fields matched',
  `status` enum('Pending_Review','Approved','Rejected') NOT NULL DEFAULT 'Pending_Review',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match` (`lost_report_id`, `found_item_id`),
  KEY `idx_status` (`status`),
  KEY `idx_lost_report_id` (`lost_report_id`),
  KEY `idx_found_item_id` (`found_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
