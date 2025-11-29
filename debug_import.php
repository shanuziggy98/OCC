<?php
/**
 * Debug Import Script - Shows detailed import information
 */
header("Content-Type: text/html; charset=utf-8");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<style>
body { font-family: monospace; padding: 20px; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.skipped { background-color: #fff3cd; }
</style>";

echo "<h1>🔍 Debug Import - Fujinomori</h1>";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    // Get fujinomori data
    $stmt = $pdo->prepare("SELECT property_name, google_sheet_url FROM property_sheets WHERE property_name = 'fujinomori'");
    $stmt->execute();
    $property = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$property) {
        die("<p class='error'>Property 'fujinomori' not found in database!</p>");
    }

    echo "<p><strong>Property:</strong> {$property['property_name']}</p>";
    echo "<p><strong>Google Sheet URL:</strong> {$property['google_sheet_url']}</p>";

    // Fetch CSV
    $context = stream_context_create([
        "http" => [
            "timeout" => 30,
            "user_agent" => "Mozilla/5.0 (compatible; ExseedOcc/1.0)",
            "follow_location" => true,
            "max_redirects" => 5
        ]
    ]);

    $csvData = @file_get_contents($property['google_sheet_url'], false, $context);

    if (!$csvData) {
        die("<p class='error'>Failed to fetch CSV data from Google Sheets!</p>");
    }

    echo "<p class='success'>✓ CSV data fetched successfully (" . strlen($csvData) . " bytes)</p>";

    // Parse CSV
    $lines = explode("\n", $csvData);
    $header = array_shift($lines); // Get header

    echo "<h2>CSV Header:</h2>";
    echo "<pre>" . htmlspecialchars($header) . "</pre>";

    echo "<h2>Total Lines: " . count($lines) . "</h2>";

    // Analyze each line
    echo "<table>";
    echo "<tr><th>Line</th><th>Columns</th><th>Check-in</th><th>Check-out</th><th>Fee</th><th>Nights</th><th>Status</th><th>Issue</th></tr>";

    $validCount = 0;
    $skippedCount = 0;
    $emptyCount = 0;

    foreach ($lines as $index => $line) {
        if (empty(trim($line))) {
            $emptyCount++;
            continue;
        }

        $data = str_getcsv($line);
        $lineNum = $index + 2;

        $checkIn = $data[3] ?? '';
        $checkOut = $data[4] ?? '';
        $fee = $data[5] ?? '';
        $nights = $data[6] ?? '';

        $issues = [];
        $isValid = true;

        // Check column count
        if (count($data) < 8) {
            $issues[] = "Not enough columns (" . count($data) . ")";
            $isValid = false;
        }

        // Check dates
        if (empty(trim($checkIn))) {
            $issues[] = "Missing check-in";
            $isValid = false;
        }

        if (empty(trim($checkOut))) {
            $issues[] = "Missing check-out";
            $isValid = false;
        }

        $rowClass = $isValid ? '' : ' class="skipped"';
        $statusText = $isValid ? '<span class="success">✓ Valid</span>' : '<span class="error">✗ Skipped</span>';

        if ($isValid) {
            $validCount++;
        } else {
            $skippedCount++;
        }

        echo "<tr{$rowClass}>";
        echo "<td>{$lineNum}</td>";
        echo "<td>" . count($data) . "</td>";
        echo "<td>" . htmlspecialchars($checkIn) . "</td>";
        echo "<td>" . htmlspecialchars($checkOut) . "</td>";
        echo "<td>" . htmlspecialchars($fee) . "</td>";
        echo "<td>" . htmlspecialchars($nights) . "</td>";
        echo "<td>{$statusText}</td>";
        echo "<td>" . (empty($issues) ? '-' : implode(', ', $issues)) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<h2>Summary</h2>";
    echo "<p>✅ Valid rows that will be imported: <strong>{$validCount}</strong></p>";
    echo "<p>⚠️ Skipped rows (missing data): <strong>{$skippedCount}</strong></p>";
    echo "<p>📄 Empty lines: <strong>{$emptyCount}</strong></p>";

    echo "<h2>What to Check:</h2>";
    echo "<ul>";
    echo "<li>Make sure check-in column (column D, index 3) has dates</li>";
    echo "<li>Make sure check-out column (column E, index 4) has dates</li>";
    echo "<li>Rows with missing check-in or check-out dates will be skipped</li>";
    echo "<li>Empty rows are automatically skipped</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
?>
