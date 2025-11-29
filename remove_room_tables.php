<?php
// Remove all room-specific tables that were created
header("Content-Type: text/html; charset=utf-8");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<h2>Removing Room-Specific Tables</h2>";
echo "<hr>";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "<p>✅ Connected to database successfully!</p>";

    // Get all tables in the database
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<p>📊 Found <strong>" . count($allTables) . "</strong> tables in database</p>";

    // Define the tables we want to keep (main property tables and system tables)
    $keepTables = [
        'property_sheets',
        'properties',
        'bookings',
        'occupancy_calculations',
        'import_logs',
        'room_table_mapping',
        // Main property tables
        'comodita', 'mujurin', 'fujinomori', 'enraku', 'tsubaki', 'hiiragi',
        'fushimi_apt', 'kanon', 'fushimi_house', 'kado', 'tanuki', 'fukuro',
        'hauwa_apt', 'littlehouse', 'yanagawa', 'nishijin_fujita', 'rikyu',
        'hiroshima', 'okinawa', 'iwatoyama', 'goettingen', 'ryoma', 'isa',
        'yura', 'konoha'
    ];

    // Find tables to remove (tables not in the keep list)
    $tablesToRemove = [];
    foreach ($allTables as $table) {
        if (!in_array($table, $keepTables)) {
            $tablesToRemove[] = $table;
        }
    }

    echo "<h3>🔍 Analysis:</h3>";
    echo "<p>✅ Tables to keep: <strong>" . count($keepTables) . "</strong></p>";
    echo "<p>🗑️ Tables to remove: <strong>" . count($tablesToRemove) . "</strong></p>";

    if (!empty($tablesToRemove)) {
        echo "<h4>Tables that will be removed:</h4>";
        echo "<ul>";
        foreach ($tablesToRemove as $table) {
            echo "<li><code>{$table}</code></li>";
        }
        echo "</ul>";

        echo "<hr>";
        echo "<h3>🗑️ Removing Tables...</h3>";

        $removedCount = 0;
        $errorCount = 0;

        foreach ($tablesToRemove as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                echo "<p>✅ Removed: <code>{$table}</code></p>";
                $removedCount++;
            } catch (Exception $e) {
                echo "<p>❌ Error removing <code>{$table}</code>: " . $e->getMessage() . "</p>";
                $errorCount++;
            }
        }

        echo "<hr>";
        echo "<h3>🎉 Cleanup Complete!</h3>";
        echo "<p>✅ Successfully removed: <strong>{$removedCount}</strong> tables</p>";
        if ($errorCount > 0) {
            echo "<p>❌ Errors: <strong>{$errorCount}</strong> tables</p>";
        }

        // Show remaining tables
        echo "<h4>Remaining Tables:</h4>";
        $stmt = $pdo->query("SHOW TABLES");
        $remainingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "<p>📊 Current tables in database: <strong>" . count($remainingTables) . "</strong></p>";
        echo "<ul>";
        foreach ($remainingTables as $table) {
            echo "<li><code>{$table}</code></li>";
        }
        echo "</ul>";

    } else {
        echo "<h3>✅ No Room Tables to Remove</h3>";
        echo "<p>All tables in the database are main property tables or system tables.</p>";
    }

    echo "<hr>";
    echo "<h3>📋 Current Database Structure:</h3>";
    echo "<ul>";
    echo "<li>✅ <strong>Main property tables:</strong> 25 properties (comodita, mujurin, etc.)</li>";
    echo "<li>✅ <strong>System tables:</strong> property_sheets, properties, etc.</li>";
    echo "<li>🗑️ <strong>Room tables:</strong> Removed (to be implemented in program later)</li>";
    echo "</ul>";

    echo "<p>🎯 <strong>Ready for frontend implementation!</strong></p>";

} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>