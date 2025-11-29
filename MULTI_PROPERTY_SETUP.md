# 🏢 Multi-Property Owner Setup Guide

## Problem Solved

Your database has a UNIQUE constraint on `username`, so you **cannot** create duplicate usernames.

**Solution:** Use `owner_id` field to link multiple properties to the same owner.

---

## Step 1: Run the SQL Migration

Execute this SQL file on your database:

```bash
File: add_owner_id_column.sql
```

This will:
1. ✅ Add `owner_id` column to `property_users` table
2. ✅ Create index for performance
3. ✅ Set `owner_id = username` for existing users

### Manual SQL Commands:

```sql
-- Run these in phpMyAdmin or your MySQL client

-- 1. Add owner_id column
ALTER TABLE property_users
ADD COLUMN owner_id VARCHAR(100) DEFAULT NULL COMMENT 'Shared ID for owners with multiple properties';

-- 2. Create index
CREATE INDEX idx_owner_id ON property_users(owner_id);

-- 3. Update existing records
UPDATE property_users
SET owner_id = username
WHERE owner_id IS NULL;
```

---

## Step 2: Create Multi-Property Owner

### Example: John Smith owns 3 properties

```sql
-- Insert 3 DIFFERENT usernames with SAME owner_id
INSERT INTO property_users (username, password, user_type, property_name, full_name, email, owner_id, is_active)
VALUES
  -- Property 1: Comodita
  ('comodita_owner', 'password123', 'property_owner', 'comodita', 'John Smith', 'john@example.com', 'john_smith', 1),

  -- Property 2: Enraku
  ('enraku_owner', 'password123', 'property_owner', 'enraku', 'John Smith', 'john@example.com', 'john_smith', 1),

  -- Property 3: Tsubaki
  ('tsubaki_owner', 'password123', 'property_owner', 'tsubaki', 'John Smith', 'john@example.com', 'john_smith', 1);
```

### Key Points:
- ✅ **Different usernames**: `comodita_owner`, `enraku_owner`, `tsubaki_owner`
- ✅ **Same owner_id**: `john_smith` (this links them together!)
- ✅ **Same full_name**: `John Smith`
- ✅ **Same email**: `john@example.com`

---

## Step 3: How to Login

The owner can login with **ANY** of their usernames:

### Option 1: Login as comodita_owner
```
Username: comodita_owner
Password: password123
```
→ Will see dropdown with: comodita, enraku, tsubaki

### Option 2: Login as enraku_owner
```
Username: enraku_owner
Password: password123
```
→ Will see dropdown with: comodita, enraku, tsubaki

### Option 3: Login as tsubaki_owner
```
Username: tsubaki_owner
Password: password123
```
→ Will see dropdown with: comodita, enraku, tsubaki

**All 3 logins show the same 3 properties!**

---

## How It Works

### Database Structure:

```
property_users table:
┌──────────────────┬──────────┬───────────────┬────────────┬────────────┬─────────────┐
│ username         │ password │ property_name │ full_name  │ owner_id   │ is_active   │
├──────────────────┼──────────┼───────────────┼────────────┼────────────┼─────────────┤
│ comodita_owner   │ pass123  │ comodita      │ John Smith │ john_smith │ 1           │
│ enraku_owner     │ pass123  │ enraku        │ John Smith │ john_smith │ 1           │
│ tsubaki_owner    │ pass123  │ tsubaki       │ John Smith │ john_smith │ 1           │
└──────────────────┴──────────┴───────────────┴────────────┴────────────┴─────────────┘
                                                             ↑
                                             SAME owner_id links them!
```

### Login Flow:

```
1. User enters: comodita_owner / password123
2. System finds owner_id: john_smith
3. System queries: SELECT property_name WHERE owner_id = 'john_smith'
4. Returns: ['comodita', 'enraku', 'tsubaki']
5. Dashboard shows dropdown to switch between properties
```

---

## Examples

### Single Property Owner (Existing Users)

For users with only 1 property, set `owner_id = username`:

```sql
-- Example: User with only 1 property
UPDATE property_users
SET owner_id = 'iwatoyama_owner'
WHERE username = 'iwatoyama_owner';
```

No dropdown will appear (only 1 property).

### Multi-Property Owner (New Setup)

Create owner "Mary Johnson" with 2 properties:

```sql
INSERT INTO property_users (username, password, user_type, property_name, full_name, email, owner_id, is_active)
VALUES
  ('iwatoyama_mary', 'secure123', 'property_owner', 'iwatoyama', 'Mary Johnson', 'mary@example.com', 'mary_johnson', 1),
  ('goettingen_mary', 'secure123', 'property_owner', 'goettingen', 'Mary Johnson', 'mary@example.com', 'mary_johnson', 1);
```

Dropdown will show: iwatoyama, goettingen

---

## Verification Queries

### Check Owner's Properties

```sql
-- See all properties for an owner
SELECT username, property_name, full_name, owner_id
FROM property_users
WHERE owner_id = 'john_smith'
ORDER BY property_name;
```

### List All Multi-Property Owners

```sql
-- Find owners with multiple properties
SELECT owner_id, full_name, COUNT(*) as property_count
FROM property_users
WHERE owner_id IS NOT NULL
GROUP BY owner_id, full_name
HAVING COUNT(*) > 1
ORDER BY property_count DESC;
```

### Update Owner ID

```sql
-- Change owner_id for existing users
UPDATE property_users
SET owner_id = 'new_owner_id'
WHERE username = 'some_username';
```

---

## Updated Files

**Backend:**
- ✅ `property_owner_api.php` - Updated `getUserProperties()` to use `owner_id`

**Frontend:**
- ✅ `PropertyOwnerDashboard.tsx` - Already supports multi-property

**Database:**
- ✅ `add_owner_id_column.sql` - Migration script

---

## Migration Steps for Existing System

### For Existing Single-Property Users:

```sql
-- Set owner_id = username for all existing users
UPDATE property_users
SET owner_id = username
WHERE owner_id IS NULL;
```

### To Convert Single → Multi-Property:

If a user currently has 1 property and you want to add more:

```sql
-- Example: iwatoyama_owner currently exists
-- Add a second property for the same owner

-- 1. Get current owner_id
SELECT owner_id FROM property_users WHERE username = 'iwatoyama_owner';
-- Returns: iwatoyama_owner

-- 2. Insert new property with SAME owner_id
INSERT INTO property_users (username, password, user_type, property_name, full_name, email, owner_id)
VALUES ('goettingen_new', 'password', 'property_owner', 'goettingen', 'Same Owner Name', 'email@example.com', 'iwatoyama_owner');
```

Now when logging in as `iwatoyama_owner`, they'll see both properties!

---

## Testing

### Test 1: Verify Column Added

```sql
DESCRIBE property_users;
```

Should show `owner_id` column.

### Test 2: Create Test Multi-Property Owner

```sql
INSERT INTO property_users (username, password, user_type, property_name, full_name, owner_id)
VALUES
  ('test_prop1', 'test123', 'property_owner', 'comodita', 'Test Owner', 'test_owner'),
  ('test_prop2', 'test123', 'property_owner', 'enraku', 'Test Owner', 'test_owner');
```

### Test 3: Login & Verify

1. Login with: `test_prop1` / `test123`
2. Should see dropdown with 2 properties
3. Switch between properties
4. Verify data updates

---

## Rollback (If Needed)

To remove owner_id:

```sql
-- Remove column
ALTER TABLE property_users DROP COLUMN owner_id;

-- Drop index
DROP INDEX idx_owner_id ON property_users;
```

---

## Summary

### ❌ Old Approach (Doesn't Work):
```
Same username → Database error (UNIQUE constraint)
```

### ✅ New Approach (Works!):
```
Different usernames + Same owner_id = Multi-property owner
```

### Quick Reference:

| Field        | Must Be      | Example                    |
|--------------|--------------|----------------------------|
| username     | **UNIQUE**   | comodita_owner, enraku_owner |
| owner_id     | **SAME**     | john_smith, john_smith     |
| property_name| **DIFFERENT**| comodita, enraku          |
| full_name    | Can be same  | John Smith, John Smith    |

---

## Next Steps

1. ✅ Run `add_owner_id_column.sql`
2. ✅ Create multi-property test user
3. ✅ Rebuild and deploy: `deploy.bat`
4. ✅ Test login and property switching

Done! 🎉
