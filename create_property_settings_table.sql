-- Create property settings table for commission and cleaning fee management
-- This table stores commission calculation methods and fees for properties and individual rooms

USE `LAA0963548-occ`;

-- Drop table if exists
DROP TABLE IF EXISTS property_settings;

-- Create the property_settings table
CREATE TABLE property_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL,
    room_name VARCHAR(255) DEFAULT NULL COMMENT 'NULL means this setting applies to the whole property',
    commission_percent DECIMAL(5,2) DEFAULT 15.00 COMMENT 'Commission percentage (e.g., 15.00 for 15%)',
    commission_calculation_method ENUM('regular', 'com_option1', 'com_option2') DEFAULT 'regular' COMMENT 'Method for calculating commission',
    cleaning_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Cleaning fee per booking',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_property_room (property_name, room_name),
    INDEX idx_property (property_name),
    INDEX idx_active (is_active),
    INDEX idx_calculation_method (commission_calculation_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores commission and cleaning fee settings for properties and rooms';

-- Insert default settings for all existing properties (whole property settings)
INSERT INTO property_settings (property_name, room_name, commission_percent, commission_calculation_method, cleaning_fee) VALUES
-- Hostels with multiple rooms
('iwatoyama', NULL, 58.00, 'regular', 4000.00),
('goettingen', NULL, 25.00, 'regular', 4000.00),
('littlehouse', NULL, 25.00, 'regular', 4200.00),
('kaguya', NULL, 25.00, 'regular', 4000.00),

-- Guesthouses
('comodita', NULL, 15.00, 'regular', 6050.00),
('mujurin', NULL, 15.00, 'regular', 13200.00),
('fujinomori', NULL, 15.00, 'regular', 7150.00),
('enraku', NULL, 15.00, 'regular', 4950.00),
('tsubaki', NULL, 15.00, 'regular', 8800.00),
('hiiragi', NULL, 15.00, 'regular', 8800.00),
('fushimi_apt', NULL, 15.00, 'regular', 6500.00),
('kanon', NULL, 15.00, 'regular', 4500.00),
('fushimi_house', NULL, 15.00, 'regular', 6000.00),
('kado', NULL, 15.00, 'regular', 6500.00),
('tanuki', NULL, 15.00, 'regular', 6500.00),
('fukuro', NULL, 15.00, 'regular', 6500.00),
('hauwa_apt', NULL, 15.00, 'regular', 0.00),
('yanagawa', NULL, 15.00, 'regular', 0.00),
('nishijin_fujita', NULL, 15.00, 'regular', 0.00),
('rikyu', NULL, 15.00, 'regular', 6600.00),
('hiroshima', NULL, 15.00, 'regular', 0.00),
('okinawa', NULL, 15.00, 'regular', 0.00),
('ryoma', NULL, 50.00, 'regular', 0.00),
('isa', NULL, 15.00, 'regular', 0.00),
('yura', NULL, 15.00, 'regular', 0.00),
('konoha', NULL, 15.00, 'regular', 8000.00);

-- Insert individual room settings for Kaguya hostel
INSERT INTO property_settings (property_name, room_name, commission_percent, commission_calculation_method, cleaning_fee) VALUES
('kaguya', '風の間', 25.00, 'regular', 4000.00),
('kaguya', '鳥の間', 25.00, 'regular', 4000.00),
('kaguya', '花の間', 25.00, 'regular', 4000.00);

-- Insert individual room settings for Littlehouse
INSERT INTO property_settings (property_name, room_name, commission_percent, commission_calculation_method, cleaning_fee) VALUES
('littlehouse', 'いぬねこ1F', 25.00, 'regular', 4200.00),
('littlehouse', 'いぬねこ2F', 25.00, 'regular', 4200.00),
('littlehouse', '秘密の部屋', 25.00, 'regular', 4200.00);

-- Insert individual room settings for Goettingen
INSERT INTO property_settings (property_name, room_name, commission_percent, commission_calculation_method, cleaning_fee) VALUES
('goettingen', '月沈原101', 25.00, 'regular', 4000.00),
('goettingen', '月沈原102', 25.00, 'regular', 4000.00),
('goettingen', '月沈原201', 25.00, 'regular', 4000.00),
('goettingen', '月沈原202', 25.00, 'regular', 4000.00),
('goettingen', '月沈原203', 25.00, 'regular', 4000.00),
('goettingen', '月沈原204', 25.00, 'regular', 4000.00),
('goettingen', '月沈原205', 25.00, 'regular', 4000.00),
('goettingen', '月沈原301', 25.00, 'regular', 4000.00),
('goettingen', '月沈原302', 25.00, 'regular', 4000.00),
('goettingen', '月沈原303', 25.00, 'regular', 4000.00),
('goettingen', '月沈原304', 25.00, 'regular', 4000.00);

-- Insert individual room settings for Iwatoyama (sample - you can add more as needed)
INSERT INTO property_settings (property_name, room_name, commission_percent, commission_calculation_method, cleaning_fee) VALUES
('iwatoyama', '岩戸山全体', 58.00, 'regular', 4000.00),
('iwatoyama', 'ファミリー401', 58.00, 'regular', 4000.00),
('iwatoyama', '共用D402A', 58.00, 'regular', 4000.00),
('iwatoyama', '共用D402B', 58.00, 'regular', 4000.00),
('iwatoyama', '共用D402C', 58.00, 'regular', 4000.00),
('iwatoyama', '共用D402D', 58.00, 'regular', 4000.00),
('iwatoyama', '共用D402E', 58.00, 'regular', 4000.00),
('iwatoyama', '共用D402F', 58.00, 'regular', 4000.00),
('iwatoyama', 'ダブル403', 58.00, 'regular', 4000.00),
('iwatoyama', 'ダブル404', 58.00, 'regular', 4000.00),
('iwatoyama', 'ダブル405', 58.00, 'regular', 4000.00),
('iwatoyama', 'ユニーク406', 58.00, 'regular', 4000.00),
('iwatoyama', 'ユニーク407', 58.00, 'regular', 4000.00),
('iwatoyama', 'ツイン408', 58.00, 'regular', 4000.00),
('iwatoyama', 'ファミリー301', 58.00, 'regular', 4000.00),
('iwatoyama', '女子D302A', 58.00, 'regular', 4000.00),
('iwatoyama', '女子D302B', 58.00, 'regular', 4000.00),
('iwatoyama', '女子D302C', 58.00, 'regular', 4000.00),
('iwatoyama', '女子D302D', 58.00, 'regular', 4000.00),
('iwatoyama', '女子D302E', 58.00, 'regular', 4000.00),
('iwatoyama', '女子D302F', 58.00, 'regular', 4000.00),
('iwatoyama', 'ダブル303', 58.00, 'regular', 4000.00),
('iwatoyama', 'ダブル304', 58.00, 'regular', 4000.00),
('iwatoyama', 'ダブル305', 58.00, 'regular', 4000.00),
('iwatoyama', 'ユニーク306', 58.00, 'regular', 4000.00),
('iwatoyama', 'ユニーク307', 58.00, 'regular', 4000.00),
('iwatoyama', 'ツイン308', 58.00, 'regular', 4000.00);

-- Create stored procedures for managing settings

-- Procedure to get settings for a property/room
DELIMITER //
DROP PROCEDURE IF EXISTS GetPropertySettings //
CREATE PROCEDURE GetPropertySettings(
    IN prop_name VARCHAR(255),
    IN room_name_param VARCHAR(255)
)
BEGIN
    -- Try to get room-specific setting first
    IF room_name_param IS NOT NULL THEN
        SELECT * FROM property_settings
        WHERE property_name = prop_name
        AND room_name = room_name_param
        AND is_active = TRUE
        LIMIT 1;
    ELSE
        -- Get property-level setting
        SELECT * FROM property_settings
        WHERE property_name = prop_name
        AND room_name IS NULL
        AND is_active = TRUE
        LIMIT 1;
    END IF;
END //

-- Procedure to update settings
DROP PROCEDURE IF EXISTS UpdatePropertySettings //
CREATE PROCEDURE UpdatePropertySettings(
    IN prop_name VARCHAR(255),
    IN room_name_param VARCHAR(255),
    IN commission_pct DECIMAL(5,2),
    IN calc_method VARCHAR(20),
    IN clean_fee DECIMAL(10,2)
)
BEGIN
    INSERT INTO property_settings (
        property_name,
        room_name,
        commission_percent,
        commission_calculation_method,
        cleaning_fee
    ) VALUES (
        prop_name,
        room_name_param,
        commission_pct,
        calc_method,
        clean_fee
    )
    ON DUPLICATE KEY UPDATE
        commission_percent = commission_pct,
        commission_calculation_method = calc_method,
        cleaning_fee = clean_fee,
        updated_at = CURRENT_TIMESTAMP;
END //
DELIMITER ;

-- Display summary
SELECT '✅ Property settings table created successfully!' as Status;

SELECT
    commission_calculation_method,
    COUNT(*) as count,
    AVG(commission_percent) as avg_commission,
    SUM(cleaning_fee) as total_cleaning_fees
FROM property_settings
WHERE is_active = TRUE
GROUP BY commission_calculation_method;

SELECT '📊 Sample property settings:' as Info;
SELECT
    property_name,
    room_name,
    commission_percent,
    commission_calculation_method,
    cleaning_fee
FROM property_settings
WHERE is_active = TRUE
ORDER BY property_name, room_name
LIMIT 20;
