-- Add 'Claimed' to items.status for History / Claimed Items
-- Run in phpMyAdmin if status enum doesn't include Claimed.

USE `lostandfound_db`;

ALTER TABLE `items` 
MODIFY COLUMN `status` ENUM(
  'Unclaimed Items',
  'Unresolved Claimants',
  'For Verification',
  'Unclaimed IDs External',
  'Claimed'
) NOT NULL DEFAULT 'Unclaimed Items';
