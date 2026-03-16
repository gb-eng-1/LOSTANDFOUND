-- Migration 001: Enhance items table with new columns for matching and disposal
-- This migration adds columns needed for the new backend system

ALTER TABLE `items` 
ADD COLUMN `disposal_deadline` date DEFAULT NULL COMMENT 'Date after which unclaimed item may be disposed' AFTER `status`,
ADD COLUMN `matched_barcode_id` varchar(50) DEFAULT NULL COMMENT 'Reference to matched found item' AFTER `disposal_deadline`,
ADD INDEX `idx_disposal_deadline` (`disposal_deadline`),
ADD INDEX `idx_matched_barcode_id` (`matched_barcode_id`);

-- Update status enum to include new statuses
ALTER TABLE `items` 
MODIFY `status` enum('Found','Lost','Matched','Claimed','Resolved','Archived','Cancelled') NOT NULL DEFAULT 'Found';
