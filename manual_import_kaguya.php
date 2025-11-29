<?php
/**
 * Manual Import for Kaguya
 * Alternative method: Upload a CSV file to import data
 *
 * Instructions:
 * 1. Download the CSV from Google Sheets manually
 * 2. Upload it to the same directory as this script, named "kaguya_import.csv"
 * 3. Run this script to import the data
 */

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

header("Content-Type: text/plain; charset=utf-8");

echo "=== KAGUYA MANUAL CSV IMPORT ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    // Look for the CSV file
    $csvFile = __DIR__ . '/kaguya_import.csv';

    echo "Step 1: Looking for CSV file...\n";
    echo str_repeat("-", 60) . "\n";
    echo "Expected location: $csvFile\n";

    if (!file_exists($csvFile)) {
        echo "❌ CSV file not found!\n\n";
        echo "INSTRUCTIONS:\n";
        echo "1. Open this URL in your browser:\n";
        echo "   https://docs.google.com/spreadsheets/d/1i3l0Gz8_vzoZLrgZVqMM8T7MvVbuWdEB5dS-X1drhqg/export?format=csv&gid=1324392454\n\n";
        echo "2. Save the downloaded file as: kaguya_import.csv\n\n";
        echo "3. Upload kaguya_import.csv to: " . __DIR__ . "/\n\n";
        echo "4. Run this script again\n";
        exit;
    }

    echo "✅ CSV file found!\n";
    echo "File size: " . filesize($csvFile) . " bytes\n\n";

    // Step 2: Read and parse CSV
    echo "Step 2: Reading CSV file...\n";
    echo str_repeat("-", 60) . "\n";

    $csvContent = file_get_contents($csvFile);

    if (empty($csvContent)) {
        throw new Exception("CSV file is empty");
    }

    echo "✅ CSV content loaded (" . strlen($csvContent) . " bytes)\n\n";

    // Parse CSV
    $lines = explode("\n", $csvContent);
    $csvArray = array_map(function($line) {
        return str_getcsv($line);
    }, $lines);

    // Remove empty lines
    $csvArray = array_filter($csvArray, function($row) {
        return !empty(array_filter($row));
    });

    $header = array_shift($csvArray);
    $dataRows = $csvArray;

    echo "Step 3: Analyzing CSV structure...\n";
    echo str_repeat("-", 60) . "\n";
    echo "Header columns (" . count($header) . "):\n";
    foreach ($header as $i => $col) {
        echo "  [$i] $col\n";
    }
    echo "\nData rows: " . count($dataRows) . "\n\n";

    if (empty($dataRows)) {
        throw new Exception("No data rows found in CSV (only header)");
    }

    // Show first row as example
    echo "First data row example:\n";
    foreach ($header as $i => $col) {
        $value = $dataRows[0][$i] ?? '';
        echo "  $col: $value\n";
    }
    echo "\n";

    // Step 4: Map columns
    echo "Step 4: Mapping columns...\n";
    echo str_repeat("-", 60) . "\n";

    // Create column mapping (adjust based on actual headers)
    $columnMap = [];
    $requiredFields = [
        'check_in' => ['チェックイン', 'check_in', 'チェックイン日', 'Check-in', 'Checkin'],
        'check_out' => ['チェックアウト', 'check_out', 'チェックアウト日', 'Check-out', 'Checkout'],
        'accommodation_fee' => ['宿泊料金', 'accommodation_fee', '料金', 'Fee', 'Amount', 'Price'],
        'night_count' => ['泊数', 'night_count', 'nights', 'Nights', '宿泊数'],
        'booking_date' => ['予約日', 'booking_date', 'Booking Date', '予約受付日'],
        'room_type' => ['部屋タイプ', 'room_type', 'Room Type', '部屋', 'Room'],
        'guest_name' => ['ゲスト名', 'guest_name', 'Guest Name', 'Name', 'ゲスト'],
    ];

    foreach ($requiredFields as $field => $possibleNames) {
        foreach ($possibleNames as $name) {
            $index = array_search($name, $header);
            if ($index !== false) {
                $columnMap[$field] = $index;
                echo "  ✅ $field => Column $index ($header[$index])\n";
                break;
            }
        }
        if (!isset($columnMap[$field])) {
            echo "  ⚠️  $field => NOT FOUND (will use default)\n";
        }
    }
    echo "\n";

    // Step 5: Import data
    echo "Step 5: Importing data...\n";
    echo str_repeat("-", 60) . "\n";

    $importCount = 0;
    $errorCount = 0;
    $errors = [];

    $pdo->beginTransaction();

    // Clear existing data
    $stmt = $pdo->prepare("TRUNCATE TABLE kaguya");
    $stmt->execute();
    echo "Cleared existing data\n\n";

    $insertStmt = $pdo->prepare("
        INSERT INTO kaguya (
            check_in,
            check_out,
            accommodation_fee,
            night_count,
            booking_date,
            room_type,
            guest_name,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($dataRows as $index => $row) {
        try {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Extract data using column map
            $checkIn = isset($columnMap['check_in']) ? ($row[$columnMap['check_in']] ?? '') : '';
            $checkOut = isset($columnMap['check_out']) ? ($row[$columnMap['check_out']] ?? '') : '';
            $accommodationFee = isset($columnMap['accommodation_fee']) ? ($row[$columnMap['accommodation_fee']] ?? 0) : 0;
            $nightCount = isset($columnMap['night_count']) ? ($row[$columnMap['night_count']] ?? 0) : 0;
            $bookingDate = isset($columnMap['booking_date']) ? ($row[$columnMap['booking_date']] ?? null) : null;
            $roomType = isset($columnMap['room_type']) ? ($row[$columnMap['room_type']] ?? '') : '';
            $guestName = isset($columnMap['guest_name']) ? ($row[$columnMap['guest_name']] ?? '') : '';

            // Validate required fields
            if (empty($checkIn) || empty($checkOut)) {
                $errorCount++;
                if ($errorCount <= 5) {
                    $errors[] = "Row " . ($index + 1) . ": Missing check-in or check-out date";
                }
                continue;
            }

            // Clean numeric values
            $accommodationFee = preg_replace('/[^0-9.]/', '', $accommodationFee);
            $nightCount = preg_replace('/[^0-9]/', '', $nightCount);

            // Convert date formats if needed
            // Add date conversion logic here if your CSV uses different date formats

            $insertStmt->execute([
                $checkIn,
                $checkOut,
                $accommodationFee ?: 0,
                $nightCount ?: 0,
                $bookingDate ?: null,
                $roomType,
                $guestName
            ]);

            $importCount++;

            // Show progress every 100 rows
            if ($importCount % 100 == 0) {
                echo "  Imported $importCount records...\n";
            }

        } catch (Exception $e) {
            $errorCount++;
            if ($errorCount <= 5) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }
    }

    $pdo->commit();

    echo "\n✅ Import completed!\n";
    echo "Successfully imported: $importCount records\n";
    echo "Errors: $errorCount\n";

    if (!empty($errors)) {
        echo "\nFirst few errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    echo "\n";

    // Step 6: Update property_sheets
    echo "Step 6: Updating last_imported timestamp...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("
        UPDATE property_sheets
        SET last_imported = CURRENT_TIMESTAMP
        WHERE property_name = 'kaguya'
    ");
    $stmt->execute();

    echo "✅ Updated\n\n";

    // Step 7: Show summary
    echo "Step 7: Import Summary...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_records,
            MIN(check_in) as earliest_checkin,
            MAX(check_out) as latest_checkout,
            SUM(accommodation_fee) as total_revenue,
            COUNT(DISTINCT room_type) as room_types
        FROM kaguya
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Total records: " . $summary['total_records'] . "\n";
    echo "Earliest check-in: " . ($summary['earliest_checkin'] ?? 'N/A') . "\n";
    echo "Latest check-out: " . ($summary['latest_checkout'] ?? 'N/A') . "\n";
    echo "Total revenue: ¥" . number_format($summary['total_revenue']) . "\n";
    echo "Room types: " . $summary['room_types'] . "\n\n";

    // Show room breakdown
    $stmt = $pdo->query("
        SELECT room_type, COUNT(*) as count
        FROM kaguya
        WHERE room_type IS NOT NULL AND room_type != ''
        GROUP BY room_type
        ORDER BY count DESC
    ");
    $roomBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($roomBreakdown)) {
        echo "Room breakdown:\n";
        foreach ($roomBreakdown as $room) {
            echo "  " . $room['room_type'] . ": " . $room['count'] . " bookings\n";
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Import completed successfully!\n";
    echo "\nYou can now delete kaguya_import.csv if you want.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ ERROR:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
