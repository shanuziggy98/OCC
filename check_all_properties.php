<?php
/**
 * Check all properties in database
 */
header("Content-Type: text/html; charset=utf-8");

echo "<h2>All Properties in Database</h2>";
echo "<hr>";

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8mb4");

    echo "<p>✅ Database connected</p>";

    // Get all properties
    $stmt = $pdo->query("SELECT * FROM property_sheets ORDER BY display_order, property_name");
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($properties)) {
        echo "<p style='color:red'>❌ No properties found in property_sheets table!</p>";
        exit;
    }

    echo "<h3>Total Properties: " . count($properties) . "</h3>";

    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>
            <th>ID</th>
            <th>Property Name</th>
            <th>Property Type</th>
            <th>Room List</th>
            <th>Active</th>
            <th>Google Sheet URL (first 100 chars)</th>
            <th>Last Imported</th>
          </tr>";

    foreach ($properties as $prop) {
        $isActive = $prop['is_active'] ? '✅ Yes' : '❌ No';
        $propertyType = $prop['property_type'] ?? 'not set';
        $roomList = $prop['room_list'] ?? 'N/A';
        $googleSheetPreview = substr($prop['google_sheet_url'], 0, 100);

        echo "<tr>";
        echo "<td>{$prop['id']}</td>";
        echo "<td><strong>{$prop['property_name']}</strong></td>";
        echo "<td>{$propertyType}</td>";
        echo "<td style='font-size: 11px;'>" . htmlspecialchars(substr($roomList, 0, 50)) . "...</td>";
        echo "<td>{$isActive}</td>";
        echo "<td style='font-size: 11px;'>" . htmlspecialchars($googleSheetPreview) . "...</td>";
        echo "<td>" . ($prop['last_imported'] ?? 'Never') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Search for kaguya-like names
    echo "<h3>Search Results for 'kaguya':</h3>";
    $stmt = $pdo->prepare("SELECT * FROM property_sheets WHERE property_name LIKE ?");
    $stmt->execute(['%kaguya%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo "<p style='color:orange'>⚠️ No properties found with 'kaguya' in the name</p>";

        echo "<h3>🔍 Checking the most recent property added:</h3>";
        $stmt = $pdo->query("SELECT * FROM property_sheets ORDER BY created_at DESC LIMIT 1");
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($latest) {
            echo "<pre>";
            print_r($latest);
            echo "</pre>";
        }
    } else {
        echo "<p>✅ Found " . count($results) . " matching properties:</p>";
        echo "<pre>";
        print_r($results);
        echo "</pre>";
    }

    // Check all tables in database
    echo "<h3>All Tables in Database:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<p>Total tables: " . count($tables) . "</p>";
    echo "<ul>";
    $kaguyaTables = [];
    foreach ($tables as $table) {
        if (stripos($table, 'kaguya') !== false) {
            $kaguyaTables[] = $table;
            echo "<li style='color: green; font-weight: bold;'>✅ {$table}</li>";
        } else {
            echo "<li>{$table}</li>";
        }
    }
    echo "</ul>";

    if (!empty($kaguyaTables)) {
        echo "<h3>🎯 Kaguya-related tables found:</h3>";
        foreach ($kaguyaTables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p>Table <strong>`{$table}`</strong> has <strong>{$count['count']}</strong> rows</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
