<?php
/**
 * Diagnose Google Sheets Connection Issues
 * Tests various methods to fetch Google Sheets data
 */

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

header("Content-Type: text/plain; charset=utf-8");

echo "=== GOOGLE SHEETS CONNECTION DIAGNOSTICS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get the Google Sheet URL
    $stmt = $pdo->prepare("SELECT google_sheet_url FROM property_sheets WHERE property_name = 'kaguya'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $originalUrl = $result['google_sheet_url'];

    echo "Original URL from database:\n";
    echo "$originalUrl\n\n";

    // Test 1: Check if cURL is available
    echo "Test 1: Checking if cURL is available...\n";
    echo str_repeat("-", 60) . "\n";
    if (function_exists('curl_version')) {
        $curlVersion = curl_version();
        echo "✅ cURL is available\n";
        echo "   Version: " . $curlVersion['version'] . "\n";
        echo "   SSL Version: " . $curlVersion['ssl_version'] . "\n";
        echo "   Protocols: " . implode(", ", $curlVersion['protocols']) . "\n";
    } else {
        echo "❌ cURL is NOT available\n";
    }
    echo "\n";

    // Test 2: Check if allow_url_fopen is enabled
    echo "Test 2: Checking if allow_url_fopen is enabled...\n";
    echo str_repeat("-", 60) . "\n";
    if (ini_get('allow_url_fopen')) {
        echo "✅ allow_url_fopen is enabled\n";
    } else {
        echo "❌ allow_url_fopen is disabled\n";
    }
    echo "\n";

    // Test 3: Try different URL formats
    echo "Test 3: Testing different URL formats...\n";
    echo str_repeat("-", 60) . "\n";

    $urls = [
        'Original' => $originalUrl,
        'Without spaces' => str_replace(["\n", "\r", " "], '', $originalUrl),
        'Alternative format' => 'https://docs.google.com/spreadsheets/d/1i3l0Gz8_vzoZLrgZVqMM8T7MvVbuWdEB5dS-X1drhqg/export?format=csv&gid=1324392454',
    ];

    foreach ($urls as $name => $url) {
        echo "\nTrying $name URL:\n";
        echo substr($url, 0, 80) . "...\n";

        // Method 1: cURL
        if (function_exists('curl_init')) {
            echo "  Method 1 (cURL): ";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false, // Try without SSL verification
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_VERBOSE => false
            ]);

            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            if ($curlErrno) {
                echo "❌ Error ($curlErrno): $curlError\n";
            } else if ($httpCode == 200 && !empty($data)) {
                echo "✅ Success! (HTTP $httpCode, " . strlen($data) . " bytes)\n";
                echo "    First 100 chars: " . substr($data, 0, 100) . "...\n";
            } else {
                echo "⚠️  HTTP $httpCode, Data size: " . strlen($data) . " bytes\n";
                echo "    First 100 chars: " . substr($data, 0, 100) . "...\n";
            }
        }

        // Method 2: file_get_contents
        if (ini_get('allow_url_fopen')) {
            echo "  Method 2 (file_get_contents): ";
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\n",
                    'timeout' => 30,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $data = @file_get_contents($url, false, $context);

            if ($data !== false && !empty($data)) {
                echo "✅ Success! (" . strlen($data) . " bytes)\n";
                echo "    First 100 chars: " . substr($data, 0, 100) . "...\n";
            } else {
                echo "❌ Failed\n";
                $error = error_get_last();
                if ($error) {
                    echo "    Error: " . $error['message'] . "\n";
                }
            }
        }
    }

    echo "\n";

    // Test 4: Check PHP settings that might affect connections
    echo "Test 4: Checking relevant PHP settings...\n";
    echo str_repeat("-", 60) . "\n";

    $settings = [
        'allow_url_fopen',
        'allow_url_include',
        'max_execution_time',
        'default_socket_timeout',
        'user_agent',
    ];

    foreach ($settings as $setting) {
        $value = ini_get($setting);
        echo sprintf("  %-30s: %s\n", $setting, $value ?: '(empty)');
    }

    echo "\n";

    // Test 5: Try a simple external connection test
    echo "Test 5: Testing basic external connectivity...\n";
    echo str_repeat("-", 60) . "\n";

    $testUrls = [
        'Google' => 'https://www.google.com',
        'Google APIs' => 'https://www.googleapis.com',
    ];

    foreach ($testUrls as $name => $testUrl) {
        echo "  Testing $name: ";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_NOBODY => true, // HEAD request only
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            echo "❌ Error: $curlError\n";
        } else if ($httpCode >= 200 && $httpCode < 400) {
            echo "✅ Success (HTTP $httpCode)\n";
        } else {
            echo "⚠️  HTTP $httpCode\n";
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Diagnostics completed\n\n";

    echo "RECOMMENDATIONS:\n";
    echo "1. If all tests fail, contact your hosting provider about:\n";
    echo "   - Firewall restrictions on outbound HTTPS connections\n";
    echo "   - SSL certificate verification issues\n";
    echo "   - DNS resolution for docs.google.com\n\n";
    echo "2. If only Google Sheets fails but other sites work:\n";
    echo "   - Check if Google services are blocked\n";
    echo "   - Verify the sheet is publicly accessible\n";
    echo "   - Check sheet permissions and sharing settings\n\n";
    echo "3. Alternative solution:\n";
    echo "   - Download CSV manually and upload via FTP\n";
    echo "   - Use a manual import script instead\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
