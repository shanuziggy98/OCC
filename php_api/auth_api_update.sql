-- SQL to add C-Panel user to your property_users table
-- Run this SQL in your database to create the cpanel user

-- First, ensure the property_users table structure supports the cpanel user type
-- If your user_type column is an ENUM, you may need to alter it:
-- ALTER TABLE property_users MODIFY COLUMN user_type ENUM('admin', 'owner', 'cpanel') NOT NULL DEFAULT 'owner';

-- Insert the C-Panel user
-- Password: cpanel123 (plain text - matching existing system behavior)
INSERT INTO property_users (username, password, user_type, property_name, full_name, email, is_active)
VALUES (
    'cpanel',
    'cpanel123',
    'cpanel',
    NULL,
    'C-Panel Administrator',
    'cpanel@example.com',
    1
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    user_type = VALUES(user_type),
    full_name = VALUES(full_name),
    is_active = VALUES(is_active);
