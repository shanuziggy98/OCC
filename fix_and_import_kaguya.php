<?php
/**
 * Fix Kaguya Configuration and Import Data
 * This script:
 * 1. Fixes the kaguya property_sheets configuration
 * 2. Attempts to import data from Google Sheets
 * 3. Shows detailed error messages if import fails
 */

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

// Set content type
header("Content-Type: text/plain; charset=utf-8");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "=== KAGUYA FIX AND IMPORT PROCESS ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

    // Step 1: Fix property_sheets configuration
    echo "Step 1: Fixing property_sheets configuration...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("
        UPDATE property_sheets
        SET
            property_type = 'hostel',
            room_list = '風の間,鳥の間,花の間',
            total_rooms = 3,
            sheet_description = 'Kaguya Hostel - 3 rooms',
            updated_at = CURRENT_TIMESTAMP
        WHERE property_name = 'kaguya'
    ");

    $stmt->execute();
    $rowsAffected = $stmt->rowCount();

    if ($rowsAffected > 0) {
        echo "✅ Successfully updated kaguya configuration\n";
        echo "   - Property type: hostel\n";
        echo "   - Room list: 風の間,鳥の間,花の間\n";
        echo "   - Total rooms: 3\n";
    } else {
        echo "⚠️  No changes made (already configured correctly)\n";
    }

    echo "\n";

    // Step 2: Get Google Sheet URL
    echo "Step 2: Getting Google Sheet URL...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("
        SELECT google_sheet_url
        FROM property_sheets
        WHERE property_name = 'kaguya'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $sheetUrl = $result['google_sheet_url'];
    echo "Google Sheet URL: $sheetUrl\n\n";

    // Step 3: Attempt to fetch data from Google Sheets
    echo "Step 3: Fetching data from Google Sheets...\n";
    echo str_repeat("-", 60) . "\n";

    // Set up cURL with proper headers
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sheetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $csvData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status Code: $httpCode\n";

    if ($curlError) {
        echo "❌ cURL Error: $curlError\n";
        throw new Exception("Failed to fetch Google Sheets data: $curlError");
    }

    if ($httpCode !== 200) {
        echo "❌ HTTP Error: Received status code $httpCode\n";
        echo "Response preview: " . substr($csvData, 0, 200) . "...\n";
        throw new Exception("Failed to fetch Google Sheets data. HTTP Status: $httpCode");
    }

    if (empty($csvData)) {
        echo "❌ Empty response from Google Sheets\n";
        throw new Exception("Google Sheets returned empty data");
    }

    echo "✅ Successfully fetched CSV data\n";
    echo "Data size: " . strlen($csvData) . " bytes\n\n";

    // Step 4: Parse CSV data
    echo "Step 4: Parsing CSV data...\n";
    echo str_repeat("-", 60) . "\n";

    $lines = explode("\n", $csvData);
    $totalLines = count($lines);
    echo "Total lines: $totalLines\n";

    if ($totalLines < 2) {
        echo "❌ CSV has no data rows (only header or empty)\n";
        throw new Exception("CSV file has insufficient data");
    }

    // Show first few lines for inspection
    echo "\nFirst 3 lines of CSV:\n";
    for ($i = 0; $i < min(3, $totalLines); $i++) {
        echo "Line $i: " . substr($lines[$i], 0, 100) . "...\n";
    }

    echo "\n";

    // Parse CSV
    $csvArray = array_map(function($line) {
        return str_getcsv($line);
    }, $lines);

    // Remove empty lines
    $csvArray = array_filter($csvArray, function($row) {
        return !empty(array_filter($row));
    });

    $header = array_shift($csvArray);
    $dataRows = $csvArray;

    echo "Header columns: " . implode(", ", array_slice($header, 0, 10)) . "\n";
    echo "Data rows to import: " . count($dataRows) . "\n\n";

    if (empty($dataRows)) {
        echo "⚠️  No data rows to import (only header found)\n";
        echo "This might mean:\n";
        echo "- The Google Sheet is empty\n";
        echo "- The sheet ID/GID in the URL is incorrect\n";
        echo "- The sheet has only headers but no booking data\n";
        exit;
    }

    // Step 5: Prepare table structure (if needed)
    echo "Step 5: Checking table structure...\n";
    echo str_repeat("-", 60) . "\n";

    // Check if table needs to be created or updated
    $stmt = $pdo->query("DESCRIBE kaguya");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Existing columns in kaguya table: " . count($existingColumns) . "\n";
    echo "Columns: " . implode(", ", array_slice($existingColumns, 0, 10)) . "...\n\n";

    // Step 6: Import data
    echo "Step 6: Importing data into kaguya table...\n";
    echo str_repeat("-", 60) . "\n";

    $importCount = 0;
    $errorCount = 0;
    $errors = [];

    // Create a mapping of header names to indices
    $headerMap = array_flip($header);

    // Start transaction
    $pdo->beginTransaction();

    // Clear existing data (optional - comment out if you want to keep old data)
    $stmt = $pdo->prepare("TRUNCATE TABLE kaguya");
    $stmt->execute();
    echo "Cleared existing data from kaguya table\n";

    foreach ($dataRows as $index => $row) {
        try {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Extract key fields (adjust based on your CSV structure)
            // Common field names - adjust these based on your actual CSV headers
            $checkIn = $row[$headerMap['チェックイン'] ?? $headerMap['check_in'] ?? 0] ?? '';
            $checkOut = $row[$headerMap['チェックアウト'] ?? $headerMap['check_out'] ?? 1] ?? '';
            $accommodationFee = $row[$headerMap['宿泊料金'] ?? $headerMap['accommodation_fee'] ?? 2] ?? 0;
            $nightCount = $row[$headerMap['泊数'] ?? $headerMap['night_count'] ?? 3] ?? 0;
            $bookingDate = $row[$headerMap['予約日'] ?? $headerMap['booking_date'] ?? 4] ?? null;
            $roomType = $row[$headerMap['部屋タイプ'] ?? $headerMap['room_type'] ?? 5] ?? '';
            $guestName = $row[$headerMap['ゲスト名'] ?? $headerMap['guest_name'] ?? 6] ?? '';

            // Validate required fields
            if (empty($checkIn) || empty($checkOut)) {
                continue;
            }

            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO kaguya (
                    check_in,
                    check_out,
                    accommodation_fee,
                    night_count,
                    booking_date,
                    room_type,
                    guest_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $checkIn,
                $checkOut,
                $accommodationFee,
                $nightCount,
                $bookingDate ?: null,
                $roomType,
                $guestName
            ]);

            $importCount++;

        } catch (Exception $e) {
            $errorCount++;
            if ($errorCount <= 5) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }
    }

    // Commit transaction
    $pdo->commit();

    echo "✅ Import completed!\n";
    echo "Successfully imported: $importCount records\n";
    echo "Errors: $errorCount\n";

    if (!empty($errors)) {
        echo "\nFirst few errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }

    echo "\n";

    // Step 7: Update last_imported timestamp
    echo "Step 7: Updating last_imported timestamp...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->prepare("
        UPDATE property_sheets
        SET last_imported = CURRENT_TIMESTAMP
        WHERE property_name = 'kaguya'
    ");
    $stmt->execute();

    echo "✅ Updated last_imported timestamp\n\n";

    // Step 8: Verify import
    echo "Step 8: Verifying import...\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kaguya");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Total records in kaguya table: " . $result['count'] . "\n\n";

    if ($result['count'] > 0) {
        echo "Sample data (first 3 records):\n";
        $stmt = $pdo->query("SELECT * FROM kaguya LIMIT 3");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($samples as $i => $sample) {
            echo "\nRecord " . ($i + 1) . ":\n";
            foreach ($sample as $key => $value) {
                echo sprintf("  %-20s: %s\n", $key, $value);
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Process completed successfully!\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ ERROR OCCURRED:\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
