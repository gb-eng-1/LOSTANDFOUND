-- Migration 007: Create notifications table
-- This table stores notifications for admins and students

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) unsigned NOT NULL COMMENT 'Admin or Student ID',
  `recipient_type` enum('admin','student') NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'match_found, claim_approved, etc.',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` varchar(50) DEFAULT NULL COMMENT 'Match ID, Claim ID, etc.',
  `is_read` boolean DEFAULT false,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_id`, `recipient_type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
