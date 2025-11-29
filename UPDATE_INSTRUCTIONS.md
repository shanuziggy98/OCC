# Update Instructions for Total Sales Fix

## Problem Fixed:
- Old PHP was calculating **monthly revenue** only
- New PHP calculates **FULL YEAR revenue (Jan 1 - Dec 31)** as daily snapshots
- Now creates a **TOTAL record** that sums all properties together

## Steps to Deploy:

### 1. Upload the Fixed PHP File
Upload `save_daily_occupancy_FIXED.php` to your server:
```
https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php
```
**Replace the old file with this new one.**

### 2. Run Manual Import to Generate Historical Data
After uploading, run this URL to generate data for the last 30 days:
```
https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025&days=30
```

This will calculate and save:
- FULL YEAR sales (Jan 1 - Dec 31) for each property
- TOTAL sales across all properties
- For the last 30 days

### 3. Update the API to Return TOTAL Record

The PHP API endpoint `occupancy_metrics_api.php` needs to be updated to return the **ALL_PROPERTIES** record as `daily_ytd_revenue`.

**Find the section that handles `action=daily_occupancy`** and modify it to:

```php
// Get the TOTAL record (ALL_PROPERTIES)
$stmt = $pdo->prepare("
    SELECT record_date, room_revenue
    FROM daily_occupancy_records
    WHERE property_name = 'ALL_PROPERTIES'
    AND record_date BETWEEN ? AND ?
    ORDER BY record_date
");
$stmt->execute([$start_date, $end_date]);
$totalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build daily_ytd_revenue array
$daily_ytd_revenue = [];
foreach ($totalRecords as $record) {
    $daily_ytd_revenue[$record['record_date']] = floatval($record['room_revenue']);
}

// Calculate differences
$daily_differences = [];
$previousRevenue = null;
foreach ($totalRecords as $record) {
    $date = $record['record_date'];
    $revenue = floatval($record['room_revenue']);

    if ($previousRevenue !== null) {
        $daily_differences[$date] = $revenue - $previousRevenue;
    } else {
        $daily_differences[$date] = 0; // First date has no difference
    }

    $previousRevenue = $revenue;
}

// Add to response
$response['daily_ytd_revenue'] = $daily_ytd_revenue;
$response['daily_differences'] = $daily_differences;
```

## What This Does:

### Daily Snapshot Example:
```
March 19, 8 PM:
- Calculate ALL bookings from Jan 1 - Dec 31
- Total Sales = ¥250,000,000
- Save this snapshot

March 20, 8 PM:
- Calculate ALL bookings from Jan 1 - Dec 31 (includes new bookings!)
- Total Sales = ¥252,000,000
- Save this snapshot
- Difference = ¥252M - ¥250M = +¥2,000,000
```

### In OCC Records Tab:
```
┌─────────────────────────┬──────────┬──────────┬──────────┐
│ Property                │ Mar 19   │ Mar 20   │ Mar 21   │
├─────────────────────────┼──────────┼──────────┼──────────┤
│ Total Sales Number      │ ¥250M    │ ¥252M    │ ¥255M    │ ← Full year snapshot
├─────────────────────────┼──────────┼──────────┼──────────┤
│ Total Sales Difference  │    -     │ +¥2M     │ +¥3M     │ ← Daily growth
├─────────────────────────┼──────────┼──────────┼──────────┤
│ iwatoyama               │ 85%      │ 90%      │ 88%      │
│ goettingen              │ 72%      │ 75%      │ 78%      │
└─────────────────────────┴──────────┴──────────┴──────────┘
```

## Verification:

After deploying, verify that:
1. **Vertical View** Total Sales for full year = Last OCC Record's "Total Sales Number"
2. Both should show the same FULL YEAR total (Jan 1 - Dec 31)
3. Daily differences show how your annual forecast is growing

## Notes:
- The cron job will continue to run daily at 8 PM
- Each day it saves a snapshot of the FULL YEAR sales (Jan 1 - Dec 31)
- This tracks your annual sales forecast and how it changes as new bookings come in
