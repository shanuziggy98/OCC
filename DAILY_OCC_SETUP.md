# Daily Occupancy Record System - Setup Guide

## Overview
This system saves daily occupancy data for all properties at 8:00 AM every day via Google Apps Script.

## Files Created

1. **create_daily_occupancy_table.sql** - Database table creation
2. **save_daily_occupancy.php** - PHP script to calculate and save data
3. **google_apps_script_daily_occupancy.js** - Google Apps Script for 8 AM trigger
4. **occupancy_metrics_api.php** - Updated to read from database

## Setup Steps

### Step 1: Create Database Table

1. Open phpMyAdmin
2. Select database: `LAA0963548-occ`
3. Go to SQL tab
4. Copy and paste contents of `create_daily_occupancy_table.sql`
5. Click "Go"

You should see: ✅ Daily Occupancy Table Created!

### Step 2: Upload PHP Files to Server

Upload these files to your server:
```
https://exseed.main.jp/WG/analysis/OCC/
```

Files to upload:
- `save_daily_occupancy.php` (NEW)
- `occupancy_metrics_api.php` (UPDATED)

### Step 3: Test the Save Script

Open this URL in your browser:
```
https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025
```

You should see a JSON response like:
```json
{
  "success": true,
  "message": "Daily occupancy records saved successfully",
  "dates_processed": ["2025-10-12"],
  "properties_count": 30,
  "records_saved": 30,
  "total_records": 30
}
```

### Step 4: Save Historical Data (Optional)

To save last 30 days of data, open:
```
https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025&days=30
```

This will save 30 days of historical occupancy data.

### Step 5: Setup Google Apps Script

1. **Open Google Sheets** (any sheet or create new one)
2. **Extensions → Apps Script**
3. **Delete default code**
4. **Copy and paste** the content from `google_apps_script_daily_occupancy.js`
5. **Save** (Ctrl+S)

### Step 6: Test Manually

1. **Select function**: `testSaveOccupancy` from dropdown
2. **Click Run** ▶️
3. **Grant permissions** (first time only)
4. **View logs**: Check execution log

You should see:
```
Starting daily occupancy save...
✅ Daily occupancy saved successfully
Logged to spreadsheet
```

### Step 7: Enable Daily 8 AM Trigger

1. **Select function**: `setupDailyTrigger`
2. **Click Run** ▶️
3. **Done!** Now runs at 8:00 AM JST every day

Verify trigger is active:
- Click clock icon ⏰ (Triggers)
- You should see:
  - Function: `saveDailyOccupancy`
  - Event: Time-driven
  - Time: 8 AM JST daily

### Step 8: Build and Upload Frontend

1. Run `npm run build` in your local project
2. Upload `out/` folder contents to server
3. Open dashboard and click "OCC Record" button

## Trigger URL

Your trigger URL is:
```
https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025
```

### URL Parameters

- `auth_key` (required): `exseed_daily_occ_2025`
- `date` (optional): Target date (default: today) - Format: YYYY-MM-DD
- `days` (optional): Number of days to save backwards from date (default: 1)

### Examples

**Save today's data:**
```
?auth_key=exseed_daily_occ_2025
```

**Save specific date:**
```
?auth_key=exseed_daily_occ_2025&date=2025-10-01
```

**Save last 7 days:**
```
?auth_key=exseed_daily_occ_2025&days=7
```

**Save historical data (30 days):**
```
?auth_key=exseed_daily_occ_2025&days=30
```

## How It Works

### Daily Process (8:00 AM)

1. **Google Apps Script triggers** at 8:00 AM JST
2. **Calls save_daily_occupancy.php** with auth key
3. **PHP script**:
   - Gets all active properties from `property_sheets`
   - For each property and date:
     - Counts occupied rooms
     - Calculates occupancy rate
     - Saves to `daily_occupancy_records` table
4. **Returns JSON** with results
5. **Logs to spreadsheet** ("Daily OCC Log" tab)

### Frontend Display

1. User clicks "OCC Record" button
2. Frontend calls API: `occupancy_metrics_api.php?action=daily_occupancy&start_date=...&end_date=...`
3. API reads data from `daily_occupancy_records` table
4. Displays in color-coded table

## Database Table Structure

```sql
CREATE TABLE daily_occupancy_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(100) NOT NULL,
    record_date DATE NOT NULL,
    occupied_rooms INT NOT NULL,
    total_rooms INT NOT NULL,
    occupancy_rate DECIMAL(5,2) NOT NULL,
    booked_nights INT NOT NULL,
    booking_count INT NOT NULL,
    room_revenue DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_property_date (property_name, record_date)
);
```

## Monitoring

### Check if Trigger is Running

1. Open Google Sheets with the Apps Script
2. Check "Daily OCC Log" tab
3. See daily entries with timestamps

### Check Database

Run in phpMyAdmin:
```sql
SELECT 
    record_date,
    COUNT(*) as properties_count,
    AVG(occupancy_rate) as avg_occupancy
FROM daily_occupancy_records
GROUP BY record_date
ORDER BY record_date DESC
LIMIT 30;
```

## Troubleshooting

### No Data in OCC Record Tab

1. **Check if table exists**:
   ```sql
   SHOW TABLES LIKE 'daily_occupancy_records';
   ```

2. **Check if data exists**:
   ```sql
   SELECT COUNT(*) FROM daily_occupancy_records;
   ```

3. **Manually trigger save**:
   Open: `https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025`

### Trigger Not Running

1. Check Google Apps Script triggers (clock icon)
2. Check execution logs in Apps Script
3. Verify timezone is set to `Asia/Tokyo`

### Wrong Data

1. Re-run save script for specific date:
   ```
   ?auth_key=exseed_daily_occ_2025&date=2025-10-12
   ```

## Additional Functions

### Save Historical Data (30 Days)

In Google Apps Script, run:
```javascript
saveHistoricalData()
```

This saves the last 30 days of occupancy data.

### Change Save Time

Edit `setupDailyTrigger()`:
```javascript
.atHour(8)  // Change to desired hour (0-23)
```

Then run `setupDailyTrigger()` again.

### Change Number of Days

Edit CONFIG in Apps Script:
```javascript
const CONFIG = {
  SAVE_URL: '...',
  DAYS_TO_SAVE: 1  // Change to 7 for last 7 days, etc.
};
```

## Security

- Auth key: `exseed_daily_occ_2025`
- Only requests with valid auth key are processed
- Change the auth key in both:
  - `save_daily_occupancy.php` (line 9)
  - Google Apps Script CONFIG (line 10)

## Summary

✅ Database table stores daily records  
✅ PHP script calculates and saves data  
✅ Google Apps Script triggers at 8 AM daily  
✅ Frontend displays from database  
✅ No hardcoded data - all from database!  

The system now:
1. Saves daily occupancy at 8 AM automatically
2. Stores in database permanently
3. Displays historical data in OCC Record tab
4. Updates every day without manual intervention
