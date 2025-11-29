-- Automatically insert room-level settings from property_sheets.room_list
-- This script reads the room_list column and creates individual room settings
-- matching the property-level commission and cleaning fee settings

USE `LAA0963548-occ`;

-- Create a temporary procedure to split comma-separated room lists and insert room settings
DELIMITER //

DROP PROCEDURE IF EXISTS InsertRoomSettingsFromPropertySheets //

CREATE PROCEDURE InsertRoomSettingsFromPropertySheets()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE prop_name VARCHAR(255);
    DECLARE prop_type VARCHAR(50);
    DECLARE rooms_list TEXT;
    DECLARE commission_pct DECIMAL(5,2);
    DECLARE clean_fee DECIMAL(10,2);
    DECLARE calc_method VARCHAR(20);

    -- Cursor to get all hostels with room lists
    DECLARE property_cursor CURSOR FOR
        SELECT
            ps.property_name,
            ps.property_type,
            ps.room_list,
            COALESCE(pset.commission_percent, 15.00) as commission,
            COALESCE(pset.cleaning_fee, 5000.00) as cleaning,
            COALESCE(pset.commission_calculation_method, 'regular') as calc_method
        FROM property_sheets ps
        LEFT JOIN property_settings pset
            ON ps.property_name = pset.property_name
            AND pset.room_name IS NULL
        WHERE ps.property_type = 'hostel'
            AND ps.room_list IS NOT NULL
            AND ps.room_list != ''
            AND ps.is_active = TRUE;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN property_cursor;

    read_loop: LOOP
        FETCH property_cursor INTO prop_name, prop_type, rooms_list, commission_pct, clean_fee, calc_method;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Split the comma-separated room list and insert each room
        -- This uses a numbers table approach to split strings
        SET @room_insert_sql = CONCAT(
            'INSERT INTO property_settings (property_name, room_name, commission_percent, commission_calculation_method, cleaning_fee) ',
            'SELECT ''', prop_name, ''' as property_name, ',
            'TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(''', rooms_list, ''', '','', numbers.n), '','', -1)) as room_name, ',
            commission_pct, ' as commission_percent, ',
            '''', calc_method, ''' as commission_calculation_method, ',
            clean_fee, ' as cleaning_fee ',
            'FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 ',
            'UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 ',
            'UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 ',
            'UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 ',
            'UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 ',
            'UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30 ',
            'UNION SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION SELECT 35 ',
            'UNION SELECT 36 UNION SELECT 37 UNION SELECT 38 UNION SELECT 39 UNION SELECT 40) numbers ',
            'WHERE n <= 1 + LENGTH(''', rooms_list, ''') - LENGTH(REPLACE(''', rooms_list, ''', '','', '''')) ',
            'ON DUPLICATE KEY UPDATE ',
            'commission_percent = VALUES(commission_percent), ',
            'commission_calculation_method = VALUES(commission_calculation_method), ',
            'cleaning_fee = VALUES(cleaning_fee), ',
            'updated_at = CURRENT_TIMESTAMP'
        );

        PREPARE stmt FROM @room_insert_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

    END LOOP;

    CLOSE property_cursor;
END //

DELIMITER ;

-- Execute the procedure
CALL InsertRoomSettingsFromPropertySheets();

-- Clean up the procedure
DROP PROCEDURE IF EXISTS InsertRoomSettingsFromPropertySheets;

-- Display results
SELECT '✅ Room settings inserted successfully!' as Status;

SELECT
    property_name,
    COUNT(*) as room_count,
    AVG(commission_percent) as avg_commission,
    AVG(cleaning_fee) as avg_cleaning_fee
FROM property_settings
WHERE room_name IS NOT NULL
GROUP BY property_name
ORDER BY property_name;

SELECT '📊 Sample room settings:' as Info;
SELECT
    property_name,
    room_name,
    commission_percent,
    commission_calculation_method,
    cleaning_fee
FROM property_settings
WHERE room_name IS NOT NULL
ORDER BY property_name, room_name
LIMIT 30;
