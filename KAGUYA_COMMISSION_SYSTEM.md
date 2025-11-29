# Kaguya Monthly Commission System

## Overview
Kaguya property uses a **unique monthly commission structure** that is different from other properties. Instead of calculating commissions based on per-booking fees or standard percentages, Kaguya's commission is tracked monthly based on actual spreadsheet data.

## Why Kaguya is Different

### Other Properties
- **Percentage-based**: Most properties use a percentage of revenue (e.g., 15%)
- **Fixed per-booking**: Some properties (yura, konoha, isa) use fixed fees per booking (cleaning + linen)

### Kaguya
- **Monthly totals from spreadsheet**: Uses actual monthly data with varying commission percentages
- **Varying rates**: Commission percentage changes each month (0% to 100%)
- **Owner payment tracking**: Includes both owner payment and Exseed commission

## Database Structure

### Table: `kaguya_monthly_commission`

```sql
CREATE TABLE kaguya_monthly_commission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    month INT NOT NULL,
    total_sales DECIMAL(12,2) NOT NULL,
    owner_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
    exseed_commission DECIMAL(12,2) NOT NULL,
    commission_percentage DECIMAL(5,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year_month (year, month)
);
```

## Historical Data (2025)

| Month | Total Sales | Owner Payment | Exseed Commission | Commission % |
|-------|-------------|---------------|-------------------|--------------|
| Jan   | ¥1,131,970  | ¥1,031,610    | ¥100,360          | 8.9%         |
| Feb   | ¥1,137,878  | ¥955,370      | ¥158,328          | 13.9%        |
| Mar   | ¥1,940,262  | ¥1,845,006    | ¥95,256           | 4.9%         |
| Apr   | ¥3,870,532  | ¥3,765,892    | ¥104,640          | 2.7%         |
| May   | ¥2,594,538  | ¥2,594,540    | ¥0                | 0.0%         |
| Jun   | ¥707,415    | ¥0            | ¥670,615          | 94.8%        |
| Jul   | ¥1,327,236  | ¥0            | ¥1,327,236        | 100.0%       |
| Aug   | ¥1,029,915  | ¥0            | ¥1,029,915        | 100.0%       |

## API Integration

### How It Works

When the API calculates metrics for Kaguya:

1. **Check for monthly data**: Look up the year/month in `kaguya_monthly_commission` table
2. **Use monthly values if found**:
   - `ota_commission` = `exseed_commission` from table
   - `commission_percent` = `commission_percentage` from table
   - `owner_payment` = `owner_payment` from table
3. **Set commission method**: `commission_method` = `'kaguya_monthly'`

### API Response Structure

```json
{
  "property_name": "kaguya",
  "booked_nights": 45,
  "booking_count": 12,
  "room_revenue": 1131970,
  "ota_commission": 100360,
  "commission_percent": 8.9,
  "commission_method": "kaguya_monthly",
  "commission_breakdown": {
    "total_sales": 1131970,
    "owner_payment": 1031610,
    "exseed_commission": 100360,
    "commission_percentage": 8.9,
    "year": 2025,
    "month": 1,
    "notes": "January 2025",
    "data_source": "kaguya_monthly_commission_table"
  }
}
```

## Key Differences from Other Commission Methods

| Feature | Kaguya Monthly | Fixed Commission | Percentage |
|---------|----------------|------------------|------------|
| **Calculation** | Monthly total from table | Per-booking fees | % of revenue |
| **Rate Consistency** | Varies monthly | Fixed amounts | Fixed % |
| **Data Source** | Spreadsheet import | Property settings | Property settings |
| **Breakdown** | Monthly totals only | Cleaning + Linen details | Simple % |

## Commission Percentage Analysis

### Observations
- **Lowest**: 0.0% (May 2025) - No commission
- **Highest**: 100.0% (July, August 2025) - Full revenue to Exseed
- **Average**: ~40.6% across 8 months
- **Typical range**: 2.7% to 13.9% in normal months

### Why the Variation?
The varying commission percentages likely reflect:
- Special contractual arrangements
- Seasonal adjustments
- Performance-based agreements
- Transition periods (June-August showing 94.8% to 100%)

## Updating Monthly Data

### Adding New Month Data

```sql
INSERT INTO kaguya_monthly_commission
(year, month, total_sales, owner_payment, exseed_commission, commission_percentage, notes)
VALUES
(2025, 9, 1500000, 1350000, 150000, 10.0, 'September 2025')
ON DUPLICATE KEY UPDATE
    total_sales = VALUES(total_sales),
    owner_payment = VALUES(owner_payment),
    exseed_commission = VALUES(exseed_commission),
    commission_percentage = VALUES(commission_percentage),
    notes = VALUES(notes);
```

### Modifying Existing Month

```sql
UPDATE kaguya_monthly_commission
SET
    total_sales = 1200000,
    owner_payment = 1080000,
    exseed_commission = 120000,
    commission_percentage = 10.0,
    notes = 'Updated January 2025'
WHERE year = 2025 AND month = 1;
```

## Frontend Display

When displaying Kaguya commission data in the dashboard:

1. **Check commission_method**: If `'kaguya_monthly'`, show special breakdown
2. **Display monthly totals**: Show total_sales, owner_payment, exseed_commission
3. **Highlight percentage**: Show the actual commission percentage for the month
4. **Add context**: Include notes field to explain any special circumstances

### Example Display

```
Kaguya - January 2025
━━━━━━━━━━━━━━━━━━━━━━
Total Sales:       ¥1,131,970
Owner Payment:     ¥1,031,610
Exseed Commission: ¥100,360
Commission Rate:   8.9%

Source: Monthly Commission Table
```

## Implementation Files

1. **SQL Table**: `create_kaguya_commission_table.sql`
2. **API Logic**: `occupancy_metrics_api.php` (lines 66-72, 202-218, 264-276)
3. **Auto Import**: `auto_import_cron.php` (includes Kaguya commission import)
4. **Standalone Import**: `import_kaguya_commission.php` (optional, for manual import)
5. **Documentation**: This file

## Automatic Import

The Kaguya commission data is **automatically imported** when you click the "Import Data" button in the Import Manager or when the cron job runs. The import happens automatically alongside the property booking data.

### How It Works

When you run the import (via Import Manager button or cron job):

1. **Property data imports first** - All booking data is imported from property sheets
2. **Kaguya commission imports next** - Monthly commission data is fetched from the Kaguya commission spreadsheet
3. **Both are logged** - All import activity is logged for tracking

### Checking Import Success

After clicking "Import Data", check the logs to verify Kaguya commission was imported:

```
---------- Starting Kaguya Commission Import ----------
Fetching Kaguya commission data from Google Sheets...
Fetched 15 rows from Kaguya commission sheet
Inserted: January 2025 - Sales=¥1,131,970, Commission=¥100,360 (8.9%)
Updated: February 2025 - Sales=¥1,137,878, Commission=¥158,328 (13.9%)
...
Kaguya Commission Summary: Inserted=3, Updated=5, Skipped=0
---------- Kaguya Commission Import Completed ----------
```

## Troubleshooting

### Commission shows as 0%
- Check if data exists in `kaguya_monthly_commission` for that year/month
- Verify the API is correctly fetching the monthly data
- Ensure `isKaguyaWithMonthlyData` flag is set correctly

### Wrong commission calculation
- Confirm the spreadsheet data is correctly imported
- Check that Kaguya is not also using the fixed commission system
- Verify the month/year parameters match the database records

### Missing months
- Add monthly data using the INSERT statement above
- For future months, consider setting up a process to import from spreadsheet
- Historical months can use percentage method as fallback

## Future Enhancements

1. **Automatic Import**: Create a script to import monthly data from Google Sheets
2. **Validation**: Add checks to ensure total_sales = owner_payment + exseed_commission
3. **Historical Tracking**: Keep audit log of commission changes
4. **Forecasting**: Use historical data to predict future commission rates

## Notes

- This system is **exclusive to Kaguya** property
- Other properties continue using their standard commission methods
- The monthly table takes **priority** over any other commission settings
- If monthly data is not available, the system can fall back to percentage calculation
