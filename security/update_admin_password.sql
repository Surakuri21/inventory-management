-- Update default admin password for Inventory Management System
-- This changes the seed admin (username: admin) from the default `admin123`
-- to a secure password. Hash generated via bcrypt (cost=10).
-- New password: ChangeMeNow!2026
-- Also sets must_change_password=1 so the user is forced to change on first login.
-- After applying, log in with admin/ChangeMeNow!2026 and change the password immediately.
UPDATE users
SET password_hash = '$2b$10$1r9ZEpRh9Y3YhVqj0U80meSpx2jd4bZTEucaav74HBtjzG/E2erwG',
    must_change_password = 1
WHERE username = 'admin';
