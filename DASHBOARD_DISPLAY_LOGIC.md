# Dashboard Display Logic - Fixed Commission Properties

## Overview
For properties using fixed commission (yura, konoha, isa, kaguya), the dashboard now displays **calculated values** that make the data comparable with percentage-based properties.

## Automatic Calculations

### 1. Commission % (Calculated)
**Formula**: `(Total Commission / Room Revenue) × 100`

**What it shows**: The effective commission rate as a percentage of revenue

**Example**:
```
Room Revenue: ¥100,000
Total Commission: ¥15,000
Commission %: 15.00%
```

This is **calculated automatically** from the fixed fees, making it easy to compare with percentage-based properties.

### 2. Cleaning Fee/Time (Calculated)
**Formula**: `Total Cleaning Fees / Booking Count`

**What it includes**:
- Checkout cleaning fees (¥X per booking)
- Stay cleaning fees (¥Y per cleaning)

**Example**:
```
Booking Count: 5
Checkout Cleanings: 5 × ¥8,000 = ¥40,000
Stay Cleanings: 3 × ¥6,000 = ¥18,000
Total Cleaning: ¥58,000

Cleaning Fee/Time: ¥58,000 ÷ 5 = ¥11,600
```

**Important**: Stay cleaning does NOT occur on the checkout day. The checkout day is covered by regular checkout cleaning.
- Formula: `floor((nights - 1) / 3)`
- 3 nights = 0 stay cleanings (checkout cleaning only)
- 4 nights = 1 stay cleaning (day 3, checkout on day 4)

This shows the **average cleaning cost per booking** (including both checkout and stay cleanings).

### 3. Total Cleaning Fee (Sum of All Cleaning)
**Formula**: `(Booking Count × Checkout Fee) + (Stay Cleaning Count × Stay Fee)`

**What it includes**:
- All checkout cleanings
- All stay cleanings during stays

**Example**:
```
5 bookings × ¥8,000 = ¥40,000 (checkout)
3 stay cleanings × ¥6,000 = ¥18,000 (stay)
Total: ¥58,000
```

## Dashboard Display

### For Fixed Commission Properties (yura, konoha, isa, kaguya)

The dashboard shows:

| Field | Value | How It's Calculated |
|-------|-------|---------------------|
| **Commission %** | 15.23% | (Total Commission / Revenue) × 100 |
| **Cleaning Fee/Time** | ¥11,600 | Total Cleaning / Booking Count |
| **Total Cleaning** | ¥58,000 | Checkout + Stay Cleaning Total |
| **OTA Commission** | ¥15,230 | Checkout + Stay + Linen Fees |

### For Percentage Commission Properties (all others)

The dashboard shows:

| Field | Value | How It's Calculated |
|-------|-------|---------------------|
| **Commission %** | 15.00% | From database settings |
| **Cleaning Fee/Time** | ¥5,000 | From database settings |
| **Total Cleaning** | ¥25,000 | Fee/Time × Booking Count |
| **OTA Commission** | ¥15,000 | Revenue × Percentage |

## Detailed Breakdown (API Response)

For fixed commission properties, the API includes `commission_breakdown`:

```json
{
  "property_name": "yura",
  "commission_method": "fixed",
  "commission_percent": 15.23,
  "cleaning_fee_per_time": 11600,
  "total_cleaning_fee": 58000,
  "ota_commission": 15230,
  "commission_breakdown": {
    "checkout_cleaning_fee_per_booking": 8000,
    "checkout_cleaning_count": 5,
    "total_checkout_cleaning": 40000,

    "stay_cleaning_fee_per_cleaning": 6000,
    "stay_cleaning_count": 3,
    "total_stay_cleaning": 18000,

    "total_all_cleaning": 58000,
    "average_cleaning_per_booking": 11600,

    "linen_fee_per_person": 500,
    "total_people": 12,
    "total_linen_fee": 6000,

    "operation_management_fee_monthly": 50000,
    "monthly_inspection_fee": 10000,

    "total_variable_fees": 64000
  }
}
```

## Why These Calculations?

### 1. Commission % (Calculated)
- Makes fixed-fee properties **comparable** with percentage properties
- Shows the effective rate relative to revenue
- Useful for performance analysis

### 2. Cleaning Fee/Time (Average)
- Shows the **true average cost** per booking
- Includes both checkout AND stay cleanings
- More accurate than just checkout cleaning alone

### 3. Total Cleaning Fee (Sum)
- Shows the **complete cleaning cost**
- Includes all cleaning types
- Useful for expense tracking

## Understanding the Difference

### Traditional Percentage Property
```
Revenue: ¥100,000
Commission %: 15% (fixed)
OTA Commission: ¥15,000 (always 15% of revenue)
Cleaning Fee/Time: ¥5,000 (fixed per booking)
```

### Fixed Commission Property
```
Revenue: ¥100,000
Fixed Fees: ¥15,230 (based on bookings, people, cleanings)
Commission %: 15.23% (calculated for comparison)
Cleaning Fee/Time: ¥11,600 (average including stay cleanings)
```

## Monthly Fees

**Important**: The following are NOT included in the per-booking calculation:
- OP業務委託料 (Operation Management Fee): ¥X/month
- 月1回定期点検 (Monthly Inspection): ¥Y/month

These appear in the breakdown but must be added manually when calculating monthly totals:

```
Monthly Total =
    Sum of all booking commissions +
    Operation Management Fee +
    Monthly Inspection Fee
```

## Example Comparison

### Property A (Percentage)
- 5 bookings
- ¥100,000 revenue
- 15% commission
- ¥5,000 cleaning/booking

**Results:**
- OTA Commission: ¥15,000
- Total Cleaning: ¥25,000
- Commission %: 15.00%
- Cleaning Fee/Time: ¥5,000

### Property B (Fixed - yura)
- 5 bookings
- ¥100,000 revenue
- 12 people total
- 3 stay cleanings

**Results:**
- OTA Commission: ¥64,000 (¥40k checkout + ¥18k stay + ¥6k linen)
- Total Cleaning: ¥58,000 (checkout + stay)
- Commission %: 64.00% (calculated)
- Cleaning Fee/Time: ¥11,600 (average)

## Benefits

1. **Easy Comparison**: Commission % allows comparison between fixed and percentage properties
2. **Accurate Cleaning Costs**: Fee/Time shows true average including stay cleanings
3. **Complete Picture**: Total Cleaning shows all cleaning expenses
4. **Detailed Breakdown**: API provides complete cost breakdown for analysis

## Dashboard Integration

The existing dashboard will automatically show these calculated values for fixed commission properties. No frontend changes needed - the API handles all calculations!

### What the User Sees
- **Commission %**: Automatically calculated effective rate
- **Cleaning Fee/Time**: Automatically calculated average
- **Total Cleaning**: Automatically calculated sum
- All values update automatically based on database settings
