# Automatic Google Sheets to Database Import Setup

This guide will help you set up automatic real-time syncing from Google Sheets to your database.

## 📁 Files Created

1. **auto_import_cron.php** - The automated import script
2. **AUTO_IMPORT_SETUP.md** - This setup guide (you're reading it!)

## 🚀 Quick Setup

### Option 1: Via URL (Immediate Testing)

You can trigger the import manually via URL for testing:

```
https://exseed.main.jp/WG/analysis/OCC/auto_import_cron.php?auth_key=exseed_auto_import_2025
```

This will:
- Import all data from all Google Sheets
- Create logs in `/logs/` folder
- Update the database immediately

### Option 2: Set Up Cron Job (Automatic Imports)

#### For Lolipop Hosting:

1. **Login to Lolipop Control Panel**
   - Go to https://user.lolipop.jp/
   - Login with your credentials

2. **Navigate to Cron Settings**
   - Look for "定期実行設定" (Scheduled Execution Settings) or "Cron設定"

3. **Add New Cron Job**

   **For Hourly Imports (Recommended):**
   ```
   0 * * * * /usr/bin/php /home/users/2/lolipop.jp-dp63548321/web/WG/analysis/OCC/auto_import_cron.php
   ```

   **For Every 30 Minutes (More Frequent):**
   ```
   */30 * * * * /usr/bin/php /home/users/2/lolipop.jp-dp63548321/web/WG/analysis/OCC/auto_import_cron.php
   ```

   **For Every 15 Minutes (Near Real-Time):**
   ```
   */15 * * * * /usr/bin/php /home/users/2/lolipop.jp-dp63548321/web/WG/analysis/OCC/auto_import_cron.php
   ```

   **For Daily at 3 AM:**
   ```
   0 3 * * * /usr/bin/php /home/users/2/lolipop.jp-dp63548321/web/WG/analysis/OCC/auto_import_cron.php
   ```

4. **Important Notes:**
   - Replace the path `/home/users/2/lolipop.jp-dp63548321/web/WG/analysis/OCC/` with your actual server path
   - You can find your exact path in Lolipop's control panel under "サーバー情報" (Server Information)

#### Cron Schedule Format:
```
* * * * * command
│ │ │ │ │
│ │ │ │ └─── Day of week (0-7, Sunday = 0 or 7)
│ │ │ └───── Month (1-12)
│ │ └─────── Day of month (1-31)
│ └───────── Hour (0-23)
└─────────── Minute (0-59)
```

## 📊 Monitoring Imports

### Check Import Logs

Logs are automatically created in the `/logs/` folder:

```
/logs/auto_import_2025-10.log
```

Each log entry includes:
- Timestamp
- Which properties were imported
- Number of records imported
- Any errors that occurred

### View Logs via Browser

Create a simple log viewer (optional):

```php
<?php
// view_logs.php
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/auto_import_' . date('Y-m') . '.log';

if (file_exists($logFile)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_get_contents($logFile);
} else {
    echo "No log file found for this month.";
}
?>
```

Access: `https://exseed.main.jp/WG/analysis/OCC/view_logs.php`

## 🔧 Troubleshooting

### Import Not Running

1. **Check if cron job is set up correctly:**
   - Verify the path is correct
   - Check Lolipop cron job status in control panel

2. **Check PHP path:**
   ```bash
   which php
   ```
   Common paths:
   - `/usr/bin/php`
   - `/usr/local/bin/php`
   - `/usr/bin/php8.1`

3. **Test manually via URL:**
   ```
   https://exseed.main.jp/WG/analysis/OCC/auto_import_cron.php?auth_key=exseed_auto_import_2025
   ```

### Permissions Issues

If logs aren't being created:

```bash
chmod 755 auto_import_cron.php
mkdir logs
chmod 777 logs
```

### Check Last Import Time

Query the database to see when each property was last imported:

```sql
SELECT property_name, last_imported
FROM property_sheets
ORDER BY last_imported DESC;
```

## 🎯 Recommended Setup

For near real-time updates:
- **Every 15 minutes** - Best for active businesses
- Balances freshness with server load
- Ensures data is never more than 15 minutes old

```
*/15 * * * * /usr/bin/php /path/to/auto_import_cron.php
```

## 📝 What Happens During Auto Import

1. Script connects to database
2. Fetches list of all active properties from `property_sheets`
3. For each property:
   - Downloads CSV from Google Sheets
   - Clears old data from property table
   - Imports new data with proper parsing
   - Updates `last_imported` timestamp
4. Writes summary to log file
5. Completes (takes ~30-60 seconds for all 25 properties)

## 🔐 Security Notes

- The script requires auth key `exseed_auto_import_2025` when accessed via URL
- Change this key in `auto_import_cron.php` line 12 if needed
- Cron jobs run directly via CLI and bypass this check
- Logs folder should NOT be publicly accessible (add .htaccess if needed)

## ✅ Verification

After setup, verify it's working:

1. **Trigger manual import:**
   ```
   https://exseed.main.jp/WG/analysis/OCC/auto_import_cron.php?auth_key=exseed_auto_import_2025
   ```

2. **Check the log file** for success messages

3. **Check database:**
   ```sql
   SELECT property_name, last_imported
   FROM property_sheets
   WHERE property_name = 'nishioji_fujita';
   ```

4. **Verify your October 2025 booking is now in the database:**
   ```sql
   SELECT * FROM nishioji_fujita
   WHERE check_in = '2025-10-11'
   AND check_out = '2025-10-13';
   ```

## 📞 Support

If you encounter any issues:
1. Check the log files first
2. Test the manual URL trigger
3. Verify Google Sheets URLs are still accessible
4. Check database connection settings

---

**Created:** 2025-10-09
**Version:** 1.0
