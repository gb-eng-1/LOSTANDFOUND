-- Add role column to admins table
-- Run in phpMyAdmin: lostandfound_db

ALTER TABLE `admins` ADD COLUMN `role` varchar(50) DEFAULT 'Admin' AFTER `name`;

UPDATE `admins` SET `role` = 'Admin' WHERE `email` = 'admin@ub.edu.ph';
