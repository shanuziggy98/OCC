-- Create Kaguya Monthly Commission Table
-- This property has a unique commission structure based on monthly totals
-- Different from the standard fixed commission system

USE `LAA0963548-occ`;

-- Create table to store Kaguya's monthly commission data
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

    -- Ensure one record per month
    UNIQUE KEY unique_year_month (year, month),
    INDEX idx_year_month (year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Kaguya property monthly commission data - unique calculation method';

-- Data will be imported from Google Sheets using import_kaguya_commission.php
-- Run the import script after creating this table:
-- php import_kaguya_commission.php

-- Mark kaguya to use custom commission method
-- First, check if column exists
SET @dbname = DATABASE();
SET @tablename = 'property_commission_settings';
SET @columnname = 'custom_commission_table';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 'Column exists, skipping';",
  "ALTER TABLE property_commission_settings ADD COLUMN custom_commission_table VARCHAR(100) DEFAULT NULL COMMENT 'Custom commission table name for special calculations';"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update kaguya to use the custom monthly commission table
UPDATE `property_commission_settings`
SET custom_commission_table = 'kaguya_monthly_commission'
WHERE property_name = 'kaguya';

-- Create a view to easily access Kaguya commission data
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

-- Display the data
SELECT '✅ Kaguya Monthly Commission Table Created!' as Status;

SELECT 'Kaguya Historical Commission Data:' as Info;
SELECT
    CONCAT(year, '-', LPAD(month, 2, '0')) as 'Year-Month',
    FORMAT(total_sales, 0) as 'Total Sales',
    FORMAT(owner_payment, 0) as 'Owner Payment',
    FORMAT(exseed_commission, 0) as 'Exseed Commission',
    CONCAT(commission_percentage, '%') as 'Commission %',
    notes as 'Notes'
FROM kaguya_monthly_commission
ORDER BY year, month;

SELECT '📊 Commission Statistics:' as Info;
SELECT
    COUNT(*) as 'Total Months',
    FORMAT(AVG(total_sales), 0) as 'Avg Monthly Sales',
    FORMAT(AVG(exseed_commission), 0) as 'Avg Monthly Commission',
    CONCAT(ROUND(AVG(commission_percentage), 1), '%') as 'Avg Commission %'
FROM kaguya_monthly_commission;
