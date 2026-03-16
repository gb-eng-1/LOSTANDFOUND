-- Migration 002: Enhance admins table with role column
-- This migration adds role-based access control support

ALTER TABLE `admins` 
ADD COLUMN `role` varchar(50) DEFAULT 'Admin' COMMENT 'Admin role for RBAC' AFTER `name`;
