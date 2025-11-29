<?php
// Create sub-tables under hostel properties using exact Japanese room names
header("Content-Type: text/html; charset=utf-8");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<h2>Creating Sub-Tables with Exact Japanese Room Names</h2>";
echo "<hr>";

class ExactRoomTableCreator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createRoomTable($propertyName, $roomName) {
        // Use exact room name as provided
        $tableName = $roomName;

        try {
            // Create table with same structure as main property table
            $sql = "
                CREATE TABLE IF NOT EXISTS `{$tableName}` (
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
                    INDEX idx_date_range (check_in, check_out)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Room: {$roomName} from property: {$propertyName}'
            ";

            $this->pdo->exec($sql);

            // Copy data from main table for this specific room
            $mainTableName = strtolower($propertyName);
            $insertSql = "
                INSERT INTO `{$tableName}` (
                    check_in, check_out, accommodation_fee, night_count,
                    booking_date, lead_time, room_type, raw_data, imported_at
                )
                SELECT
                    check_in, check_out, accommodation_fee, night_count,
                    booking_date, lead_time, room_type, raw_data, imported_at
                FROM `{$mainTableName}`
                WHERE room_type = ?
            ";

            $stmt = $this->pdo->prepare($insertSql);
            $stmt->execute([$roomName]);
            $recordCount = $stmt->rowCount();

            return [
                'success' => true,
                'table_name' => $tableName,
                'record_count' => $recordCount,
                'room_name' => $roomName,
                'property_name' => $propertyName
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'table_name' => $tableName,
                'room_name' => $roomName,
                'property_name' => $propertyName
            ];
        }
    }

    public function checkRoomExists($propertyName, $roomName) {
        $mainTableName = strtolower($propertyName);

        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM `{$mainTableName}`
                WHERE room_type = ?
            ");
            $stmt->execute([$roomName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return intval($result['count']);
        } catch (Exception $e) {
            return 0;
        }
    }
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "<p>✅ Connected to database successfully!</p>";

    $roomCreator = new ExactRoomTableCreator($pdo);

    // Define the exact room names for each property
    $propertyRooms = [
        'iwatoyama' => [
            '月沈原101',
            '月沈原102',
            '月沈原201',
            '月沈原202',
            '月沈原203',
            '月沈原204',
            '月沈原205',
            '月沈原301',
            '月沈原302',
            '月沈原303',
            '月沈原304',
            '岩戸山全体',
            'ファミリー401',
            '共用D402A',
            '共用D402B',
            '共用D402C',
            '共用D402D',
            '共用D402E',
            '共用D402F',
            'ダブル403',
            'ダブル404',
            'ダブル405',
            'ユニーク406',
            'ユニーク407',
            'ツイン408',
            'ファミリー301',
            '女子D302A',
            '女子D302B',
            '女子D302C',
            '女子D302D',
            '女子D302E',
            '女子D302F',
            'ダブル303',
            'ダブル304',
            'ダブル305',
            'ユニーク306',
            'ユニーク307',
            'ツイン308',
            '男子D202A',
            '男子D202B',
            '男子D202C',
            '男子D202D',
            '男子D202E',
            '男子D202F',
            'ダブル203',
            'ダブル204',
            'ダブル205',
            'ユニーク206',
            'ユニーク207',
            'ツイン208'
        ],
        'littlehouse' => [
            'いぬねこ1F',
            'いぬねこ2F',
            '秘密の部屋'
        ]
    ];

    // Create tables for each property and room
    echo "<h3>🏗️ Creating Room Sub-Tables</h3>";

    $totalCreated = 0;
    $totalRecords = 0;
    $allResults = [];

    foreach ($propertyRooms as $propertyName => $roomNames) {
        echo "<h4>Creating sub-tables for: {$propertyName}</h4>";

        foreach ($roomNames as $roomName) {
            // Check if this room exists in the data
            $recordCount = $roomCreator->checkRoomExists($propertyName, $roomName);

            if ($recordCount > 0) {
                $result = $roomCreator->createRoomTable($propertyName, $roomName);

                if ($result['success']) {
                    echo "<p>✅ <strong>`{$result['table_name']}`</strong></p>";
                    echo "<p style='margin-left: 20px;'>📊 Records: <strong>{$result['record_count']}</strong></p>";

                    $totalCreated++;
                    $totalRecords += $result['record_count'];
                    $allResults[] = $result;
                } else {
                    echo "<p>❌ Failed to create: <strong>`{$roomName}`</strong></p>";
                    echo "<p style='margin-left: 20px;'>Error: {$result['error']}</p>";
                }
            } else {
                echo "<p>⚠️ Skipped: <strong>`{$roomName}`</strong> (no data found)</p>";
            }
        }
        echo "<hr>";
    }

    // Also check for Goettingen rooms
    echo "<h4>Checking Goettingen for existing rooms:</h4>";
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT room_type, COUNT(*) as count
            FROM goettingen
            WHERE room_type IS NOT NULL AND room_type != ''
            GROUP BY room_type
            ORDER BY room_type
        ");
        $goettingenRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($goettingenRooms)) {
            foreach ($goettingenRooms as $roomData) {
                $roomName = $roomData['room_type'];
                $count = $roomData['count'];

                $result = $roomCreator->createRoomTable('goettingen', $roomName);

                if ($result['success']) {
                    echo "<p>✅ <strong>`{$result['table_name']}`</strong></p>";
                    echo "<p style='margin-left: 20px;'>📊 Records: <strong>{$result['record_count']}</strong></p>";

                    $totalCreated++;
                    $totalRecords += $result['record_count'];
                    $allResults[] = $result;
                } else {
                    echo "<p>❌ Failed to create: <strong>`{$roomName}`</strong></p>";
                    echo "<p style='margin-left: 20px;'>Error: {$result['error']}</p>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p>⚠️ Could not check Goettingen rooms: " . $e->getMessage() . "</p>";
    }

    echo "<hr>";
    echo "<h3>🎉 Room Sub-Tables Creation Complete!</h3>";
    echo "<p>✅ Created <strong>{$totalCreated}</strong> room sub-tables</p>";
    echo "<p>📊 Total records distributed: <strong>{$totalRecords}</strong></p>";

    // Summary table
    echo "<h4>Created Room Sub-Tables:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Property</th><th>Table Name (Room Name)</th><th>Records</th></tr>";

    foreach ($allResults as $result) {
        if ($result['success']) {
            echo "<tr>";
            echo "<td>{$result['property_name']}</td>";
            echo "<td><strong>`{$result['table_name']}`</strong></td>";
            echo "<td>{$result['record_count']}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    echo "<hr>";
    echo "<h3>📊 Usage Examples:</h3>";
    echo "<ul>";
    echo "<li><strong>Query iwatoyama room 月沈原101:</strong><br>";
    echo "<code>SELECT * FROM `月沈原101` WHERE check_in >= '2024-01-01'</code></li>";
    echo "<li><strong>Query iwatoyama shared room D402A:</strong><br>";
    echo "<code>SELECT * FROM `共用D402A` WHERE accommodation_fee > 0</code></li>";
    echo "<li><strong>Query littlehouse secret room:</strong><br>";
    echo "<code>SELECT * FROM `秘密の部屋` ORDER BY check_in DESC</code></li>";
    echo "<li><strong>Get occupancy for specific room:</strong><br>";
    echo "<code>SELECT COUNT(*) as bookings FROM `いぬねこ1F` WHERE check_in BETWEEN '2024-01-01' AND '2024-01-31'</code></li>";
    echo "</ul>";

    echo "<h3>🎯 Room Sub-Tables Created:</h3>";
    echo "<p>✅ Each room has its own table with the exact Japanese name</p>";
    echo "<p>✅ Use backticks when querying: <code>`月沈原101`</code></p>";
    echo "<p>✅ Each table contains only bookings for that specific room</p>";
    echo "<p>✅ Perfect for room-level occupancy analysis</p>";

} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>