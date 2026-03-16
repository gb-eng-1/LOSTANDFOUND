-- UB Lost and Found System - MySQL Database Schema
-- Run this in phpMyAdmin: create database, then import or paste this SQL.

CREATE DATABASE IF NOT EXISTS `lostandfound_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lostandfound_db`;

-- Items: all found/lost items (Unclaimed, Unresolved Claimants, For Verification)
CREATE TABLE IF NOT EXISTS `items` (
  `id` varchar(50) NOT NULL COMMENT 'Barcode/Reference ID',
  `user_id` varchar(100) DEFAULT NULL COMMENT 'User who reported (e.g. email)',
  `item_type` varchar(100) DEFAULT NULL COMMENT 'Category: use config/categories.php (Electronics & Gadgets, Document & Identification, Personal Belongings, Apparel & Accessories, Miscellaneous)',
  `color` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `found_at` varchar(200) DEFAULT NULL COMMENT 'Location where item was found',
  `found_by` varchar(200) DEFAULT NULL COMMENT 'Person who found (e.g. email)',
  `date_encoded` date DEFAULT NULL COMMENT 'Date found/encoded',
  `date_lost` date DEFAULT NULL COMMENT 'Date lost (if reported as lost)',
  `item_description` text,
  `storage_location` varchar(200) DEFAULT NULL,
  `image_data` longtext COMMENT 'Base64 data URL or path',
  `status` enum('Unclaimed Items','Unresolved Claimants','For Verification') NOT NULL DEFAULT 'Unclaimed Items',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_date_encoded` (`date_encoded`),
  KEY `idx_item_type` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins: for admin login
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: email admin@ub.edu.ph, password "Admin"
-- If you need to recreate this user after deleting, run create_admin.php once in the browser.
-- INSERT below uses a bcrypt hash for "Admin"; or run create_admin.php to generate and insert.
-- INSERT INTO `admins` (`email`, `password_hash`, `name`) VALUES ('admin@ub.edu.ph', '<run create_admin.php to get hash>', 'Admin');

-- Activity log (optional, for "Recent Activity" from DB)
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` varchar(50) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'e.g. encoded, matched, claimed',
  `details` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
