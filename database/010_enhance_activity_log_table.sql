-- Migration 010: Enhance activity_log table
-- This migration adds columns for better tracking of activities

ALTER TABLE `activity_log` 
ADD COLUMN `actor_id` int(11) unsigned DEFAULT NULL AFTER `action`,
ADD COLUMN `actor_type` enum('admin','student','system') DEFAULT 'system' AFTER `actor_id`,
ADD COLUMN `details` json DEFAULT NULL AFTER `actor_type`,
MODIFY `details` json DEFAULT NULL,
ADD INDEX `idx_action` (`action`);
