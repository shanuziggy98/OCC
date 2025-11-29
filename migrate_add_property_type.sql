-- Migration: Add property_type and room_list columns to property_sheets
-- Run this script to update your existing database
USE `LAA0963548-occ`;

-- Step 1: Add new columns to property_sheets table
ALTER TABLE property_sheets
ADD COLUMN IF NOT EXISTS property_type ENUM('hostel', 'guesthouse') DEFAULT 'guesthouse'
    COMMENT 'hostel=has multiple rooms, guesthouse=single unit' AFTER sheet_description,
ADD COLUMN IF NOT EXISTS room_list TEXT
    COMMENT 'Comma-separated list of room names for hostels (e.g., "ダブル203,ダブル204,ダブル205")' AFTER property_type,
ADD INDEX IF NOT EXISTS idx_property_type (property_type);

-- Step 2: Update iwatoyama as hostel with all room names
UPDATE property_sheets
SET
    property_type = 'hostel',
    room_list = '岩戸山全体,ファミリー401,共用D402A,共用D402B,共用D402C,共用D402D,共用D402E,共用D402F,ダブル403,ダブル404,ダブル405,ユニーク406,ユニーク407,ツイン408,ファミリー301,女子D302A,女子D302B,女子D302C,女子D302D,女子D302E,女子D302F,ダブル303,ダブル304,ダブル305,ユニーク306,ユニーク307,ツイン308,男子D202A,男子D202B,男子D202C,男子D202D,男子D202E,男子D202F,ダブル203,ダブル204,ダブル205,ユニーク206,ユニーク207,ツイン208'
WHERE property_name = 'iwatoyama';

-- Step 3: Update goettingen as hostel with all room names (note: case variations handled)
UPDATE property_sheets
SET
    property_type = 'hostel',
    room_list = '月沈原101,月沈原102,月沈原201,月沈原202,月沈原203,月沈原204,月沈原205,月沈原301,月沈原302,月沈原303,月沈原304'
WHERE property_name = 'goettingen' OR property_name = 'Goettingen';

-- Step 4: Update littlehouse as hostel with all room names
UPDATE property_sheets
SET
    property_type = 'hostel',
    room_list = 'いぬねこ1F,いぬねこ2F,秘密の部屋'
WHERE property_name = 'littlehouse' OR property_name = 'Little house';

-- Step 5: Set all other properties as guesthouse (they should already be default, but this ensures it)
UPDATE property_sheets
SET property_type = 'guesthouse'
WHERE property_name NOT IN ('iwatoyama', 'goettingen', 'Goettingen', 'littlehouse', 'Little house')
AND property_type IS NULL;

-- Step 6: Verify the changes
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
    END as room_count,
    CASE
        WHEN property_type = 'hostel' THEN
            SUBSTRING(room_list, 1, 50)
        ELSE NULL
    END as sample_rooms
FROM property_sheets
WHERE is_active = TRUE
ORDER BY property_type DESC, property_name;

-- Show summary
SELECT
    property_type,
    COUNT(*) as property_count
FROM property_sheets
WHERE is_active = TRUE
GROUP BY property_type;
