<?php
/**
 * Check Kaguya Table Status
 * This script checks if the kaguya table exists and if it's empty
 */

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

// Set content type to plain text for better readability
header("Content-Type: text/plain; charset=utf-8");

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "=== KAGUYA TABLE STATUS CHECK ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

    // 1. Check if kaguya exists in property_sheets table
    echo "1. Checking 'kaguya' in property_sheets table...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("SELECT * FROM property_sheets WHERE property_name = 'kaguya'");
    $stmt->execute();
    $propertyRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($propertyRecord) {
        echo "✅ Found kaguya in property_sheets table\n\n";
        echo "Property Details:\n";
        foreach ($propertyRecord as $key => $value) {
            if ($key === 'google_sheet_url' && strlen($value) > 80) {
                echo sprintf("  %-20s: %s...\n", $key, substr($value, 0, 80));
            } else {
                echo sprintf("  %-20s: %s\n", $key, $value);
            }
        }
    } else {
        echo "❌ No 'kaguya' record found in property_sheets table\n";
        echo "   You may need to run add_kaguya_hostel.sql first!\n";
    }

    echo "\n";

    // 2. Check if kaguya booking table exists
    echo "2. Checking if 'kaguya' booking table exists...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("SHOW TABLES LIKE 'kaguya'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "✅ Table 'kaguya' exists\n\n";

        // 3. Check row count
        echo "3. Checking row count in kaguya table...\n";
        echo str_repeat("-", 60) . "\n";

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM kaguya");
        $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRows = $countResult['count'];

        echo "Total rows in kaguya table: $totalRows\n\n";

        if ($totalRows == 0) {
            echo "⚠️  WARNING: The kaguya table is EMPTY!\n";
            echo "   No booking data found. You may need to:\n";
            echo "   - Import data from Google Sheets\n";
            echo "   - Check if the import process is working correctly\n";
        } else {
            echo "✅ The kaguya table has $totalRows booking record(s)\n\n";

            // Show table structure
            echo "4. Table structure:\n";
            echo str_repeat("-", 60) . "\n";
            $stmt = $pdo->query("DESCRIBE kaguya");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo sprintf("%-25s %-20s %-10s %-10s\n", "Column", "Type", "Null", "Key");
            echo str_repeat("-", 60) . "\n";
            foreach ($columns as $column) {
                echo sprintf("%-25s %-20s %-10s %-10s\n",
                    $column['Field'],
                    $column['Type'],
                    $column['Null'],
                    $column['Key']
                );
            }

            echo "\n";

            // Show sample data
            echo "5. Sample data (first 5 rows):\n";
            echo str_repeat("-", 60) . "\n";

            $stmt = $pdo->query("SELECT * FROM kaguya ORDER BY check_in DESC LIMIT 5");
            $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($sampleData)) {
                echo "No data to display\n";
            } else {
                foreach ($sampleData as $index => $row) {
                    echo "\nRecord #" . ($index + 1) . ":\n";
                    foreach ($row as $key => $value) {
                        echo sprintf("  %-25s: %s\n", $key, $value);
                    }
                }
            }

            // Show room type breakdown
            echo "\n\n6. Room type breakdown:\n";
            echo str_repeat("-", 60) . "\n";

            $stmt = $pdo->query("
                SELECT
                    room_type,
                    COUNT(*) as booking_count,
                    MIN(check_in) as first_booking,
                    MAX(check_out) as last_booking
                FROM kaguya
                WHERE room_type IS NOT NULL AND room_type != ''
                GROUP BY room_type
                ORDER BY booking_count DESC
            ");
            $roomBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($roomBreakdown)) {
                echo "No room type data found\n";
            } else {
                echo sprintf("%-20s %15s %15s %15s\n", "Room Type", "Bookings", "First Booking", "Last Booking");
                echo str_repeat("-", 60) . "\n";
                foreach ($roomBreakdown as $room) {
                    echo sprintf("%-20s %15s %15s %15s\n",
                        $room['room_type'],
                        $room['booking_count'],
                        $room['first_booking'],
                        $room['last_booking']
                    );
                }
            }

            // Show date range of bookings
            echo "\n\n7. Booking date range:\n";
            echo str_repeat("-", 60) . "\n";

            $stmt = $pdo->query("
                SELECT
                    MIN(check_in) as earliest_checkin,
                    MAX(check_out) as latest_checkout,
                    MIN(booking_date) as earliest_booking_date,
                    MAX(booking_date) as latest_booking_date
                FROM kaguya
            ");
            $dateRange = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "Earliest check-in:     " . ($dateRange['earliest_checkin'] ?? 'N/A') . "\n";
            echo "Latest check-out:      " . ($dateRange['latest_checkout'] ?? 'N/A') . "\n";
            echo "Earliest booking date: " . ($dateRange['earliest_booking_date'] ?? 'N/A') . "\n";
            echo "Latest booking date:   " . ($dateRange['latest_booking_date'] ?? 'N/A') . "\n";
        }

    } else {
        echo "❌ Table 'kaguya' does NOT exist\n";
        echo "   The table needs to be created. This usually happens when:\n";
        echo "   - Data is first imported from Google Sheets\n";
        echo "   - Or the table is manually created\n";
    }

    // 8. Check for kaguya_monthly_commission table (NEW!)
    echo "\n\n8. Checking 'kaguya_monthly_commission' table...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("SHOW TABLES LIKE 'kaguya_monthly_commission'");
    $commissionTableExists = $stmt->rowCount() > 0;

    if ($commissionTableExists) {
        echo "✅ Table 'kaguya_monthly_commission' EXISTS!\n\n";

        // Check row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM kaguya_monthly_commission");
        $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRows = $countResult['count'];

        echo "Total months in table: $totalRows\n\n";

        if ($totalRows == 0) {
            echo "⚠️  WARNING: The kaguya_monthly_commission table is EMPTY!\n";
            echo "   You need to click 'Import Data' button to populate it.\n\n";
        } else {
            echo "✅ Commission data found for $totalRows month(s)\n\n";

            // Show all commission data
            echo "Commission data:\n";
            $stmt = $pdo->query("
                SELECT year, month, total_sales, owner_payment, exseed_commission, commission_percentage, notes
                FROM kaguya_monthly_commission
                ORDER BY year DESC, month DESC
            ");
            $commissionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo sprintf("%-10s %-15s %-15s %-18s %-8s %s\n",
                "Year/Month", "Total Sales", "Owner Payment", "Exseed Commission", "Rate %", "Notes");
            echo str_repeat("-", 100) . "\n";

            foreach ($commissionData as $row) {
                echo sprintf("%d/%02d      ¥%-13s ¥%-13s ¥%-16s %6.1f%%  %s\n",
                    $row['year'],
                    $row['month'],
                    number_format($row['total_sales']),
                    number_format($row['owner_payment']),
                    number_format($row['exseed_commission']),
                    $row['commission_percentage'],
                    $row['notes'] ?? ''
                );
            }
        }
    } else {
        echo "❌ Table 'kaguya_monthly_commission' DOES NOT EXIST!\n\n";
        echo "🚨 THIS IS THE PROBLEM! 🚨\n\n";
        echo "Without this table, Kaguya uses the wrong calculation method.\n";
        echo "It will fall back to fixed commission (like konoha/yura).\n\n";
        echo "TO FIX:\n";
        echo "1. Open phpMyAdmin (https://your-server/phpmyadmin)\n";
        echo "2. Select database: LAA0963548-occ\n";
        echo "3. Go to SQL tab\n";
        echo "4. Copy and paste the contents of setup_kaguya_commission.sql\n";
        echo "5. Click 'Go' to run it\n";
        echo "6. Then click 'Import Data' button in your dashboard\n";
    }

    // 9. Check if Kaguya is in property_commission_settings (should NOT be there)
    echo "\n\n9. Checking 'property_commission_settings' table...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("SELECT * FROM property_commission_settings WHERE property_name = 'kaguya'");
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo "⚠️  WARNING: Kaguya found in property_commission_settings!\n\n";
        echo "This causes it to use fixed commission method (like konoha/yura).\n";
        echo "The setup_kaguya_commission.sql file will remove this.\n\n";

        $kaguyaSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Current settings:\n";
        foreach ($kaguyaSettings as $key => $value) {
            echo sprintf("  %-30s: %s\n", $key, $value);
        }
    } else {
        echo "✅ Kaguya NOT in property_commission_settings (good!)\n";
        echo "   Kaguya should only use the monthly commission table.\n";
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Check completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ DATABASE ERROR:\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "- Database connection settings\n";
    echo "- Database server is accessible\n";
    echo "- User has proper permissions\n";
} catch (Exception $e) {
    echo "❌ ERROR:\n";
    echo $e->getMessage() . "\n";
}
?>
