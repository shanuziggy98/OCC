<?php
/**
 * Import Kaguya Commission Data from Google Sheets
 * Fetches monthly commission data and updates the kaguya_monthly_commission table
 */

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

// Google Sheets configuration
$spreadsheetId = '1B0at-W68i5AwUOmrrc3l0RjMNlQdysOR';
$gid = '1404137725'; // The sheet ID from the URL

// Create database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully\n";
} catch(PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

/**
 * Fetch data from Google Sheets as CSV
 */
function fetchGoogleSheetData($spreadsheetId, $gid) {
    // Use the CSV export URL for Google Sheets
    $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";

    echo "📥 Fetching data from Google Sheets...\n";
    echo "URL: $url\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: PHP Script',
            'follow_location' => true
        ]
    ]);

    $csvData = file_get_contents($url, false, $context);

    if ($csvData === false) {
        throw new Exception("Failed to fetch data from Google Sheets");
    }

    return $csvData;
}

/**
 * Parse CSV data into array
 */
function parseCSV($csvData) {
    $lines = explode("\n", $csvData);
    $data = [];

    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $data[] = str_getcsv($line);
    }

    return $data;
}

/**
 * Parse month string (e.g., "25/01" to year: 2025, month: 1)
 */
function parseMonthString($monthStr) {
    // Format: "25/01" or "25/1"
    if (preg_match('/^(\d{2})\/(\d{1,2})$/', trim($monthStr), $matches)) {
        $year = 2000 + intval($matches[1]); // "25" -> 2025
        $month = intval($matches[2]);
        return ['year' => $year, 'month' => $month];
    }
    return null;
}

/**
 * Parse numeric value (remove commas, handle empty values)
 */
function parseNumeric($value) {
    $value = trim($value);
    if ($value === '' || $value === '-') {
        return 0;
    }
    // Remove commas and convert to float
    return floatval(str_replace(',', '', $value));
}

/**
 * Parse percentage value
 */
function parsePercentage($value) {
    $value = trim($value);
    if ($value === '' || $value === '-' || $value === '#DIV/0!') {
        return 0;
    }
    // Remove % sign and convert to float
    return floatval(str_replace('%', '', $value));
}

try {
    // Fetch data from Google Sheets
    $csvData = fetchGoogleSheetData($spreadsheetId, $gid);
    $rows = parseCSV($csvData);

    echo "✅ Fetched " . count($rows) . " rows from Google Sheets\n\n";

    // Display first few rows for debugging
    echo "📊 First 5 rows (for debugging):\n";
    for ($i = 0; $i < min(5, count($rows)); $i++) {
        echo "Row $i: " . implode(' | ', $rows[$i]) . "\n";
    }
    echo "\n";

    // Find header row (looking for "month" or "Total sales")
    $headerRowIndex = 0;
    for ($i = 0; $i < count($rows); $i++) {
        $firstCell = strtolower(trim($rows[$i][0] ?? ''));
        if (strpos($firstCell, 'month') !== false || strpos($firstCell, 'total') !== false) {
            $headerRowIndex = $i;
            break;
        }
    }

    echo "📋 Header row found at index: $headerRowIndex\n";
    echo "Header: " . implode(' | ', $rows[$headerRowIndex]) . "\n\n";

    // Expected columns based on your data:
    // Column 0: month (25/01, 25/02, etc.)
    // Column 1: Total sales
    // Column 2: owner payment
    // Column 3: exseed commission
    // Column 4: com percentage

    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    // Process data rows (skip header)
    for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        // Skip empty rows
        if (empty($row[0]) || trim($row[0]) === '') {
            continue;
        }

        $monthStr = $row[0] ?? '';
        $parsedDate = parseMonthString($monthStr);

        if ($parsedDate === null) {
            echo "⚠️  Skipping row $i: Invalid month format '$monthStr'\n";
            $skipped++;
            continue;
        }

        $year = $parsedDate['year'];
        $month = $parsedDate['month'];
        $totalSales = parseNumeric($row[1] ?? '0');
        $ownerPayment = parseNumeric($row[2] ?? '0');
        $exseedCommission = parseNumeric($row[3] ?? '0');
        $commissionPercentage = parsePercentage($row[4] ?? '0');

        // Create notes
        $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        $notes = $monthNames[$month] . ' ' . $year;

        // Check if this is valid data (total sales should be > 0)
        if ($totalSales <= 0) {
            echo "⚠️  Skipping row $i: No sales data for {$monthStr}\n";
            $skipped++;
            continue;
        }

        // Insert or update in database
        $stmt = $pdo->prepare("
            INSERT INTO kaguya_monthly_commission
            (year, month, total_sales, owner_payment, exseed_commission, commission_percentage, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_sales = VALUES(total_sales),
                owner_payment = VALUES(owner_payment),
                exseed_commission = VALUES(exseed_commission),
                commission_percentage = VALUES(commission_percentage),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            $year,
            $month,
            $totalSales,
            $ownerPayment,
            $exseedCommission,
            $commissionPercentage,
            $notes
        ]);

        if ($stmt->rowCount() > 0) {
            // Check if it was an insert or update
            $checkStmt = $pdo->prepare("SELECT id FROM kaguya_monthly_commission WHERE year = ? AND month = ?");
            $checkStmt->execute([$year, $month]);

            echo "✅ {$monthStr} ({$notes}): Sales=¥" . number_format($totalSales) .
                 ", Commission=¥" . number_format($exseedCommission) .
                 " ({$commissionPercentage}%)\n";

            if ($pdo->lastInsertId() > 0) {
                $inserted++;
            } else {
                $updated++;
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 Import Summary:\n";
    echo "✅ Inserted: $inserted records\n";
    echo "🔄 Updated: $updated records\n";
    echo "⚠️  Skipped: $skipped records\n";
    echo str_repeat("=", 60) . "\n\n";

    // Display current data in database
    echo "📋 Current data in kaguya_monthly_commission table:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-12s | %12s | %12s | %15s | %8s | %s\n",
           "Month", "Total Sales", "Owner Pay", "Exseed Comm", "Comm %", "Notes");
    echo str_repeat("-", 100) . "\n";

    $stmt = $pdo->query("
        SELECT
            CONCAT(`year`, '-', LPAD(`month`, 2, '0')) AS year_month,
            total_sales,
            owner_payment,
            exseed_commission,
            commission_percentage,
            notes
        FROM kaguya_monthly_commission
        ORDER BY `year` DESC, `month` DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-12s | ¥%11s | ¥%11s | ¥%14s | %7.1f%% | %s\n",
               $row['year_month'],
               number_format($row['total_sales']),
               number_format($row['owner_payment']),
               number_format($row['exseed_commission']),
               $row['commission_percentage'],
               $row['notes']
        );
    }
    echo str_repeat("-", 100) . "\n";

    // Calculate statistics
    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) as total_months,
            AVG(total_sales) as avg_sales,
            AVG(exseed_commission) as avg_commission,
            AVG(commission_percentage) as avg_percentage,
            MIN(commission_percentage) as min_percentage,
            MAX(commission_percentage) as max_percentage
        FROM kaguya_monthly_commission
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo "\n📈 Statistics:\n";
    echo "Total Months: {$stats['total_months']}\n";
    echo "Average Monthly Sales: ¥" . number_format($stats['avg_sales']) . "\n";
    echo "Average Monthly Commission: ¥" . number_format($stats['avg_commission']) . "\n";
    echo "Average Commission %: " . number_format($stats['avg_percentage'], 1) . "%\n";
    echo "Commission % Range: " . number_format($stats['min_percentage'], 1) . "% - " .
         number_format($stats['max_percentage'], 1) . "%\n";

    echo "\n✅ Kaguya commission data imported successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
