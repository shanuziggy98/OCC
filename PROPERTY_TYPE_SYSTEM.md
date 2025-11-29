# Property Type System - Dynamic Hostel & Guesthouse Management

## Overview

This system allows you to easily manage different property types (hostels with multiple rooms vs. guesthouses with single units) without hardcoding property names in your code.

## Key Features

- **Dynamic Property Classification**: Properties are automatically identified as hostels or guesthouses based on database configuration
- **Flexible Room Management**: Hostel room lists are stored in the database, making it easy to add/update rooms
- **No Code Changes Needed**: Adding new properties only requires database updates

## Database Schema

### New Columns in `property_sheets` Table

1. **`property_type`** - ENUM('hostel', 'guesthouse')
   - `hostel`: Properties with multiple rooms (e.g., iwatoyama, goettingen, littlehouse)
   - `guesthouse`: Properties with single units (e.g., comodita, mujurin, etc.)
   - Default: `guesthouse`

2. **`room_list`** - TEXT
   - Comma-separated list of room names for hostels
   - Example: `"ダブル203,ダブル204,ダブル205,ユニーク206"`
   - Only used when `property_type = 'hostel'`
   - NULL for guesthouses

## Installation

### Step 1: Run Migration Script

Execute the migration script to add the new columns and populate data:

```bash
mysql -h mysql327.phy.lolipop.lan -u LAA0963548 -p LAA0963548-occ < migrate_add_property_type.sql
```

Or run via phpMyAdmin by opening `migrate_add_property_type.sql` and executing it.

### Step 2: Verify Migration

Check that the columns were added and data was populated:

```sql
SELECT
    property_name,
    property_type,
    CASE
        WHEN property_type = 'hostel' THEN
            CONCAT(
                CHAR_LENGTH(room_list) - CHAR_LENGTH(REPLACE(room_list, ',', '')) + 1,
                ' rooms'
            )
        ELSE 'N/A'
    END as room_count
FROM property_sheets
WHERE is_active = TRUE
ORDER BY property_type DESC, property_name;
```

### Step 3: Upload Updated PHP API

Upload the updated `occupancy_metrics_api.php` to your server to replace the old version.

## How to Add a New Property

### Adding a New Guesthouse (Single Unit)

Simply insert the property with default settings (property_type will default to 'guesthouse'):

```sql
INSERT INTO property_sheets (property_name, google_sheet_url, sheet_description)
VALUES (
    'new_guesthouse',
    'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit',
    'Description of the new guesthouse'
);
```

### Adding a New Hostel (Multiple Rooms)

Insert the property and specify it's a hostel with the room list:

```sql
INSERT INTO property_sheets (
    property_name,
    google_sheet_url,
    sheet_description,
    property_type,
    room_list
) VALUES (
    'new_hostel',
    'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit',
    'Description of the new hostel',
    'hostel',
    'Room101,Room102,Room103,Room201,Room202,Room203'
);
```

**Important**: Room names in `room_list` should match the exact `room_type` values in your booking data.

## How to Update Room Lists

### Add Rooms to an Existing Hostel

```sql
UPDATE property_sheets
SET room_list = CONCAT(room_list, ',NewRoom301,NewRoom302')
WHERE property_name = 'iwatoyama';
```

### Replace Entire Room List

```sql
UPDATE property_sheets
SET room_list = 'Room101,Room102,Room103,Room201,Room202'
WHERE property_name = 'goettingen';
```

### Remove a Room

```sql
UPDATE property_sheets
SET room_list = REPLACE(room_list, 'OldRoom,', '')
WHERE property_name = 'littlehouse';
```

## How It Works

### PHP API Flow

1. **Property Type Check**:
   - When fetching metrics, the API queries `property_sheets` to check `property_type`
   - Function: `isHostelProperty($propertyName)`

2. **Room List Retrieval** (for hostels only):
   - The API reads `room_list` from `property_sheets`
   - Splits comma-separated values into an array
   - Function: `getPropertyRooms($propertyName, $year, $month)`

3. **Room Count Calculation**:
   - Hostels: Count of rooms in `room_list`
   - Guesthouses: Always 1
   - Function: `getDefaultRoomCount($propertyName, $roomFilter)`

4. **Metrics Calculation**:
   - For hostels: Can calculate per-room metrics using room filters
   - For guesthouses: Calculate property-level metrics only

### Frontend Integration

The React component automatically detects hostel properties and provides expandable room views:

```typescript
// Check if property is hostel by attempting to fetch room data
const isHostelProperty = (propertyName: string) => {
  // API call to check property_type or room availability
}
```

## Current Property Configuration

### Hostels (property_type = 'hostel')

**iwatoyama** - 38 rooms:
```
岩戸山全体,ファミリー401,共用D402A,共用D402B,共用D402C,共用D402D,共用D402E,共用D402F,
ダブル403,ダブル404,ダブル405,ユニーク406,ユニーク407,ツイン408,ファミリー301,
女子D302A,女子D302B,女子D302C,女子D302D,女子D302E,女子D302F,ダブル303,ダブル304,
ダブル305,ユニーク306,ユニーク307,ツイン308,男子D202A,男子D202B,男子D202C,男子D202D,
男子D202E,男子D202F,ダブル203,ダブル204,ダブル205,ユニーク206,ユニーク207,ツイン208
```

**goettingen** - 11 rooms:
```
月沈原101,月沈原102,月沈原201,月沈原202,月沈原203,月沈原204,月沈原205,
月沈原301,月沈原302,月沈原303,月沈原304
```

**littlehouse** - 3 rooms:
```
いぬねこ1F,いぬねこ2F,秘密の部屋
```

### Guesthouses (property_type = 'guesthouse')

All other properties: comodita, mujurin, fujinomori, enraku, tsubaki, hiiragi, fushimi_apt, kanon, fushimi_house, kado, tanuki, fukuro, hauwa_apt, yanagawa, nishijin_fujita, rikyu, hiroshima, okinawa, ryoma, isa, yura, konoha

## Benefits

### Before (Hardcoded):
```php
// Every time you add a property, you had to update code in multiple places
if ($propertyName === 'iwatoyama' || $propertyName === 'goettingen' || $propertyName === 'littlehouse') {
    // Hostel logic
}
```

### After (Dynamic):
```php
// Just check the database - no code changes needed!
if ($this->isHostelProperty($propertyName)) {
    // Hostel logic - works for ANY property marked as hostel
}
```

### Key Advantages:
1. **No Code Changes**: Add new properties by just inserting database records
2. **Centralized Configuration**: All property info in one place (property_sheets table)
3. **Easy Updates**: Change room lists without touching code
4. **Type Safety**: ENUM ensures only valid property types
5. **Backward Compatible**: Fallback to hardcoded values if database query fails

## API Endpoints

### Get All Properties with Room Info
```
GET /occupancy_metrics_api.php?action=metrics&year=2025&month=1
```

Response includes property_type automatically detected.

### Get Hostel Room Breakdown
```
GET /occupancy_metrics_api.php?action=iwatoyama_rooms&year=2025&month=1
GET /occupancy_metrics_api.php?action=goettingen_rooms&year=2025&month=1
GET /occupancy_metrics_api.php?action=littlehouse_rooms&year=2025&month=1
```

These endpoints work dynamically - they fetch the room_list from the database.

## Troubleshooting

### Problem: Room not showing up in dashboard

**Check 1**: Verify room exists in room_list
```sql
SELECT property_name, room_list
FROM property_sheets
WHERE property_name = 'your_property';
```

**Check 2**: Verify room_type in booking data matches room_list
```sql
SELECT DISTINCT room_type
FROM iwatoyama
WHERE room_type NOT IN (
    SELECT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(room_list, ',', numbers.n), ',', -1))
    FROM property_sheets
    CROSS JOIN (
        SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        -- Add more numbers as needed
    ) numbers
    WHERE property_name = 'iwatoyama'
);
```

### Problem: Property showing as guesthouse instead of hostel

**Solution**: Update property_type
```sql
UPDATE property_sheets
SET property_type = 'hostel',
    room_list = 'Room1,Room2,Room3'
WHERE property_name = 'your_property';
```

## Future Enhancements

Potential additions to make the system even more flexible:

1. **Room Capacity**: Add `room_capacity` column to store max occupancy per room type
2. **Room Pricing**: Store base pricing per room in property_sheets
3. **Room Status**: Add `room_status` to mark rooms as active/inactive/maintenance
4. **Seasonal Rooms**: Support different room_lists for different seasons

## Support

For questions or issues with this system, contact the development team or check the main project documentation at `DEPLOYMENT_GUIDE.md`.
