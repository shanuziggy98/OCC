# People Count Migration Guide

## Overview
This guide documents the changes made to add the `people_count` field from Google Sheets column K to the database.

## Changes Made

### 1. Database Schema Changes
**File**: `add_people_count_column.sql`

Added `people_count INT DEFAULT 0` column to all property tables. This column stores the number of people from column K of the Google Sheets.

**To apply the migration**:
```bash
# Upload the SQL file to your server and run it
mysql -h mysql327.phy.lolipop.lan -u LAA0963548 -p LAA0963548-occ < add_people_count_column.sql
```

Or execute it through phpMyAdmin or your preferred database management tool.

### 2. Import Script Updates

#### auto_import_cron.php
- Updated `ensureTableExists()` method to include `people_count` column when creating new tables
- Updated INSERT statement to include `people_count` field
- Modified parsing logic to extract people count from column K (index 10) for both hostel and standard properties

#### import_all_final.php
- Updated INSERT statement to include `people_count` field
- Modified parsing logic to extract people count from column K (index 10) for both hostel and standard properties

## Column Mapping

The Google Sheets data is mapped as follows:
- Column D (index 3): Check-in date
- Column E (index 4): Check-out date
- Column F (index 5): Accommodation fee
- Column G (index 6): Night count
- Column H (index 7): Booking date
- Column I (index 8): Lead time
- Column J (index 9): Room type (for hostels)
- **Column K (index 10): People count** ⬅️ NEW

## Next Steps

### 1. Run the SQL Migration
Execute the `add_people_count_column.sql` file on your database to add the `people_count` column to all existing property tables.

### 2. Upload Updated PHP Files
Upload the following updated files to your server:
- `auto_import_cron.php`
- `import_all_final.php`

### 3. Re-import Data
After uploading the files, you can:
- Wait for the next automatic cron job to run, OR
- Manually trigger an import by clicking the "Import Data" button in the dashboard, OR
- Run `import_all_final.php` manually through your browser

### 4. Verify the Data
After importing, check a few property tables to ensure the `people_count` column is populated:
```sql
SELECT check_in, check_out, room_type, people_count
FROM iwatoyama
LIMIT 10;
```

## Notes

- The `people_count` field defaults to 0 if column K is empty or missing
- All new tables created automatically by the import script will include the `people_count` column
- The people count data is stored as an integer value
- The raw CSV data is still preserved in the `raw_data` JSON field for reference

## Rollback

If you need to rollback these changes:
1. Remove the `people_count` column from all property tables:
```sql
ALTER TABLE `property_name` DROP COLUMN `people_count`;
```
2. Restore the previous versions of `auto_import_cron.php` and `import_all_final.php`

## Future Enhancements

Once the people count data is in the database, you can:
- Add people count to the analytics dashboard
- Calculate average people per booking
- Filter or group bookings by people count
- Use people count for capacity planning and analysis
