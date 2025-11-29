-- ============================================
-- Add owner_id to support multi-property owners
-- ============================================

-- Step 1: Add owner_id column to property_users table
ALTER TABLE property_users
ADD COLUMN owner_id VARCHAR(100) DEFAULT NULL COMMENT 'Shared ID for owners with multiple properties';

-- Step 2: Create index for better performance
CREATE INDEX idx_owner_id ON property_users(owner_id);

-- Step 3: Set owner_id for existing users (same as username initially)
UPDATE property_users
SET owner_id = username
WHERE owner_id IS NULL;

-- ============================================
-- Example: Create multi-property owner
-- ============================================

-- Example 1: Owner with 3 properties
-- All have DIFFERENT usernames but SAME owner_id

INSERT INTO property_users (username, password, user_type, property_name, full_name, email, owner_id, is_active)
VALUES
  ('comodita_owner', 'change123', 'property_owner', 'comodita', 'John Smith', 'john@example.com', 'john_smith', 1),
  ('enraku_owner', 'change123', 'property_owner', 'enraku', 'John Smith', 'john@example.com', 'john_smith', 1),
  ('tsubaki_owner', 'change123', 'property_owner', 'tsubaki', 'John Smith', 'john@example.com', 'john_smith', 1);

-- Now john_smith owns 3 properties!
-- Login with ANY of the usernames (comodita_owner, enraku_owner, or tsubaki_owner)
-- and you'll see all 3 properties

-- ============================================
-- Verify multi-property setup
-- ============================================

-- Check all properties for an owner
SELECT username, property_name, full_name, owner_id
FROM property_users
WHERE owner_id = 'john_smith';

-- Expected result:
-- +------------------+--------------+------------+------------+
-- | username         | property_name| full_name  | owner_id   |
-- +------------------+--------------+------------+------------+
-- | comodita_owner   | comodita     | John Smith | john_smith |
-- | enraku_owner     | enraku       | John Smith | john_smith |
-- | tsubaki_owner    | tsubaki      | John Smith | john_smith |
-- +------------------+--------------+------------+------------+
