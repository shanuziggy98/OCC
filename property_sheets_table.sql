-- Create table to store property names and Google Sheets links
USE `LAA0963548-occ`;

CREATE TABLE IF NOT EXISTS property_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL UNIQUE,
    google_sheet_url TEXT NOT NULL,
    sheet_description TEXT,
    property_type ENUM('hostel', 'guesthouse') DEFAULT 'guesthouse' COMMENT 'hostel=has multiple rooms, guesthouse=single unit',
    room_list TEXT COMMENT 'Comma-separated list of room names for hostels (e.g., "ダブル203,ダブル204,ダブル205")',
    is_active BOOLEAN DEFAULT TRUE,
    last_imported TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property_name (property_name),
    INDEX idx_active (is_active),
    INDEX idx_last_imported (last_imported),
    INDEX idx_property_type (property_type)
);

-- Insert sample data (replace with your actual property data)
INSERT INTO property_sheets (property_name, google_sheet_url, sheet_description) VALUES
('Hotel Tokyo Central', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID_1/edit#gid=0', 'Main booking data for Hotel Tokyo Central'),
('Guesthouse Kyoto Garden', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID_2/edit#gid=0', 'Booking records for Kyoto Garden Guesthouse'),
('Resort Osaka Bay', 'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID_3/edit#gid=0', 'Osaka Bay Resort booking information');

-- Create view to show property sheets with last import status
CREATE OR REPLACE VIEW property_sheets_status AS
SELECT
    ps.id,
    ps.property_name,
    ps.google_sheet_url,
    ps.sheet_description,
    ps.is_active,
    ps.last_imported,
    ps.created_at,
    ps.updated_at,
    COUNT(b.id) as total_bookings,
    MAX(b.created_at) as latest_booking_import
FROM property_sheets ps
LEFT JOIN bookings b ON ps.property_name = b.property_name
GROUP BY ps.id, ps.property_name, ps.google_sheet_url, ps.sheet_description, ps.is_active, ps.last_imported, ps.created_at, ps.updated_at;

-- Create stored procedure to add new property sheet
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS AddPropertySheet(
    IN prop_name VARCHAR(255),
    IN sheet_url TEXT,
    IN description TEXT
)
BEGIN
    INSERT INTO property_sheets (property_name, google_sheet_url, sheet_description)
    VALUES (prop_name, sheet_url, description)
    ON DUPLICATE KEY UPDATE
        google_sheet_url = sheet_url,
        sheet_description = description,
        updated_at = CURRENT_TIMESTAMP;

    -- Also ensure the property exists in the properties table
    INSERT IGNORE INTO properties (name, type, total_rooms)
    VALUES (prop_name, 'hotel', 1);
END //
DELIMITER ;

-- Create stored procedure to get all active property sheets
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS GetActivePropertySheets()
BEGIN
    SELECT
        property_name,
        google_sheet_url,
        sheet_description,
        last_imported,
        (SELECT COUNT(*) FROM bookings WHERE property_name = ps.property_name) as booking_count
    FROM property_sheets ps
    WHERE is_active = TRUE
    ORDER BY property_name;
END //
DELIMITER ;