-- Add Additional Fixed Fees to Commission System
-- 駆け付け要員 (Emergency Response Staff)
-- ゴミ回収費用 (Garbage Collection Fee)

USE `LAA0963548-occ`;

-- Add new columns to property_commission_settings table
ALTER TABLE `property_commission_settings`
ADD COLUMN `emergency_staff_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '駆け付け要員（固定） - Emergency response staff (monthly fixed fee)',
ADD COLUMN `garbage_collection_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ゴミ回収費用 - Garbage collection fee (monthly fixed fee)';

-- Display success message
SELECT '✅ Additional fixed fee columns added successfully!' as Status;

-- Show updated table structure
SELECT 'Updated property_commission_settings columns:' as Info;
DESCRIBE property_commission_settings;
