-- Lost and Found System - Complete Database Schema
-- Import this file directly into phpMyAdmin
-- This combines all migrations into one file for easier setup

-- ============================================================
-- MIGRATION 001: Enhance items table
-- ============================================================
ALTER TABLE `items` 
ADD COLUMN `disposal_deadline` date DEFAULT NULL COMMENT 'Date after which unclaimed item may be disposed' AFTER `status`,
ADD COLUMN `matched_barcode_id` varchar(50) DEFAULT NULL COMMENT 'Reference to matched found item' AFTER `disposal_deadline`,
ADD INDEX `idx_disposal_deadline` (`disposal_deadline`),
ADD INDEX `idx_matched_barcode_id` (`matched_barcode_id`);

ALTER TABLE `items` 
MODIFY `status` enum('Found','Lost','Matched','Claimed','Resolved','Archived','Cancelled') NOT NULL DEFAULT 'Found';

-- ============================================================
-- MIGRATION 002: Enhance admins table
-- ============================================================
ALTER TABLE `admins` 
ADD COLUMN `role` varchar(50) DEFAULT 'Admin' COMMENT 'Admin role for RBAC' AFTER `name`;

-- ============================================================
-- MIGRATION 003: Create students table
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATION 004: Create matches table
-- ============================================================
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

-- ============================================================
-- MIGRATION 005: Create claims table
-- ============================================================
CREATE TABLE IF NOT EXISTS `claims` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `reference_id` varchar(50) NOT NULL UNIQUE COMMENT 'Unique claim identifier',
  `student_id` int(11) unsigned NOT NULL,
  `found_item_id` varchar(50) NOT NULL,
  `lost_report_id` varchar(50) DEFAULT NULL,
  `proof_photo` longtext COMMENT 'Photo path or base64 data URL',
  `proof_description` text COMMENT 'Student description of item',
  `status` enum('Pending','Approved','Rejected','Resolved') NOT NULL DEFAULT 'Pending',
  `claim_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolution_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_id` (`reference_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_found_item_id` (`found_item_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATION 006: Create archives table
-- ============================================================
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

-- ============================================================
-- MIGRATION 007: Create notifications table
-- ============================================================
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

-- ============================================================
-- MIGRATION 008: Create support_contacts table
-- ============================================================
CREATE TABLE IF NOT EXISTS `support_contacts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(200) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `office_hours` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATION 009: Create process_guides table
-- ============================================================
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

-- ============================================================
-- MIGRATION 010: Enhance activity_log table
-- ============================================================
ALTER TABLE `activity_log` 
ADD COLUMN `actor_id` int(11) unsigned DEFAULT NULL AFTER `action`,
ADD COLUMN `actor_type` enum('admin','student','system') DEFAULT 'system' AFTER `actor_id`,
ADD INDEX `idx_action` (`action`);
