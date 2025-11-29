# Fixed Commission System Documentation

## Overview
This system implements a fixed-fee commission structure for properties **yura, konoha, isa, and kaguya** instead of the standard percentage-based commission used by other properties.

## Fixed Commission Structure

⚠️ **IMPORTANT**: Each property (yura, konoha, isa, kaguya) has **UNIQUE amounts** for all fees. There are NO default values - you must configure each property individually in the database.

### Fee Categories (Amounts vary per property)

#### Monthly Fixed Fees
- **OP業務委託料 (Operation Management Fee)**: Unique per property
- **月1回定期点検 (Monthly Inspection/Special Cleaning)**: Unique per property

#### Per-Booking Variable Fees
- **OUT後清掃 (Check-out Cleaning)**: Unique per property
- **連泊時ステイ清掃 (Stay Cleaning)**: Unique per property

#### Per-Person Fees
- **リネン費 (Linen Fee)**: Unique per property

#### Other
- **その他 (Other fees)**: Unique per property

### Stay Cleaning Calculation Logic
Stay cleaning is triggered when guests stay for **3 or more days**:
- **Formula**: `stay_cleanings = floor(night_count / 3)`
- **Examples**:
  - 2 days = 0 stay cleanings
  - 3 days = 1 stay cleaning
  - 4-5 days = 1 stay cleaning
  - 6 days = 2 stay cleanings
  - 7 days = 2 stay cleanings
  - 9 days = 3 stay cleanings

This means we clean:
1. On check-in (included in check-out cleaning fee)
2. Every 3 days during the stay
3. On check-out for the next guest

## Database Structure

### New Table: `property_commission_settings`
```sql
CREATE TABLE property_commission_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL UNIQUE,
    commission_method ENUM('percentage', 'fixed') DEFAULT 'fixed',

    -- Fixed monthly fees
    operation_management_fee DECIMAL(10,2) DEFAULT 50000.00,
    monthly_inspection_fee DECIMAL(10,2) DEFAULT 10000.00,

    -- Per-booking fees
    checkout_cleaning_fee DECIMAL(10,2) DEFAULT 8000.00,
    stay_cleaning_fee DECIMAL(10,2) DEFAULT 6000.00,

    -- Per-person fees
    linen_fee_per_person DECIMAL(10,2) DEFAULT 500.00,

    -- Configuration
    stay_cleaning_trigger_days INT DEFAULT 3,
    percentage_rate DECIMAL(5,2) DEFAULT 15.00,

    -- Metadata
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Updated Table: `property_sheets`
Added column:
- `commission_method ENUM('percentage', 'fixed')` - Determines which calculation method to use

## Commission Calculation Examples

**Note**: The examples below use sample amounts. **Your actual amounts will be different** for each property based on what you configure in the database.

### Example Calculation Formula

For a booking:
```
Total Per-Booking Commission =
    (Checkout Cleaning Fee × 1) +
    (Stay Cleaning Fee × Number of Stay Cleanings) +
    (Linen Fee × Number of People)

Where:
    Number of Stay Cleanings = floor(Night Count / 3)
```

### Example with Sample Numbers
If YURA had these amounts (yours will be different):
- Checkout Cleaning: ¥X per booking
- Stay Cleaning: ¥Y per cleaning
- Linen Fee: ¥Z per person

**Booking: 7 days, 4 people**
```
Check-out Cleaning:  ¥X × 1
Stay Cleanings:      2 cleanings × ¥Y
Linen Fees:          4 people × ¥Z
─────────────────────────────────────────
Total Commission:    ¥(X + 2Y + 4Z)
```

### Monthly Total Calculation
For a property in one month:
```
Total Monthly Commission =
    Sum of all booking commissions +
    Operation Management Fee +
    Monthly Inspection Fee
```

## API Response Structure

For properties using fixed commission, the API response includes additional fields:

```json
{
  "property_name": "yura",
  "booked_nights": 12,
  "booking_count": 3,
  "room_revenue": 150000,
  "ota_commission": 45000,
  "commission_method": "fixed",
  "total_people": 6,
  "total_stay_cleanings": 3,
  "commission_breakdown": {
    "checkout_cleaning_fee": 8000,
    "checkout_cleaning_count": 3,
    "total_checkout_cleaning": 24000,
    "stay_cleaning_fee": 6000,
    "stay_cleaning_count": 3,
    "total_stay_cleaning": 18000,
    "linen_fee_per_person": 500,
    "total_people": 6,
    "total_linen_fee": 3000,
    "operation_management_fee_monthly": 50000,
    "monthly_inspection_fee": 10000
  }
}
```

## Implementation Steps

### 1. Run SQL Migration
```bash
# Execute the SQL script to create tables and settings
mysql -h mysql327.phy.lolipop.lan -u LAA0963548 -p LAA0963548-occ < create_fixed_commission_system.sql
```

Or run in phpMyAdmin SQL tab.

⚠️ **IMPORTANT**: Before running, edit `create_fixed_commission_system.sql` and replace all `0.00` values with the actual amounts for each property. Each property (yura, konoha, isa, kaguya) has different amounts!

### 1b. Configure Amounts for Each Property
Use `update_fixed_commission_amounts.sql` template to set unique amounts for each property:

1. Open `update_fixed_commission_amounts.sql`
2. Replace all `0.00` values with actual amounts for:
   - **yura** (unique amounts)
   - **konoha** (unique amounts)
   - **isa** (unique amounts)
   - **kaguya** (unique amounts)
3. Run the SQL in phpMyAdmin

### 2. Upload Updated Files
Upload these files to your server:
- `occupancy_metrics_api.php` (updated with fixed commission logic)
- `auto_import_cron.php` (updated with people_count field)
- `import_all_final.php` (updated with people_count field)

### 3. Re-import Data
After uploading, trigger a data import to populate the `people_count` field:
- Click "Import Data" button in the dashboard, OR
- Wait for the automatic cron job to run

### 4. Verify
Check that the four properties (yura, konoha, isa, kaguya) show:
- `commission_method: "fixed"` in the API response
- `commission_breakdown` with detailed fee breakdown
- `total_people` and `total_stay_cleanings` values

## Property Configuration

### Properties Using Fixed Commission
- **yura**
- **konoha**
- **isa**
- **kaguya**

### Properties Using Percentage Commission
All other properties use the standard percentage-based commission (typically 15-58% depending on the property).

## Modifying Settings

### To Add More Properties to Fixed Commission
```sql
-- Add to property_sheets
UPDATE property_sheets
SET commission_method = 'fixed'
WHERE property_name = 'new_property_name';

-- Add settings
INSERT INTO property_commission_settings (property_name, commission_method)
VALUES ('new_property_name', 'fixed');
```

### To Modify Fixed Fee Amounts (Easy Method)
Use `update_fixed_commission_amounts.sql` template and update the specific property section.

Or directly in phpMyAdmin:
```sql
UPDATE property_commission_settings
SET
    operation_management_fee = 60000,  -- Change OP fee (unique per property)
    checkout_cleaning_fee = 9000,       -- Change checkout cleaning (unique per property)
    linen_fee_per_person = 600          -- Change linen fee (unique per property)
WHERE property_name = 'yura';
```

**Remember**: Each property has unique amounts. What works for yura may be different for konoha, isa, or kaguya!

### To Switch Back to Percentage
```sql
UPDATE property_sheets
SET commission_method = 'percentage'
WHERE property_name = 'yura';
```

## Notes

### People Count Requirement
The fixed commission system relies on the `people_count` field in the booking data. Make sure:
1. Column K in Google Sheets contains the number of people for each booking
2. The `people_count` column exists in all property tables
3. Data has been re-imported after adding the column

### Monthly Fees
The monthly fixed fees (OP業務委託料 and 月1回定期点検) are stored in the settings but **not automatically added** to the per-booking commission calculation. You'll need to add these manually when calculating monthly totals:

```
Total Monthly Commission =
    Sum of all booking commissions +
    OP業務委託料 (¥50,000) +
    月1回定期点検 (¥10,000)
```

### Stay Cleaning Schedule
The stay cleaning happens every 3 days:
- Day 0 (check-in): Initial cleaning
- Day 3: First stay cleaning
- Day 6: Second stay cleaning
- Day 9: Third stay cleaning
- etc.

On check-out day, a cleaning is always done (included in check-out cleaning fee) to prepare for the next guest.

## Troubleshooting

### Commission showing as 0
- Check if `people_count` column exists in the property table
- Verify `people_count` data has been imported
- Check if property is properly configured in `property_commission_settings`

### Stay cleaning count seems wrong
- Verify `night_count` is correct in the bookings
- Remember: `floor(night_count / 3)` is the formula
- 3 days = 1 cleaning, 6 days = 2 cleanings, etc.

### Property not using fixed commission
- Check `commission_method` in `property_sheets` table
- Verify entry exists in `property_commission_settings`
- Check `is_active = TRUE` in both tables
