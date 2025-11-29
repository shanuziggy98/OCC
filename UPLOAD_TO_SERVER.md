# Files to Upload to Your Lolipop Server

## Upload Location
Upload these files to: `/WG/analysis/OCC/` directory on your server

## Required Files

### 1. occupancy_metrics_api.php
**Purpose**: Main API for occupancy calculations
**Upload to**: `https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php`

### 2. api.php (Optional)
**Purpose**: Google Sheets import functionality
**Upload to**: `https://exseed.main.jp/WG/analysis/OCC/api.php`

### 3. final_import.php (Optional)
**Purpose**: Data import script for initial setup
**Upload to**: `https://exseed.main.jp/WG/analysis/OCC/final_import.php`

## Upload Steps

1. Connect to your Lolipop server via FTP or file manager
2. Navigate to `/WG/analysis/OCC/` directory
3. Upload `occupancy_metrics_api.php`
4. Test the API by visiting: `https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php`

## Expected Response
When you visit the API URL, you should see JSON output like:
```json
{
  "year": 2025,
  "month": 9,
  "overall_occupancy_rate": 60.25,
  "properties": [...],
  "summary": {...}
}
```

## If API Returns Error
If you get an error, check:
1. MySQL connection credentials in the PHP file
2. Database tables exist (run final_import.php first if needed)
3. PHP file permissions on server

## Test Your Setup
1. Upload `occupancy_metrics_api.php` to the server
2. Visit the API URL to test
3. If successful, your Next.js frontend will now connect to real data!

Your frontend is already configured to use: `https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php`