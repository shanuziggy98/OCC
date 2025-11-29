-- Complete Property Sheets Setup with Property Type System
-- This is a complete fresh setup that includes property_type and room_list columns
USE `LAA0963548-occ`;

-- Drop and recreate the table with new schema
DROP TABLE IF EXISTS property_sheets;

CREATE TABLE property_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL UNIQUE,
    google_sheet_url TEXT NOT NULL,
    sheet_description TEXT,
    property_type ENUM('hostel', 'guesthouse') DEFAULT 'guesthouse' COMMENT 'hostel=has multiple rooms, guesthouse=single unit',
    room_list TEXT COMMENT 'Comma-separated list of room names for hostels (e.g., "ダブル203,ダブル204,ダブル205")',
    total_rooms INT DEFAULT 1 COMMENT 'Total number of rooms/units in this property',
    is_active BOOLEAN DEFAULT TRUE,
    last_imported TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property_name (property_name),
    INDEX idx_active (is_active),
    INDEX idx_last_imported (last_imported),
    INDEX idx_property_type (property_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert all properties with proper configuration
-- HOSTELS (properties with multiple rooms)
INSERT INTO property_sheets (
    property_name,
    google_sheet_url,
    sheet_description,
    property_type,
    room_list,
    total_rooms
) VALUES
(
    'iwatoyama',
    'https://docs.google.com/spreadsheets/d/YOUR_IWATOYAMA_SHEET_ID/edit',
    'Iwatoyama Hostel - 38 rooms',
    'hostel',
    '岩戸山全体,ファミリー401,共用D402A,共用D402B,共用D402C,共用D402D,共用D402E,共用D402F,ダブル403,ダブル404,ダブル405,ユニーク406,ユニーク407,ツイン408,ファミリー301,女子D302A,女子D302B,女子D302C,女子D302D,女子D302E,女子D302F,ダブル303,ダブル304,ダブル305,ユニーク306,ユニーク307,ツイン308,男子D202A,男子D202B,男子D202C,男子D202D,男子D202E,男子D202F,ダブル203,ダブル204,ダブル205,ユニーク206,ユニーク207,ツイン208',
    38
),
(
    'goettingen',
    'https://docs.google.com/spreadsheets/d/YOUR_GOETTINGEN_SHEET_ID/edit',
    'Goettingen Hostel - 11 rooms',
    'hostel',
    '月沈原101,月沈原102,月沈原201,月沈原202,月沈原203,月沈原204,月沈原205,月沈原301,月沈原302,月沈原303,月沈原304',
    11
),
(
    'littlehouse',
    'https://docs.google.com/spreadsheets/d/YOUR_LITTLEHOUSE_SHEET_ID/edit',
    'Little House - 3 rooms',
    'hostel',
    'いぬねこ1F,いぬねこ2F,秘密の部屋',
    3
);

-- GUESTHOUSES (properties with single units)
INSERT INTO property_sheets (
    property_name,
    google_sheet_url,
    sheet_description,
    property_type,
    total_rooms
) VALUES
('comodita', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Comodita Guesthouse', 'guesthouse', 1),
('mujurin', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Mujurin Guesthouse', 'guesthouse', 1),
('fujinomori', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Fujinomori Guesthouse', 'guesthouse', 1),
('enraku', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Enraku Guesthouse', 'guesthouse', 1),
('tsubaki', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Tsubaki Guesthouse', 'guesthouse', 1),
('hiiragi', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Hiiragi Guesthouse', 'guesthouse', 1),
('fushimi_apt', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Fushimi Apartment', 'guesthouse', 1),
('kanon', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Kanon Guesthouse', 'guesthouse', 1),
('fushimi_house', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Fushimi House', 'guesthouse', 1),
('kado', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Kado Guesthouse', 'guesthouse', 1),
('tanuki', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Tanuki Guesthouse', 'guesthouse', 1),
('fukuro', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Fukuro Guesthouse', 'guesthouse', 1),
('hauwa_apt', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Hauwa Apartment', 'guesthouse', 1),
('yanagawa', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Yanagawa Guesthouse', 'guesthouse', 1),
('nishijin_fujita', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Nishijin Fujita Guesthouse', 'guesthouse', 1),
('rikyu', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Rikyu Guesthouse', 'guesthouse', 1),
('hiroshima', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Hiroshima Guesthouse', 'guesthouse', 1),
('okinawa', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Okinawa Guesthouse', 'guesthouse', 1),
('ryoma', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Ryoma Guesthouse', 'guesthouse', 1),
('isa', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Isa Guesthouse', 'guesthouse', 1),
('yura', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Yura Guesthouse', 'guesthouse', 1),
('konoha', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit', 'Konoha Guesthouse', 'guesthouse', 1);

-- Create view to show property sheets with last import status
CREATE OR REPLACE VIEW property_sheets_status AS
SELECT
    ps.id,
    ps.property_name,
    ps.google_sheet_url,
    ps.sheet_description,
    ps.property_type,
    ps.room_list,
    ps.total_rooms,
    ps.is_active,
    ps.last_imported,
    ps.created_at,
    ps.updated_at,
    COUNT(b.id) as total_bookings,
    MAX(b.created_at) as latest_booking_import
FROM property_sheets ps
LEFT JOIN bookings b ON ps.property_name = b.property_name
GROUP BY ps.id, ps.property_name, ps.google_sheet_url, ps.sheet_description,
         ps.property_type, ps.room_list, ps.total_rooms, ps.is_active,
         ps.last_imported, ps.created_at, ps.updated_at;

-- Create stored procedure to add new property sheet
DELIMITER //
DROP PROCEDURE IF EXISTS AddPropertySheet //
CREATE PROCEDURE AddPropertySheet(
    IN prop_name VARCHAR(255),
    IN sheet_url TEXT,
    IN description TEXT,
    IN prop_type ENUM('hostel', 'guesthouse'),
    IN rooms_list TEXT
)
BEGIN
    DECLARE room_count INT DEFAULT 1;

    -- Calculate total rooms if it's a hostel with room list
    IF prop_type = 'hostel' AND rooms_list IS NOT NULL AND rooms_list != '' THEN
        SET room_count = CHAR_LENGTH(rooms_list) - CHAR_LENGTH(REPLACE(rooms_list, ',', '')) + 1;
    END IF;

    INSERT INTO property_sheets (
        property_name,
        google_sheet_url,
        sheet_description,
        property_type,
        room_list,
        total_rooms
    )
    VALUES (prop_name, sheet_url, description, prop_type, rooms_list, room_count)
    ON DUPLICATE KEY UPDATE
        google_sheet_url = sheet_url,
        sheet_description = description,
        property_type = prop_type,
        room_list = rooms_list,
        total_rooms = room_count,
        updated_at = CURRENT_TIMESTAMP;

    -- Also ensure the property exists in the properties table if it exists
    INSERT IGNORE INTO properties (name, type, total_rooms)
    VALUES (prop_name, prop_type, room_count);
END //
DELIMITER ;

-- Create stored procedure to get all active property sheets
DELIMITER //
DROP PROCEDURE IF EXISTS GetActivePropertySheets //
CREATE PROCEDURE GetActivePropertySheets()
BEGIN
    SELECT
        property_name,
        google_sheet_url,
        sheet_description,
        property_type,
        room_list,
        total_rooms,
        last_imported,
        (SELECT COUNT(*) FROM bookings WHERE property_name = ps.property_name) as booking_count
    FROM property_sheets ps
    WHERE is_active = TRUE
    ORDER BY display_order, property_name;
END //
DELIMITER ;

-- Create stored procedure to get hostel room list
DELIMITER //
DROP PROCEDURE IF EXISTS GetHostelRooms //
CREATE PROCEDURE GetHostelRooms(IN prop_name VARCHAR(255))
BEGIN
    SELECT
        property_name,
        property_type,
        room_list,
        total_rooms
    FROM property_sheets
    WHERE property_name = prop_name
    AND property_type = 'hostel'
    AND is_active = TRUE;
END //
DELIMITER ;

-- Display summary
SELECT '✅ Property Sheets Table Created Successfully!' as Status;

SELECT
    property_type,
    COUNT(*) as count,
    SUM(total_rooms) as total_rooms
FROM property_sheets
GROUP BY property_type
ORDER BY property_type DESC;

SELECT '🏨 Hostel Properties:' as Info;
SELECT
    property_name,
    total_rooms,
    LEFT(room_list, 50) as sample_rooms
FROM property_sheets
WHERE property_type = 'hostel'
ORDER BY property_name;

SELECT '🏠 Guesthouse Properties:' as Info;
SELECT
    property_name,
    sheet_description
FROM property_sheets
WHERE property_type = 'guesthouse'
ORDER BY property_name;
