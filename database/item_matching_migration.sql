-- Optional: run this in phpMyAdmin to add support for linking Reference ID (lost report) to Barcode ID (found item).
-- See database/ITEM_MATCHING_DESIGN.md for the full idea.

USE `lostandfound_db`;

-- Add column so a lost-report row can store which found item (barcode id) it is matched to.
-- For found items: id = Barcode ID, matched_barcode_id = NULL.
-- For lost reports: id = Reference ID, matched_barcode_id = Barcode ID of the matched found item (after admin matches).
ALTER TABLE `items`
  ADD COLUMN `matched_barcode_id` VARCHAR(50) DEFAULT NULL COMMENT 'When this row is a claim/report: the Barcode ID of the found item it is matched to' AFTER `storage_location`,
  ADD KEY `idx_matched_barcode_id` (`matched_barcode_id`);
