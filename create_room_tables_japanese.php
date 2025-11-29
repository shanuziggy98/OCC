<?php
// Create sub-tables for hostel properties with original Japanese room names
header("Content-Type: text/html; charset=utf-8");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<h2>Creating Room-Specific Tables with Japanese Names</h2>";
echo "<hr>";

class JapaneseRoomTableCreator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function sanitizeTableName($propertyName, $roomName) {
        // Create table name with property prefix but keep room name readable
        $prefix = strtolower($propertyName);

        // Create a simple hash of the room name to ensure unique table names
        // but keep them readable by including the original room name in metadata
        $hash = substr(md5($roomName), 0, 8);
        $tableName = $prefix . '_room_' . $hash;

        return $tableName;
    }

    public function createRoomTable($propertyName, $roomName) {
        $tableName = $this->sanitizeTableName($propertyName, $roomName);

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
                COMMENT='Room table for {$roomName} in {$propertyName}'
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

    public function getRoomNamesFromProperty($propertyName) {
        $tableName = strtolower($propertyName);

        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT room_type, COUNT(*) as booking_count
                FROM `{$tableName}`
                WHERE room_type IS NOT NULL
                AND room_type != ''
                GROUP BY room_type
                ORDER BY room_type
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "<p>❌ Error getting room names from {$propertyName}: " . $e->getMessage() . "</p>";
            return [];
        }
    }

    public function createRoomMappingTable() {
        // Create a mapping table to track which table corresponds to which room
        $sql = "
            CREATE TABLE IF NOT EXISTS room_table_mapping (
                id INT AUTO_INCREMENT PRIMARY KEY,
                property_name VARCHAR(255) NOT NULL,
                room_name VARCHAR(255) NOT NULL,
                table_name VARCHAR(255) NOT NULL,
                record_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_property (property_name),
                INDEX idx_room (room_name),
                INDEX idx_table (table_name),
                UNIQUE KEY unique_mapping (property_name, room_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->pdo->exec($sql);
    }

    public function saveRoomMapping($propertyName, $roomName, $tableName, $recordCount) {
        $stmt = $this->pdo->prepare("
            INSERT INTO room_table_mapping (property_name, room_name, table_name, record_count)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                table_name = VALUES(table_name),
                record_count = VALUES(record_count)
        ");

        $stmt->execute([$propertyName, $roomName, $tableName, $recordCount]);
    }
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "<p>✅ Connected to database successfully!</p>";

    $roomCreator = new JapaneseRoomTableCreator($pdo);

    // Create the mapping table first
    $roomCreator->createRoomMappingTable();
    echo "<p>✅ Created room mapping table</p>";

    // Check actual room data for each hostel property
    echo "<h3>🔍 Analyzing Room Data in Hostel Properties</h3>";

    $hostelProperties = ['iwatoyama', 'littlehouse', 'goettingen'];
    $allResults = [];

    foreach ($hostelProperties as $property) {
        echo "<h4>{$property}:</h4>";
        $roomData = $roomCreator->getRoomNamesFromProperty($property);

        if (!empty($roomData)) {
            echo "<p>Found <strong>" . count($roomData) . "</strong> unique rooms:</p>";
            echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
            echo "<tr><th>Room Name (Japanese)</th><th>Booking Count</th></tr>";

            foreach ($roomData as $room) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($room['room_type']) . "</strong></td>";
                echo "<td>{$room['booking_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No room data found</p>";
        }
        echo "<hr>";
    }

    // Create room-specific tables
    echo "<h3>🏗️ Creating Room-Specific Tables</h3>";

    $totalCreated = 0;
    $totalRecords = 0;

    foreach ($hostelProperties as $property) {
        echo "<h4>Creating tables for: {$property}</h4>";

        $roomData = $roomCreator->getRoomNamesFromProperty($property);

        foreach ($roomData as $roomInfo) {
            $roomName = $roomInfo['room_type'];
            $bookingCount = $roomInfo['booking_count'];

            $result = $roomCreator->createRoomTable($property, $roomName);

            if ($result['success']) {
                echo "<p>✅ <strong>{$result['table_name']}</strong></p>";
                echo "<p style='margin-left: 20px;'>🏠 Room: <strong>{$roomName}</strong></p>";
                echo "<p style='margin-left: 20px;'>📊 Records: <strong>{$result['record_count']}</strong></p>";

                // Save mapping
                $roomCreator->saveRoomMapping(
                    $property,
                    $roomName,
                    $result['table_name'],
                    $result['record_count']
                );

                $totalCreated++;
                $totalRecords += $result['record_count'];
                $allResults[] = $result;
            } else {
                echo "<p>❌ <strong>{$result['table_name']}</strong>: {$result['error']}</p>";
                echo "<p style='margin-left: 20px;'>🏠 Room: <strong>{$roomName}</strong></p>";
            }
        }
        echo "<hr>";
    }

    echo "<h3>🎉 Room Tables Creation Complete!</h3>";
    echo "<p>✅ Created <strong>{$totalCreated}</strong> room-specific tables</p>";
    echo "<p>📊 Total records distributed: <strong>{$totalRecords}</strong></p>";

    // Show the mapping table for reference
    echo "<h4>Room Table Mapping (for reference):</h4>";
    $mappingStmt = $pdo->query("
        SELECT property_name, room_name, table_name, record_count
        FROM room_table_mapping
        ORDER BY property_name, room_name
    ");
    $mappings = $mappingStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($mappings)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Property</th><th>Room Name (Japanese)</th><th>Table Name</th><th>Records</th></tr>";

        foreach ($mappings as $mapping) {
            echo "<tr>";
            echo "<td>{$mapping['property_name']}</td>";
            echo "<td><strong>{$mapping['room_name']}</strong></td>";
            echo "<td><code>{$mapping['table_name']}</code></td>";
            echo "<td>{$mapping['record_count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<hr>";
    echo "<h3>📊 Usage Examples:</h3>";
    echo "<ul>";
    echo "<li><strong>Find table for specific room:</strong><br>";
    echo "<code>SELECT table_name FROM room_table_mapping WHERE room_name = '月沈原101'</code></li>";
    echo "<li><strong>Query specific room data:</strong><br>";
    echo "<code>SELECT * FROM [table_name] WHERE check_in >= '2024-01-01'</code></li>";
    echo "<li><strong>Get all rooms for a property:</strong><br>";
    echo "<code>SELECT room_name, table_name FROM room_table_mapping WHERE property_name = 'iwatoyama'</code></li>";
    echo "</ul>";

    echo "<h3>🎯 Japanese Room Names Preserved:</h3>";
    echo "<p>✅ All original Japanese room names are preserved in the mapping table</p>";
    echo "<p>✅ Use the mapping table to find the correct table for each Japanese room name</p>";
    echo "<p>✅ Each room table contains only bookings for that specific room</p>";

} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>