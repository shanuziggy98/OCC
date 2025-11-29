# Setup Summary - Fixed Commission System

## What Was Created

### 1. Database Changes
- **New Table**: `property_commission_settings` - Stores unique fee amounts for each property
- **Updated Table**: `property_sheets` - Added `commission_method` column
- **Updated Tables**: All property tables now have `people_count` column

### 2. PHP Files Updated
- **occupancy_metrics_api.php** - Added fixed commission calculation logic
- **auto_import_cron.php** - Updated to import `people_count` from column K
- **import_all_final.php** - Updated to import `people_count` from column K

### 3. SQL Scripts Created
- **create_fixed_commission_system.sql** - Creates tables and settings
- **update_fixed_commission_amounts.sql** - Template for updating amounts
- **add_people_count_column.sql** - Adds people_count to existing tables

### 4. Documentation
- **FIXED_COMMISSION_SYSTEM.md** - Complete documentation
- **PEOPLE_COUNT_MIGRATION.md** - People count feature docs
- **SETUP_SUMMARY.md** - This file

## Key Features

### ✅ No Hardcoded Values
- ALL fee amounts are stored in the database
- Each property (yura, konoha, isa, kaguya) has **UNIQUE amounts**
- You can change any amount anytime through SQL updates

### ✅ Flexible Configuration
- **Monthly Fees**: OP業務委託料, 月1回定期点検 (unique per property)
- **Per-Booking Fees**: OUT後清掃, 連泊時ステイ清掃 (unique per property)
- **Per-Person Fees**: リネン費 (unique per property)
- **Other Fees**: その他 (unique per property)

### ✅ Automatic Calculations
- **Stay Cleanings**: Automatically calculated (every 3 days)
- **Linen Fees**: Automatically calculated from people_count
- **Commission Breakdown**: Detailed breakdown in API response

## Properties Affected

### Fixed Commission Properties (Configurable Amounts)
- **yura** - Unique amounts
- **konoha** - Unique amounts
- **isa** - Unique amounts
- **kaguya** - Unique amounts

### Percentage Commission Properties (Unchanged)
- All other properties continue using percentage-based commission

## Setup Process

### Step 1: Configure the Amounts
1. Open `create_fixed_commission_system.sql`
2. Replace all `0.00` values with actual amounts for each property
3. Each property has DIFFERENT amounts - fill them all in

### Step 2: Run Database Migration
```sql
-- In phpMyAdmin, run:
create_fixed_commission_system.sql
```

### Step 3: Add people_count Column
```sql
-- In phpMyAdmin, run:
add_people_count_column.sql
```

### Step 4: Upload PHP Files
Upload to `https://exseed.main.jp/WG/analysis/OCC/`:
- occupancy_metrics_api.php
- auto_import_cron.php
- import_all_final.php

### Step 5: Import Data
Click "Import Data" button in dashboard to populate people_count

## How to Update Amounts Later

### Method 1: Use Template (Recommended)
1. Open `update_fixed_commission_amounts.sql`
2. Fill in the amounts for the property you want to update
3. Run in phpMyAdmin

### Method 2: Direct SQL
```sql
UPDATE property_commission_settings
SET
    operation_management_fee = YOUR_AMOUNT,
    monthly_inspection_fee = YOUR_AMOUNT,
    checkout_cleaning_fee = YOUR_AMOUNT,
    stay_cleaning_fee = YOUR_AMOUNT,
    linen_fee_per_person = YOUR_AMOUNT
WHERE property_name = 'yura';  -- or konoha, isa, kaguya
```

## Verification

### Check Settings
```sql
SELECT
    property_name,
    operation_management_fee,
    monthly_inspection_fee,
    checkout_cleaning_fee,
    stay_cleaning_fee,
    linen_fee_per_person
FROM property_commission_settings
WHERE property_name IN ('yura', 'konoha', 'isa', 'kaguya');
```

### Check API Response
Visit in browser:
```
https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php?year=2025&month=1
```

Look for properties with:
- `"commission_method": "fixed"`
- `"commission_breakdown"` object with detailed fees

## Important Notes

### ⚠️ Each Property is Unique
- yura has its own amounts
- konoha has its own amounts
- isa has its own amounts
- kaguya has its own amounts
- **Do NOT use the same amounts for all properties!**

### ⚠️ No Default Values
- All amounts start at 0.00
- You MUST configure each property before use
- The system won't work correctly with 0.00 values

### ⚠️ Monthly Fees
- Monthly fees (OP業務委託料, 月1回定期点検) are stored but not auto-added to bookings
- Add them manually when calculating monthly totals

## Stay Cleaning Logic

The system automatically calculates stay cleanings:
- **Trigger**: Guests staying 3+ days
- **Formula**: `floor(night_count / 3)`
- **Examples**:
  - 2 days → 0 cleanings
  - 3 days → 1 cleaning
  - 6 days → 2 cleanings
  - 7 days → 2 cleanings
  - 9 days → 3 cleanings

## People Count

The system uses column K from Google Sheets:
- Tracks number of people per booking
- Used for linen fee calculation
- Included in API response

## Support

### Files to Reference
1. **FIXED_COMMISSION_SYSTEM.md** - Complete documentation
2. **update_fixed_commission_amounts.sql** - Easy update template
3. **PEOPLE_COUNT_MIGRATION.md** - People count setup

### Common Tasks

**Update a single property's fees:**
→ Use `update_fixed_commission_amounts.sql`

**Add a new fixed commission property:**
→ See "To Add More Properties" in FIXED_COMMISSION_SYSTEM.md

**Switch property back to percentage:**
→ See "To Switch Back to Percentage" in FIXED_COMMISSION_SYSTEM.md

**Check current settings:**
→ Run the verification query above
