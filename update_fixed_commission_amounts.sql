-- Template for Updating Fixed Commission Amounts
-- Each property has UNIQUE amounts - fill in the actual values for each property

USE `LAA0963548-occ`;

-- ========================================
-- YURA - Update all amounts
-- ========================================
UPDATE `property_commission_settings`
SET
    operation_management_fee = 0.00,    -- ⚠️ Replace: OP業務委託料 (Monthly)
    monthly_inspection_fee = 0.00,      -- ⚠️ Replace: 月1回定期点検 (Monthly)
    checkout_cleaning_fee = 0.00,       -- ⚠️ Replace: OUT後清掃 (Per booking)
    stay_cleaning_fee = 0.00,           -- ⚠️ Replace: 連泊時ステイ清掃 (Per cleaning)
    linen_fee_per_person = 0.00,        -- ⚠️ Replace: リネン費 (Per person)
    other_fee = 0.00,                   -- その他
    stay_cleaning_trigger_days = 3,     -- Days to trigger stay cleaning (usually 3)
    notes = 'YURA specific amounts'
WHERE property_name = 'yura';

-- ========================================
-- KONOHA - Update all amounts
-- ========================================
UPDATE `property_commission_settings`
SET
    operation_management_fee = 0.00,    -- ⚠️ Replace: OP業務委託料 (Monthly)
    monthly_inspection_fee = 0.00,      -- ⚠️ Replace: 月1回定期点検 (Monthly)
    checkout_cleaning_fee = 0.00,       -- ⚠️ Replace: OUT後清掃 (Per booking)
    stay_cleaning_fee = 0.00,           -- ⚠️ Replace: 連泊時ステイ清掃 (Per cleaning)
    linen_fee_per_person = 0.00,        -- ⚠️ Replace: リネン費 (Per person)
    other_fee = 0.00,                   -- その他
    stay_cleaning_trigger_days = 3,     -- Days to trigger stay cleaning (usually 3)
    notes = 'KONOHA specific amounts'
WHERE property_name = 'konoha';

-- ========================================
-- ISA - Update all amounts
-- ========================================
UPDATE `property_commission_settings`
SET
    operation_management_fee = 0.00,    -- ⚠️ Replace: OP業務委託料 (Monthly)
    monthly_inspection_fee = 0.00,      -- ⚠️ Replace: 月1回定期点検 (Monthly)
    checkout_cleaning_fee = 0.00,       -- ⚠️ Replace: OUT後清掃 (Per booking)
    stay_cleaning_fee = 0.00,           -- ⚠️ Replace: 連泊時ステイ清掃 (Per cleaning)
    linen_fee_per_person = 0.00,        -- ⚠️ Replace: リネン費 (Per person)
    other_fee = 0.00,                   -- その他
    stay_cleaning_trigger_days = 3,     -- Days to trigger stay cleaning (usually 3)
    notes = 'ISA specific amounts'
WHERE property_name = 'isa';

-- ========================================
-- KAGUYA - Update all amounts
-- ========================================
UPDATE `property_commission_settings`
SET
    operation_management_fee = 0.00,    -- ⚠️ Replace: OP業務委託料 (Monthly)
    monthly_inspection_fee = 0.00,      -- ⚠️ Replace: 月1回定期点検 (Monthly)
    checkout_cleaning_fee = 0.00,       -- ⚠️ Replace: OUT後清掃 (Per booking)
    stay_cleaning_fee = 0.00,           -- ⚠️ Replace: 連泊時ステイ清掃 (Per cleaning)
    linen_fee_per_person = 0.00,        -- ⚠️ Replace: リネン費 (Per person)
    other_fee = 0.00,                   -- その他
    stay_cleaning_trigger_days = 3,     -- Days to trigger stay cleaning (usually 3)
    notes = 'KAGUYA specific amounts'
WHERE property_name = 'kaguya';

-- ========================================
-- Verify the changes
-- ========================================
SELECT
    property_name as 'Property',
    operation_management_fee as 'OP業務委託料',
    monthly_inspection_fee as '月1回定期点検',
    checkout_cleaning_fee as 'OUT後清掃',
    stay_cleaning_fee as '連泊時ステイ清掃',
    linen_fee_per_person as 'リネン費/人',
    other_fee as 'その他',
    stay_cleaning_trigger_days as '清掃発生日数'
FROM property_commission_settings
WHERE property_name IN ('yura', 'konoha', 'isa', 'kaguya')
ORDER BY property_name;

-- ========================================
-- EXAMPLE: How to update a single property
-- ========================================
-- UPDATE property_commission_settings
-- SET
--     operation_management_fee = 55000.00,
--     checkout_cleaning_fee = 9000.00
-- WHERE property_name = 'yura';
