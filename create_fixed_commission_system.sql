-- Create Fixed Commission System for Properties
-- Properties using fixed commission: yura, konoha, isa, kaguya

USE `LAA0963548-occ`;

-- Step 1: Add commission_method column to property_sheets
ALTER TABLE `property_sheets`
ADD COLUMN `commission_method` ENUM('percentage', 'fixed') DEFAULT 'percentage'
COMMENT 'Commission calculation method: percentage or fixed fees';

-- Step 2: Create property_commission_settings table for fixed commission properties
-- NO DEFAULT VALUES - each property has unique amounts
CREATE TABLE IF NOT EXISTS `property_commission_settings` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_name VARCHAR(255) NOT NULL UNIQUE,
    commission_method ENUM('percentage', 'fixed') NOT NULL,

    -- Fixed monthly fees (UNIQUE PER PROPERTY)
    operation_management_fee DECIMAL(10,2) NOT NULL COMMENT 'OP業務委託料 - Monthly fixed fee (unique per property)',
    monthly_inspection_fee DECIMAL(10,2) NOT NULL COMMENT '月1回定期点検 - Monthly inspection/special cleaning (unique per property)',

    -- Per-booking fees (UNIQUE PER PROPERTY)
    checkout_cleaning_fee DECIMAL(10,2) NOT NULL COMMENT 'OUT後清掃 - Cleaning after checkout (unique per property)',
    stay_cleaning_fee DECIMAL(10,2) NOT NULL COMMENT '連泊時ステイ清掃 - Cleaning during stay (unique per property)',

    -- Per-person fees (UNIQUE PER PROPERTY)
    linen_fee_per_person DECIMAL(10,2) NOT NULL COMMENT 'リネン費 - Linen fee per person (unique per property)',

    -- Other variable fees (UNIQUE PER PROPERTY)
    other_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT 'その他 - Other variable fees',

    -- Stay cleaning configuration (can be different per property)
    stay_cleaning_trigger_days INT NOT NULL DEFAULT 3 COMMENT 'Number of days to trigger stay cleaning',

    -- Percentage commission (fallback for percentage method)
    percentage_rate DECIMAL(5,2) DEFAULT 15.00 COMMENT 'Commission percentage if using percentage method',

    -- Metadata
    notes TEXT COMMENT 'Additional notes about commission structure',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_property_name (property_name),
    INDEX idx_commission_method (commission_method),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (property_name) REFERENCES property_sheets(property_name) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fixed commission settings - EACH PROPERTY HAS UNIQUE AMOUNTS';

-- Step 3: Update property_sheets to mark properties as using fixed commission
UPDATE `property_sheets`
SET commission_method = 'fixed'
WHERE property_name IN ('yura', 'konoha', 'isa', 'kaguya');

-- Step 4: Insert fixed commission settings for yura, konoha, isa, kaguya
-- ⚠️ IMPORTANT: Replace the 0.00 values with actual amounts for EACH property
-- Each property has DIFFERENT amounts - update all values before running!

INSERT INTO `property_commission_settings` (
    property_name,
    commission_method,
    operation_management_fee,
    monthly_inspection_fee,
    checkout_cleaning_fee,
    stay_cleaning_fee,
    linen_fee_per_person,
    other_fee,
    stay_cleaning_trigger_days,
    percentage_rate,
    notes
) VALUES
-- YURA - Replace all 0.00 with actual amounts
(
    'yura',
    'fixed',
    0.00,  -- OP業務委託料 (Replace with actual amount)
    0.00,  -- 月1回定期点検 (Replace with actual amount)
    0.00,  -- OUT後清掃 (Replace with actual amount)
    0.00,  -- 連泊時ステイ清掃 (Replace with actual amount)
    0.00,  -- リネン費/人 (Replace with actual amount)
    0.00,  -- その他
    3,     -- Stay cleaning trigger days
    15.00, -- Fallback percentage
    'Fixed commission structure - YURA specific amounts'
),
-- KONOHA - Replace all 0.00 with actual amounts
(
    'konoha',
    'fixed',
    0.00,  -- OP業務委託料 (Replace with actual amount)
    0.00,  -- 月1回定期点検 (Replace with actual amount)
    0.00,  -- OUT後清掃 (Replace with actual amount)
    0.00,  -- 連泊時ステイ清掃 (Replace with actual amount)
    0.00,  -- リネン費/人 (Replace with actual amount)
    0.00,  -- その他
    3,     -- Stay cleaning trigger days
    15.00, -- Fallback percentage
    'Fixed commission structure - KONOHA specific amounts'
),
-- ISA - Replace all 0.00 with actual amounts
(
    'isa',
    'fixed',
    0.00,  -- OP業務委託料 (Replace with actual amount)
    0.00,  -- 月1回定期点検 (Replace with actual amount)
    0.00,  -- OUT後清掃 (Replace with actual amount)
    0.00,  -- 連泊時ステイ清掃 (Replace with actual amount)
    0.00,  -- リネン費/人 (Replace with actual amount)
    0.00,  -- その他
    3,     -- Stay cleaning trigger days
    15.00, -- Fallback percentage
    'Fixed commission structure - ISA specific amounts'
),
-- KAGUYA - Replace all 0.00 with actual amounts
(
    'kaguya',
    'fixed',
    0.00,  -- OP業務委託料 (Replace with actual amount)
    0.00,  -- 月1回定期点検 (Replace with actual amount)
    0.00,  -- OUT後清掃 (Replace with actual amount)
    0.00,  -- 連泊時ステイ清掃 (Replace with actual amount)
    0.00,  -- リネン費/人 (Replace with actual amount)
    0.00,  -- その他
    3,     -- Stay cleaning trigger days
    15.00, -- Fallback percentage
    'Fixed commission structure - KAGUYA specific amounts'
)
ON DUPLICATE KEY UPDATE
    commission_method = VALUES(commission_method),
    operation_management_fee = VALUES(operation_management_fee),
    monthly_inspection_fee = VALUES(monthly_inspection_fee),
    checkout_cleaning_fee = VALUES(checkout_cleaning_fee),
    stay_cleaning_fee = VALUES(stay_cleaning_fee),
    linen_fee_per_person = VALUES(linen_fee_per_person),
    other_fee = VALUES(other_fee),
    stay_cleaning_trigger_days = VALUES(stay_cleaning_trigger_days),
    percentage_rate = VALUES(percentage_rate),
    notes = VALUES(notes);

-- Step 5: Create a view for easy commission calculation
CREATE OR REPLACE VIEW `property_commission_view` AS
SELECT
    ps.property_name,
    ps.property_type,
    ps.total_rooms,
    COALESCE(ps.commission_method, 'percentage') as commission_method,
    pcs.operation_management_fee,
    pcs.monthly_inspection_fee,
    pcs.checkout_cleaning_fee,
    pcs.stay_cleaning_fee,
    pcs.linen_fee_per_person,
    pcs.other_fee,
    pcs.stay_cleaning_trigger_days,
    pcs.percentage_rate,
    pcs.notes
FROM property_sheets ps
LEFT JOIN property_commission_settings pcs ON ps.property_name = pcs.property_name
WHERE ps.is_active = TRUE;

-- Step 6: Create stored procedure to calculate stay cleanings
DELIMITER //
DROP PROCEDURE IF EXISTS CalculateStayCleanings //
CREATE PROCEDURE CalculateStayCleanings(
    IN night_count INT,
    IN trigger_days INT,
    OUT stay_cleaning_count INT
)
BEGIN
    -- Calculate how many stay cleanings are needed
    -- Formula: For every 'trigger_days' (default 3), one cleaning
    -- Example: 7 days = 2 cleanings (day 3 and day 6)
    --          3 days = 1 cleaning (day 3)
    --          2 days = 0 cleanings

    IF night_count >= trigger_days THEN
        SET stay_cleaning_count = FLOOR(night_count / trigger_days);
    ELSE
        SET stay_cleaning_count = 0;
    END IF;
END //
DELIMITER ;

-- Step 7: Create stored procedure to calculate total commission for a booking
DELIMITER //
DROP PROCEDURE IF EXISTS CalculateBookingCommission //
CREATE PROCEDURE CalculateBookingCommission(
    IN prop_name VARCHAR(255),
    IN accommodation_fee DECIMAL(10,2),
    IN night_count INT,
    IN people_count INT,
    OUT total_commission DECIMAL(10,2),
    OUT breakdown JSON
)
BEGIN
    DECLARE method VARCHAR(20);
    DECLARE checkout_fee DECIMAL(10,2) DEFAULT 0;
    DECLARE stay_fee DECIMAL(10,2) DEFAULT 0;
    DECLARE linen_fee DECIMAL(10,2) DEFAULT 0;
    DECLARE stay_cleanings INT DEFAULT 0;
    DECLARE percentage DECIMAL(5,2) DEFAULT 15;
    DECLARE trigger_days INT DEFAULT 3;

    -- Get commission settings
    SELECT
        commission_method,
        checkout_cleaning_fee,
        stay_cleaning_fee,
        linen_fee_per_person,
        stay_cleaning_trigger_days,
        percentage_rate
    INTO
        method,
        checkout_fee,
        stay_fee,
        linen_fee,
        trigger_days,
        percentage
    FROM property_commission_settings
    WHERE property_name = prop_name
    LIMIT 1;

    -- If no settings found, use percentage method
    IF method IS NULL THEN
        SET method = 'percentage';
    END IF;

    IF method = 'fixed' THEN
        -- Calculate stay cleanings
        CALL CalculateStayCleanings(night_count, trigger_days, stay_cleanings);

        -- Calculate total commission
        SET total_commission =
            checkout_fee +                           -- Check-out cleaning
            (stay_cleanings * stay_fee) +           -- Stay cleanings
            (people_count * linen_fee);             -- Linen fees

        -- Create breakdown JSON
        SET breakdown = JSON_OBJECT(
            'method', 'fixed',
            'checkout_cleaning_fee', checkout_fee,
            'stay_cleaning_count', stay_cleanings,
            'stay_cleaning_fee_per_time', stay_fee,
            'total_stay_cleaning_fee', stay_cleanings * stay_fee,
            'people_count', people_count,
            'linen_fee_per_person', linen_fee,
            'total_linen_fee', people_count * linen_fee,
            'total_commission', total_commission
        );
    ELSE
        -- Use percentage method
        SET total_commission = accommodation_fee * (percentage / 100);

        SET breakdown = JSON_OBJECT(
            'method', 'percentage',
            'percentage_rate', percentage,
            'accommodation_fee', accommodation_fee,
            'total_commission', total_commission
        );
    END IF;
END //
DELIMITER ;

-- Display success message and summary
SELECT '✅ Fixed Commission System Created Successfully!' as Status;

SELECT 'Properties using FIXED commission:' as Info;
SELECT property_name, commission_method
FROM property_sheets
WHERE commission_method = 'fixed';

SELECT 'Fixed Commission Settings:' as Info;
SELECT
    property_name,
    operation_management_fee as 'OP業務委託料',
    monthly_inspection_fee as '月1回定期点検',
    checkout_cleaning_fee as 'OUT後清掃',
    stay_cleaning_fee as '連泊時ステイ清掃',
    linen_fee_per_person as 'リネン費/人',
    stay_cleaning_trigger_days as '清掃発生日数'
FROM property_commission_settings;

-- Show example calculations
SELECT '📊 Example Commission Calculations:' as Info;

SELECT
    'Example 1: 2-day stay, 2 people' as Booking,
    8000 as 'OUT後清掃',
    0 as 'Stay清掃回数',
    0 as 'Stay清掃費用',
    2 as '人数',
    1000 as 'リネン費用',
    9000 as '合計' UNION ALL
SELECT
    'Example 2: 3-day stay, 2 people',
    8000,
    1,
    6000,
    2,
    1000,
    15000 UNION ALL
SELECT
    'Example 3: 7-day stay, 4 people',
    8000,
    2,
    12000,
    4,
    2000,
    22000;
