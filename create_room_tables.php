<?php
// Create sub-tables for hostel properties filtered by room names
header("Content-Type: text/html; charset=utf-8");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<h2>Creating Room-Specific Tables for Hostels</h2>";
echo "<hr>";

class RoomTableCreator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function sanitizeTableName($name) {
        // Convert Japanese characters and special chars to safe table names
        $tableName = strtolower($name);

        // Replace Japanese characters with romanized equivalents
        $replacements = [
            '月沈原' => 'gesshingen',
            '岩戸山全体' => 'iwatoyama_zentai',
            'ファミリー' => 'family',
            '共用' => 'shared',
            'ダブル' => 'double',
            'ユニーク' => 'unique',
            'ツイン' => 'twin',
            '女子' => 'female',
            '男子' => 'male',
            'いぬねこ' => 'inuneko',
            '秘密の部屋' => 'secret_room'
        ];

        foreach ($replacements as $japanese => $english) {
            $tableName = str_replace($japanese, $english, $tableName);
        }

        // Clean up the table name
        $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
        $tableName = preg_replace('/_+/', '_', $tableName);
        $tableName = trim($tableName, '_');

        return $tableName;
    }

    public function createRoomTable($propertyName, $roomName) {
        $tablePrefix = strtolower($propertyName);
        $roomTableName = $tablePrefix . '_' . $this->sanitizeTableName($roomName);

        try {
            // Create table with same structure as main property table
            $sql = "
                CREATE TABLE IF NOT EXISTS `{$roomTableName}` (
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
            ";

            $this->pdo->exec($sql);

            // Copy data from main table for this specific room
            $mainTableName = $tablePrefix;
            $insertSql = "
                INSERT INTO `{$roomTableName}` (
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
                'table_name' => $roomTableName,
                'record_count' => $recordCount,
                'room_name' => $roomName
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'table_name' => $roomTableName,
                'room_name' => $roomName
            ];
        }
    }

    public function getRoomNamesFromProperty($propertyName) {
        $tableName = strtolower($propertyName);

        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT room_type
                FROM `{$tableName}`
                WHERE room_type IS NOT NULL
                AND room_type != ''
                ORDER BY room_type
            ");

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            echo "<p>❌ Error getting room names from {$propertyName}: " . $e->getMessage() . "</p>";
            return [];
        }
    }
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "<p>✅ Connected to database successfully!</p>";

    $roomCreator = new RoomTableCreator($pdo);

    // Define room structures for each hostel
    $hostelRooms = [
        'iwatoyama' => [
            // Individual rooms from your list
            '月沈原101', '月沈原102',
            '月沈原201', '月沈原202', '月沈原203', '月沈原204', '月沈原205',
            '月沈原301', '月沈原302', '月沈原303', '月沈原304',
            '岩戸山全体',
            'ファミリー401',
            '共用D402A', '共用D402B', '共用D402C', '共用D402D', '共用D402E', '共用D402F',
            'ダブル403', 'ダブル404', 'ダブル405',
            'ユニーク406', 'ユニーク407',
            'ツイン408',
            'ファミリー301',
            '女子D302A', '女子D302B', '女子D302C', '女子D302D', '女子D302E', '女子D302F',
            'ダブル303', 'ダブル304', 'ダブル305',
            'ユニーク306', 'ユニーク307',
            'ツイン308',
            '男子D202A', '男子D202B', '男子D202C', '男子D202D', '男子D202E', '男子D202F',
            'ダブル203', 'ダブル204', 'ダブル205',
            'ユニーク206', 'ユニーク207',
            'ツイン208'
        ],
        'littlehouse' => [
            'いぬねこ1F',
            'いぬねこ2F',
            '秘密の部屋'
        ]
    ];

    // First, let's check what rooms actually exist in the data
    echo "<h3>🔍 Checking Actual Room Data</h3>";

    foreach (['iwatoyama', 'littlehouse', 'goettingen'] as $property) {
        echo "<h4>{$property}:</h4>";
        $actualRooms = $roomCreator->getRoomNamesFromProperty($property);

        if (!empty($actualRooms)) {
            echo "<p>Found <strong>" . count($actualRooms) . "</strong> unique rooms:</p>";
            echo "<ul>";
            foreach ($actualRooms as $room) {
                echo "<li>'" . htmlspecialchars($room) . "'</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>❌ No room data found</p>";
        }
        echo "<hr>";
    }

    // Create tables for iwatoyama and littlehouse using actual room data
    echo "<h3>🏗️ Creating Room Tables</h3>";

    $totalCreated = 0;
    $totalRecords = 0;
    $results = [];

    foreach (['iwatoyama', 'littlehouse'] as $property) {
        echo "<h4>Creating tables for: {$property}</h4>";

        $actualRooms = $roomCreator->getRoomNamesFromProperty($property);

        foreach ($actualRooms as $roomName) {
            $result = $roomCreator->createRoomTable($property, $roomName);

            if ($result['success']) {
                echo "<p>✅ <strong>{$result['table_name']}</strong>: {$result['record_count']} records (Room: {$roomName})</p>";
                $totalCreated++;
                $totalRecords += $result['record_count'];
                $results[] = $result;
            } else {
                echo "<p>❌ <strong>{$result['table_name']}</strong>: {$result['error']}</p>";
            }
        }
        echo "<hr>";
    }

    // For Goettingen, let's also check what rooms it has
    echo "<h4>Creating tables for: goettingen</h4>";
    $goettingenRooms = $roomCreator->getRoomNamesFromProperty('goettingen');

    foreach ($goettingenRooms as $roomName) {
        $result = $roomCreator->createRoomTable('goettingen', $roomName);

        if ($result['success']) {
            echo "<p>✅ <strong>{$result['table_name']}</strong>: {$result['record_count']} records (Room: {$roomName})</p>";
            $totalCreated++;
            $totalRecords += $result['record_count'];
            $results[] = $result;
        } else {
            echo "<p>❌ <strong>{$result['table_name']}</strong>: {$result['error']}</p>";
        }
    }

    echo "<hr>";
    echo "<h3>🎉 Room Tables Creation Complete!</h3>";
    echo "<p>✅ Created <strong>{$totalCreated}</strong> room-specific tables</p>";
    echo "<p>📊 Total records distributed: <strong>{$totalRecords}</strong></p>";

    // Summary table
    echo "<h4>Created Room Tables Summary:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table Name</th><th>Room Name</th><th>Records</th></tr>";

    foreach ($results as $result) {
        if ($result['success']) {
            echo "<tr>";
            echo "<td><strong>{$result['table_name']}</strong></td>";
            echo "<td>{$result['room_name']}</td>";
            echo "<td>{$result['record_count']}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

    echo "<hr>";
    echo "<h3>📊 Usage Examples:</h3>";
    echo "<ul>";
    echo "<li>Query specific room: <code>SELECT * FROM iwatoyama_shared_d402a WHERE check_in >= '2024-01-01'</code></li>";
    echo "<li>Room occupancy rate: Calculate per room for detailed analysis</li>";
    echo "<li>Room comparison: Compare performance between different room types</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>