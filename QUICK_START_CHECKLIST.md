# Quick Start Checklist - Fixed Commission System

## Before You Start
✅ Have the actual fee amounts ready for each property:
- yura amounts
- konoha amounts
- isa amounts
- kaguya amounts

## Step-by-Step Setup

### ☐ 1. Configure Amounts
Edit `create_fixed_commission_system.sql`:
- [ ] Replace all `0.00` for **yura** with actual amounts
- [ ] Replace all `0.00` for **konoha** with actual amounts
- [ ] Replace all `0.00` for **isa** with actual amounts
- [ ] Replace all `0.00` for **kaguya** with actual amounts

Each property needs:
- OP業務委託料 (Operation Management Fee)
- 月1回定期点検 (Monthly Inspection Fee)
- OUT後清掃 (Checkout Cleaning Fee)
- 連泊時ステイ清掃 (Stay Cleaning Fee)
- リネン費 (Linen Fee per Person)

### ☐ 2. Run Database Scripts
In phpMyAdmin SQL tab:
- [ ] Run `create_fixed_commission_system.sql`
- [ ] Run `add_people_count_column.sql`
- [ ] Verify tables created (check for `property_commission_settings`)

### ☐ 3. Upload PHP Files
Upload to `https://exseed.main.jp/WG/analysis/OCC/`:
- [ ] `occupancy_metrics_api.php`
- [ ] `auto_import_cron.php`
- [ ] `import_all_final.php`

### ☐ 4. Import Data
- [ ] Go to your dashboard
- [ ] Click "Import Data" button
- [ ] Wait for import to complete
- [ ] Check that people_count is populated

### ☐ 5. Verify Everything Works
- [ ] Open dashboard
- [ ] Check yura, konoha, isa, kaguya show commission data
- [ ] Verify people count appears in data
- [ ] Check stay cleaning counts are calculated

## Verification Queries

### Check if settings exist:
```sql
SELECT property_name, operation_management_fee, checkout_cleaning_fee
FROM property_commission_settings
WHERE property_name IN ('yura', 'konoha', 'isa', 'kaguya');
```

### Check if people_count column exists:
```sql
DESCRIBE yura;  -- Look for people_count column
```

### Check commission method:
```sql
SELECT property_name, commission_method
FROM property_sheets
WHERE property_name IN ('yura', 'konoha', 'isa', 'kaguya');
```

## Future Updates

### To Update Amounts:
1. Open `update_fixed_commission_amounts.sql`
2. Fill in new amounts for specific property
3. Run in phpMyAdmin

### Quick Update Example:
```sql
UPDATE property_commission_settings
SET checkout_cleaning_fee = 9000
WHERE property_name = 'yura';
```

## Troubleshooting

### Commission shows 0:
→ Check if amounts are configured (not 0.00) in database

### People count not showing:
→ Re-run import after adding people_count column

### Property using percentage instead of fixed:
→ Check commission_method in property_sheets table

### Stay cleaning count wrong:
→ Verify night_count is correct in bookings

## Important Reminders

⚠️ **Each property has UNIQUE amounts** - Don't use same values for all!

⚠️ **No hardcoded defaults** - All amounts come from database

⚠️ **People count required** - Make sure column K is filled in Google Sheets

⚠️ **Monthly fees separate** - Add OP業務委託料 and 月1回定期点検 manually to monthly totals

## Need Help?

📄 **Detailed Docs**: See `FIXED_COMMISSION_SYSTEM.md`

📄 **Setup Guide**: See `SETUP_SUMMARY.md`

📄 **People Count**: See `PEOPLE_COUNT_MIGRATION.md`
