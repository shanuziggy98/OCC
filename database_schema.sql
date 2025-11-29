-- ExseedOcc Database Schema
-- Use existing Lolipop database
USE `LAA0963548-occ`;

-- Properties table to store property information
CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('hotel', 'guesthouse') NOT NULL DEFAULT 'hotel',
    total_rooms INT NOT NULL DEFAULT 0,
    address TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_type (type)
);

-- Bookings table to store imported booking data from Google Sheets
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    accommodation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    night_count INT NOT NULL DEFAULT 0,
    booking_date DATE,
    lead_time INT DEFAULT 0,
    guest_name VARCHAR(255),
    guest_email VARCHAR(255),
    room_type VARCHAR(100),
    special_requests TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property (property_name),
    INDEX idx_check_in (check_in),
    INDEX idx_check_out (check_out),
    INDEX idx_booking_date (booking_date),
    INDEX idx_date_range (check_in, check_out),
    FOREIGN KEY (property_name) REFERENCES properties(name) ON UPDATE CASCADE
);

-- Occupancy calculations cache table for performance
CREATE TABLE IF NOT EXISTS occupancy_calculations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL,
    calculation_date DATE NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_rooms INT NOT NULL,
    occupied_room_nights INT NOT NULL,
    available_room_nights INT NOT NULL,
    occupancy_rate DECIMAL(5,2) NOT NULL,
    daily_breakdown JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_calculation (property_name, start_date, end_date, total_rooms),
    INDEX idx_property_calc (property_name),
    INDEX idx_calc_date (calculation_date),
    INDEX idx_date_range_calc (start_date, end_date),
    FOREIGN KEY (property_name) REFERENCES properties(name) ON UPDATE CASCADE
);

-- Import logs table to track Google Sheets imports
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL,
    sheet_url TEXT NOT NULL,
    import_status ENUM('success', 'failed', 'partial') NOT NULL,
    records_imported INT DEFAULT 0,
    error_message TEXT,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_property_import (property_name),
    INDEX idx_import_date (import_date),
    INDEX idx_status (import_status)
);

-- Room types table (optional, for more detailed tracking)
CREATE TABLE IF NOT EXISTS room_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL,
    room_type_name VARCHAR(100) NOT NULL,
    room_count INT NOT NULL DEFAULT 1,
    base_price DECIMAL(10,2) DEFAULT 0.00,
    max_occupancy INT DEFAULT 2,
    amenities JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_room_type (property_name, room_type_name),
    INDEX idx_property_room (property_name),
    FOREIGN KEY (property_name) REFERENCES properties(name) ON UPDATE CASCADE
);

-- Insert some sample properties (you can remove this after testing)
INSERT IGNORE INTO properties (name, type, total_rooms, description) VALUES
('Sample Hotel Tokyo', 'hotel', 50, 'A sample hotel in Tokyo for testing'),
('Sample Guesthouse Kyoto', 'guesthouse', 12, 'A sample guesthouse in Kyoto for testing');

-- Create a view for easy occupancy reporting
CREATE OR REPLACE VIEW occupancy_summary AS
SELECT
    p.name as property_name,
    p.type as property_type,
    p.total_rooms,
    COUNT(b.id) as total_bookings,
    MIN(b.check_in) as first_booking_date,
    MAX(b.check_out) as last_booking_date,
    AVG(b.accommodation_fee) as avg_accommodation_fee,
    AVG(b.night_count) as avg_night_count,
    AVG(b.lead_time) as avg_lead_time
FROM properties p
LEFT JOIN bookings b ON p.name = b.property_name
GROUP BY p.id, p.name, p.type, p.total_rooms;

-- Create stored procedure for quick occupancy calculation
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CalculateOccupancyRate(
    IN prop_name VARCHAR(255),
    IN start_dt DATE,
    IN end_dt DATE,
    IN total_rm INT
)
BEGIN
    DECLARE total_nights INT;
    DECLARE occupied_nights INT;
    DECLARE occ_rate DECIMAL(5,2);

    -- Calculate total available room nights
    SET total_nights = DATEDIFF(end_dt, start_dt) * total_rm;

    -- Calculate occupied room nights
    SELECT COALESCE(SUM(
        CASE
            WHEN check_in >= start_dt AND check_out <= end_dt THEN night_count
            WHEN check_in < start_dt AND check_out > end_dt THEN DATEDIFF(end_dt, start_dt)
            WHEN check_in < start_dt AND check_out <= end_dt THEN DATEDIFF(check_out, start_dt)
            WHEN check_in >= start_dt AND check_out > end_dt THEN DATEDIFF(end_dt, check_in)
            ELSE 0
        END
    ), 0) INTO occupied_nights
    FROM bookings
    WHERE property_name = prop_name
    AND check_in < end_dt
    AND check_out > start_dt;

    -- Calculate occupancy rate
    IF total_nights > 0 THEN
        SET occ_rate = (occupied_nights / total_nights) * 100;
    ELSE
        SET occ_rate = 0;
    END IF;

    -- Return results
    SELECT
        prop_name as property_name,
        start_dt as start_date,
        end_dt as end_date,
        total_rm as total_rooms,
        occupied_nights as occupied_room_nights,
        total_nights as available_room_nights,
        occ_rate as occupancy_rate;
END //
DELIMITER ;

-- Create trigger to update properties when new bookings are added
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_property_on_booking
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO properties (name, type, total_rooms)
    VALUES (NEW.property_name, 'hotel', 1);
END //
DELIMITER ;