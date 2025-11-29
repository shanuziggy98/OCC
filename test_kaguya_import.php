<?php
/**
 * Debug script to test kaguya import
 */
header("Content-Type: text/html; charset=utf-8");

echo "<h2>Testing Kaguya Import</h2>";
echo "<hr>";

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "<p>✅ Database connected</p>";

    // Step 1: Check if property exists in property_sheets
    echo "<h3>Step 1: Check property_sheets</h3>";
    $stmt = $pdo->prepare("SELECT * FROM property_sheets WHERE property_name = 'kaguya'");
    $stmt->execute();
    $property = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$property) {
        echo "<p style='color:red'>❌ Property 'kaguya' not found in property_sheets!</p>";
        exit;
    }

    echo "<pre>";
    print_r($property);
    echo "</pre>";

    // Step 2: Test fetching CSV data
    echo "<h3>Step 2: Fetch CSV from Google Sheets</h3>";
    $csvUrl = $property['google_sheet_url'];
    echo "<p>URL: <code>{$csvUrl}</code></p>";

    // Try with cURL first
    $csvData = false;
    if (function_exists('curl_init')) {
        echo "<h4>Trying with cURL...</h4>";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $csvUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        $csvData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        echo "<p>HTTP Code: <strong>{$httpCode}</strong></p>";
        echo "<p>Final URL after redirects: <code>{$finalUrl}</code></p>";

        if ($error) {
            echo "<p style='color:orange'>cURL Error: {$error}</p>";
        }

        if ($csvData && $httpCode == 200) {
            echo "<p style='color:green'>✅ cURL fetch successful!</p>";
        } else {
            echo "<p style='color:red'>❌ cURL failed</p>";
            $csvData = false;
        }
    } else {
        echo "<p style='color:orange'>⚠️ cURL not available, trying file_get_contents...</p>";
    }

    // Fallback to file_get_contents
    if (!$csvData) {
        echo "<h4>Trying with file_get_contents...</h4>";
        $context = stream_context_create([
            "http" => [
                "timeout" => 30,
                "user_agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
                "follow_location" => true,
                "max_redirects" => 5
            ]
        ]);

        $csvData = @file_get_contents($csvUrl, false, $context);

        if (!$csvData) {
            echo "<p style='color:red'>❌ Failed to fetch CSV data with both methods!</p>";
            echo "<p>Last error: " . error_get_last()['message'] . "</p>";
            exit;
        } else {
            echo "<p style='color:green'>✅ file_get_contents successful!</p>";
        }
    }

    echo "<p>✅ CSV data fetched successfully</p>";
    echo "<p>Data size: " . strlen($csvData) . " bytes</p>";

    // Show first few lines
    echo "<h4>First 10 lines of CSV:</h4>";
    $lines = explode("\n", $csvData);
    echo "<pre>";
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";

    // Step 3: Check if table exists
    echo "<h3>Step 3: Check if table exists</h3>";
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'kaguya'");
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        echo "<p style='color:orange'>⚠️ Table 'kaguya' does not exist yet</p>";
        echo "<p>Creating table...</p>";

        $createTableSQL = "
            CREATE TABLE `kaguya` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                accommodation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                night_count INT NOT NULL DEFAULT 0,
                booking_date DATE,
                lead_time INT DEFAULT 0,
                room_type VARCHAR(100),
                guest_name VARCHAR(255),
                guest_email VARCHAR(255),
                special_requests TEXT,
                raw_data JSON,
                imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_check_in (check_in),
                INDEX idx_check_out (check_out),
                INDEX idx_booking_date (booking_date),
                INDEX idx_date_range (check_in, check_out),
                INDEX idx_room_type (room_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $pdo->exec($createTableSQL);
        echo "<p>✅ Table created</p>";
    } else {
        echo "<p>✅ Table 'kaguya' exists</p>";

        // Check row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM kaguya");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Current rows in table: <strong>{$count['count']}</strong></p>";
    }

    // Step 4: Parse CSV structure
    echo "<h3>Step 4: Analyze CSV structure</h3>";
    $lines = explode("\n", $csvData);

    if (count($lines) < 2) {
        echo "<p style='color:red'>❌ CSV has no data rows!</p>";
        exit;
    }

    // Show header
    $header = str_getcsv($lines[0]);
    echo "<h4>CSV Header (Column count: " . count($header) . "):</h4>";
    echo "<ol start='0'>";
    foreach ($header as $index => $col) {
        echo "<li>[{$index}] " . htmlspecialchars($col) . "</li>";
    }
    echo "</ol>";

    // Show first data row
    if (isset($lines[1])) {
        $firstRow = str_getcsv($lines[1]);
        echo "<h4>First Data Row (Column count: " . count($firstRow) . "):</h4>";
        echo "<ol start='0'>";
        foreach ($firstRow as $index => $col) {
            echo "<li>[{$index}] " . htmlspecialchars($col) . "</li>";
        }
        echo "</ol>";

        // Check if it's a hostel
        echo "<h4>Property Type Analysis:</h4>";
        echo "<p>Property type in DB: <strong>" . ($property['property_type'] ?? 'not set') . "</strong></p>";
        echo "<p>Is hostel: <strong>" . ($property['property_type'] === 'hostel' ? 'YES' : 'NO') . "</strong></p>";
        echo "<p>Expected columns for hostel: 10+ (including room_type at index 9)</p>";
        echo "<p>Expected columns for guesthouse: 9+</p>";
        echo "<p>Actual columns in data: <strong>" . count($firstRow) . "</strong></p>";

        if ($property['property_type'] === 'hostel' && count($firstRow) < 10) {
            echo "<p style='color:red'>⚠️ WARNING: Marked as hostel but doesn't have room_type column!</p>";
        }
    }

    echo "<h3>✅ Diagnostics Complete</h3>";
    echo "<p><strong>Next step:</strong> Run the import script to process this data:</p>";
    echo "<p><a href='auto_import_cron.php?auth_key=exseed_auto_import_2025' target='_blank'>Click here to run import</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
