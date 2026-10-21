-- Migration: Add must_change_password column to users table
-- Run this against an existing database that was created from an older database.sql
-- This column flags users who must change their password on next login.

ALTER TABLE `users`
ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
ADD KEY `idx_users_must_change` (`must_change_password`);

-- Set the flag for the default admin user (password was rotated to ChangeMeNow!2026)
UPDATE users SET must_change_password = 1 WHERE username = 'admin';
