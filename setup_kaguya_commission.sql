-- Quick Setup for Kaguya Monthly Commission System
-- Run this entire file in phpMyAdmin

USE `LAA0963548-occ`;

-- Step 1: Create the kaguya_monthly_commission table
CREATE TABLE IF NOT EXISTS `kaguya_monthly_commission` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    month INT NOT NULL,
    total_sales DECIMAL(12,2) NOT NULL COMMENT 'Total sales for the month',
    owner_payment DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Payment to property owner',
    exseed_commission DECIMAL(12,2) NOT NULL COMMENT 'Commission for Exseed',
    commission_percentage DECIMAL(5,2) NOT NULL COMMENT 'Commission percentage for the month',
    notes TEXT COMMENT 'Additional notes about this month',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year_month (year, month),
    INDEX idx_year_month (year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Kaguya property monthly commission data';

-- Step 2: Check if property_commission_settings table has Kaguya
-- If yes, we need to remove it or mark it to use monthly table
-- Check first if the table exists
SELECT COUNT(*) as kaguya_in_fixed_table
FROM information_schema.tables
WHERE table_schema = 'LAA0963548-occ'
AND table_name = 'property_commission_settings';

-- If kaguya exists in property_commission_settings, remove it
-- (Comment out if you want to keep it)
DELETE FROM `property_commission_settings` WHERE property_name = 'kaguya';

-- Step 3: Create view for easy access
CREATE OR REPLACE VIEW `kaguya_commission_view` AS
SELECT
    `year`,
    `month`,
    total_sales,
    owner_payment,
    exseed_commission,
    commission_percentage,
    notes
FROM kaguya_monthly_commission;

-- Display success message
SELECT '✅ Kaguya Monthly Commission Table Created!' as Status;
SELECT 'Now click the Import Data button in the dashboard to fetch commission data from Google Sheets' as 'Next Step';
