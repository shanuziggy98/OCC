-- Create table for daily occupancy records
-- Run this in phpMyAdmin

USE `LAA0963548-occ`;

CREATE TABLE IF NOT EXISTS `daily_occupancy_records` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(100) NOT NULL,
    record_date DATE NOT NULL,
    occupied_rooms INT NOT NULL DEFAULT 0,
    total_rooms INT NOT NULL DEFAULT 1,
    occupancy_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    booked_nights INT NOT NULL DEFAULT 0,
    booking_count INT NOT NULL DEFAULT 0,
    room_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_property_date (property_name, record_date),
    INDEX idx_property (property_name),
    INDEX idx_date (record_date),
    INDEX idx_property_date (property_name, record_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily occupancy records for all properties';

-- Create view for easy querying
CREATE OR REPLACE VIEW `daily_occupancy_view` AS
SELECT 
    property_name,
    record_date,
    occupied_rooms,
    total_rooms,
    occupancy_rate,
    booked_nights,
    booking_count,
    room_revenue,
    DATE_FORMAT(record_date, '%Y-%m-%d') as formatted_date,
    DATE_FORMAT(record_date, '%m/%d') as short_date
FROM daily_occupancy_records
ORDER BY property_name, record_date;

SELECT '✅ Daily Occupancy Table Created!' as Status;
