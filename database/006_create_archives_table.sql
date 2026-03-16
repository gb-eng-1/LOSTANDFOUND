-- Migration 006: Create archives table
-- This table stores historical records of resolved claims

CREATE TABLE IF NOT EXISTS `archives` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `reference_id` varchar(50) NOT NULL UNIQUE,
  `found_item_id` varchar(50) NOT NULL,
  `student_id` int(11) unsigned NOT NULL,
  `claimant_name` varchar(100) NOT NULL,
  `claimant_email` varchar(255) NOT NULL,
  `claimant_phone` varchar(20) DEFAULT NULL,
  `item_details` json NOT NULL COMMENT 'Snapshot of found item',
  `proof_photo` longtext,
  `claim_date` datetime NOT NULL,
  `resolution_date` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_id` (`reference_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_resolution_date` (`resolution_date`),
  KEY `idx_claimant_name` (`claimant_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
