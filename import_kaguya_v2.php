<?php
/**
 * Import Kaguya Data - Version 2 (without created_at column)
 * Fixed to match the actual kaguya table structure
 */

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

header("Content-Type: text/plain; charset=utf-8");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "=== KAGUYA DATA IMPORT (v2) ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

    // Step 1: Get and clean the URL
    echo "Step 1: Getting Google Sheet URL...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("SELECT google_sheet_url FROM property_sheets WHERE property_name = 'kaguya'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $originalUrl = $result['google_sheet_url'];
    $cleanUrl = str_replace(["\n", "\r", " ", "\t"], '', $originalUrl);

    echo "Clean URL: $cleanUrl\n\n";

    // Step 2: Fetch data from Google Sheets
    echo "Step 2: Fetching data from Google Sheets...\n";
    echo str_repeat("-", 60) . "\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $cleanUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);

    $csvData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL Error: $curlError");
    }

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error $httpCode");
    }

    if (empty($csvData)) {
        throw new Exception("Empty response from Google Sheets");
    }

    echo "✅ Successfully fetched data (" . strlen($csvData) . " bytes)\n\n";

    // Step 3: Parse CSV
    echo "Step 3: Parsing CSV data...\n";
    echo str_repeat("-", 60) . "\n";

    $lines = explode("\n", $csvData);
    $csvArray = array_map('str_getcsv', $lines);

    // Remove empty lines
    $csvArray = array_filter($csvArray, function($row) {
        return !empty(array_filter($row));
    });

    $header = array_shift($csvArray);
    $dataRows = $csvArray;

    echo "Data rows found: " . count($dataRows) . "\n\n";

    if (empty($dataRows)) {
        throw new Exception("No data rows found in CSV");
    }

    // Step 4: Map columns
    $headerMap = array_flip(array_map('trim', $header));

    $fieldMapping = [
        'check_in' => $headerMap['IN'] ?? null,
        'check_out' => $headerMap['OUT'] ?? null,
        'accommodation_fee' => $headerMap['宿泊費用合計'] ?? null,
        'night_count' => $headerMap['泊数'] ?? null,
        'booking_date' => $headerMap['予約日'] ?? null,
        'room_type' => $headerMap['room name'] ?? null,
    ];

    echo "Step 4: Column mapping verified\n";
    echo str_repeat("-", 60) . "\n";
    foreach ($fieldMapping as $field => $index) {
        if ($index !== null) {
            echo "  ✅ $field\n";
        }
    }
    echo "\n";

    // Step 5: Check table structure first
    echo "Step 5: Checking table structure...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("DESCRIBE kaguya");
    $tableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Available columns in kaguya table:\n";
    foreach ($tableColumns as $col) {
        echo "  - $col\n";
    }
    echo "\n";

    // Step 6: Import data (using only columns that exist)
    echo "Step 6: Importing data...\n";
    echo str_repeat("-", 60) . "\n";

    $importCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $errors = [];

    $pdo->beginTransaction();

    // Clear existing data
    $stmt = $pdo->prepare("TRUNCATE TABLE kaguya");
    $stmt->execute();
    echo "Cleared existing data\n";

    // Build INSERT query based on available columns
    $insertColumns = [];
    $placeholders = [];

    if (in_array('check_in', $tableColumns)) {
        $insertColumns[] = 'check_in';
        $placeholders[] = '?';
    }
    if (in_array('check_out', $tableColumns)) {
        $insertColumns[] = 'check_out';
        $placeholders[] = '?';
    }
    if (in_array('accommodation_fee', $tableColumns)) {
        $insertColumns[] = 'accommodation_fee';
        $placeholders[] = '?';
    }
    if (in_array('night_count', $tableColumns)) {
        $insertColumns[] = 'night_count';
        $placeholders[] = '?';
    }
    if (in_array('booking_date', $tableColumns)) {
        $insertColumns[] = 'booking_date';
        $placeholders[] = '?';
    }
    if (in_array('room_type', $tableColumns)) {
        $insertColumns[] = 'room_type';
        $placeholders[] = '?';
    }

    $sql = "INSERT INTO kaguya (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insertStmt = $pdo->prepare($sql);

    echo "Using SQL: $sql\n\n";

    foreach ($dataRows as $index => $row) {
        try {
            // Skip empty rows
            if (empty(array_filter($row))) {
                $skippedCount++;
                continue;
            }

            // Extract data
            $checkIn = isset($fieldMapping['check_in']) && isset($row[$fieldMapping['check_in']])
                ? trim($row[$fieldMapping['check_in']]) : '';
            $checkOut = isset($fieldMapping['check_out']) && isset($row[$fieldMapping['check_out']])
                ? trim($row[$fieldMapping['check_out']]) : '';
            $accommodationFee = isset($fieldMapping['accommodation_fee']) && isset($row[$fieldMapping['accommodation_fee']])
                ? trim($row[$fieldMapping['accommodation_fee']]) : 0;
            $nightCount = isset($fieldMapping['night_count']) && isset($row[$fieldMapping['night_count']])
                ? trim($row[$fieldMapping['night_count']]) : 0;
            $bookingDate = isset($fieldMapping['booking_date']) && isset($row[$fieldMapping['booking_date']])
                ? trim($row[$fieldMapping['booking_date']]) : null;
            $roomType = isset($fieldMapping['room_type']) && isset($row[$fieldMapping['room_type']])
                ? trim($row[$fieldMapping['room_type']]) : '';

            // Validate required fields
            if (empty($checkIn) || empty($checkOut)) {
                $skippedCount++;
                continue;
            }

            // Clean numeric values
            $accommodationFee = preg_replace('/[^0-9.]/', '', $accommodationFee);
            $nightCount = preg_replace('/[^0-9]/', '', $nightCount);

            // Convert empty dates to null
            if (empty($bookingDate) || $bookingDate === '0' || $bookingDate === '0000-00-00') {
                $bookingDate = null;
            }

            // Build values array based on columns
            $values = [];
            if (in_array('check_in', $tableColumns)) $values[] = $checkIn;
            if (in_array('check_out', $tableColumns)) $values[] = $checkOut;
            if (in_array('accommodation_fee', $tableColumns)) $values[] = $accommodationFee ?: 0;
            if (in_array('night_count', $tableColumns)) $values[] = $nightCount ?: 0;
            if (in_array('booking_date', $tableColumns)) $values[] = $bookingDate;
            if (in_array('room_type', $tableColumns)) $values[] = $roomType;

            $insertStmt->execute($values);

            $importCount++;

            // Show progress
            if ($importCount % 50 == 0) {
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
    echo "Skipped (empty/invalid): $skippedCount rows\n";
    echo "Errors: $errorCount\n";

    if (!empty($errors)) {
        echo "\nFirst few errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    echo "\n";

    // Step 7: Update last_imported
    echo "Step 7: Updating last_imported timestamp...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("
        UPDATE property_sheets
        SET last_imported = CURRENT_TIMESTAMP
        WHERE property_name = 'kaguya'
    ");
    $stmt->execute();

    echo "✅ Updated\n\n";

    // Step 8: Show summary
    echo "Step 8: Import Summary...\n";
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

    echo "✅ Total records in table: " . $summary['total_records'] . "\n";
    echo "   Earliest check-in: " . ($summary['earliest_checkin'] ?? 'N/A') . "\n";
    echo "   Latest check-out: " . ($summary['latest_checkout'] ?? 'N/A') . "\n";
    echo "   Total revenue: ¥" . number_format($summary['total_revenue']) . "\n";
    echo "   Different room types: " . $summary['room_types'] . "\n\n";

    // Show room breakdown
    $stmt = $pdo->query("
        SELECT
            room_type,
            COUNT(*) as bookings,
            SUM(accommodation_fee) as revenue,
            AVG(accommodation_fee) as avg_fee
        FROM kaguya
        WHERE room_type IS NOT NULL AND room_type != ''
        GROUP BY room_type
        ORDER BY bookings DESC
    ");
    $roomBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($roomBreakdown)) {
        echo "Room breakdown:\n";
        echo sprintf("%-20s %10s %15s %15s\n", "Room", "Bookings", "Revenue", "Avg Fee");
        echo str_repeat("-", 65) . "\n";
        foreach ($roomBreakdown as $room) {
            echo sprintf("%-20s %10s ¥%14s ¥%14s\n",
                $room['room_type'],
                $room['bookings'],
                number_format($room['revenue']),
                number_format($room['avg_fee'])
            );
        }
    } else {
        echo "No room type data found\n";
    }

    // Sample records
    echo "\n\nSample records (first 5):\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $pdo->query("SELECT * FROM kaguya ORDER BY check_in DESC LIMIT 5");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($samples)) {
        echo "No records found\n";
    } else {
        foreach ($samples as $i => $record) {
            echo "\nRecord " . ($i + 1) . ":\n";
            foreach ($record as $key => $value) {
                echo sprintf("  %-20s: %s\n", $key, $value);
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 SUCCESS! Kaguya data imported successfully!\n";
    echo "\nThe kaguya table now has " . $summary['total_records'] . " booking records.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ ERROR:\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
