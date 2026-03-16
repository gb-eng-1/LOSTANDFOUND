-- Add 'Unclaimed IDs External' to items.status enum for Guest Items (IDs surrendered by external/guest users)
-- Run this in phpMyAdmin if you get "Data truncated" or enum error when saving guest items.

USE `lostandfound_db`;

ALTER TABLE `items` 
MODIFY COLUMN `status` ENUM(
  'Unclaimed Items',
  'Unresolved Claimants',
  'For Verification',
  'Unclaimed IDs External'
) NOT NULL DEFAULT 'Unclaimed Items';
