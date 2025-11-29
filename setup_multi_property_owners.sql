-- ============================================
-- Setup Multi-Property Owners
-- For existing property_users table
-- ============================================

-- Current Status: Each user has owner_id = their username
-- Goal: Group properties under shared owner_ids

-- ============================================
-- Example 1: Group Fushimi Properties
-- ============================================
-- Combine fushimi_apt and fushimi_house under one owner

UPDATE property_users
SET owner_id = 'fushimi_group'
WHERE username IN ('fushimi_apt_owner', 'fushimi_house_owner');

-- Now login with either:
--   fushimi_apt_owner / change123  OR
--   fushimi_house_owner / change123
-- Both will show: [fushimi apt, Fushimi house]

-- ============================================
-- Example 2: Group Multiple Properties
-- ============================================
-- Create an owner with comodita, enraku, and tsubaki

UPDATE property_users
SET owner_id = 'kyoto_group'
WHERE username IN ('comodita_owner', 'enraku_owner', 'tsubaki_owner');

-- Now login with any of:
--   comodita_owner / change123
--   enraku_owner / change123
--   tsubaki_owner / change123
-- All will show: [comodita, enraku, Tsubaki]

-- ============================================
-- Example 3: Hostel Group
-- ============================================
-- Group all hostels together

UPDATE property_users
SET owner_id = 'hostel_group'
WHERE username IN ('iwatoyama_owner', 'goettingen_owner', 'littlehouse_owner', 'kaguya_owner');

-- Now login with any hostel username
-- Will see all 4 hostels: [iwatoyama, Goettingen, littlehouse, kaguya]

-- ============================================
-- Verification Queries
-- ============================================

-- Check Fushimi Group
SELECT username, property_name, owner_id
FROM property_users
WHERE owner_id = 'fushimi_group';

-- Check Kyoto Group
SELECT username, property_name, owner_id
FROM property_users
WHERE owner_id = 'kyoto_group';

-- Check Hostel Group
SELECT username, property_name, owner_id
FROM property_users
WHERE owner_id = 'hostel_group';

-- List all multi-property owners
SELECT owner_id, COUNT(*) as property_count, GROUP_CONCAT(property_name) as properties
FROM property_users
WHERE user_type = 'property_owner'
GROUP BY owner_id
HAVING COUNT(*) > 1;

-- ============================================
-- Reset to Single Property (if needed)
-- ============================================

-- Reset specific user to single property
-- UPDATE property_users
-- SET owner_id = username
-- WHERE username = 'comodita_owner';

-- Reset ALL users to single property
-- UPDATE property_users
-- SET owner_id = username
-- WHERE user_type = 'property_owner';

-- ============================================
-- Custom Examples - Edit as needed
-- ============================================

-- Template: Group properties
-- UPDATE property_users
-- SET owner_id = 'custom_group_name'
-- WHERE username IN ('property1_owner', 'property2_owner', 'property3_owner');
