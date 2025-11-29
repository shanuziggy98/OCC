<?php
/**
 * Auto-Update Property Rooms Script
 *
 * This script automatically detects room types from existing property tables
 * and updates the property_sheets table with:
 * - property_type (hostel or guesthouse)
 * - room_list (comma-separated room names for hostels)
 * - total_rooms (count of rooms)
 *
 * Run this script whenever you need to sync room data from existing tables
 */

header("Content-Type: text/html; charset=utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Auto-Update Property Rooms</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #3498db; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-hostel { background: #e74c3c; color: white; }
        .badge-guesthouse { background: #27ae60; color: white; }
        .room-list { font-family: monospace; font-size: 11px; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-number { font-size: 36px; font-weight: bold; }
        .stat-label { font-size: 14px; opacity: 0.9; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔄 Auto-Update Property Rooms</h1>";
echo "<div class='info'>This script will scan all property tables and automatically update property_sheets with room information.</div>";

class PropertyRoomUpdater {
    private $pdo;
    private $results = [];
    private $stats = [
        'total_properties' => 0,
        'hostels_found' => 0,
        'guesthouses_found' => 0,
        'total_rooms' => 0,
        'updated' => 0,
        'errors' => 0
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all property tables from database
     */
    private function getPropertyTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Filter out system tables and keep only property tables
        $propertyTables = [];
        $excludeTables = ['bookings', 'properties', 'property_sheets', 'property_sheets_status'];

        foreach ($allTables as $table) {
            if (!in_array($table, $excludeTables) && !preg_match('/^(月沈原|岩戸山|共用|女子|男子|ダブル|ツイン|ユニーク|ファミリー|いぬねこ|秘密)/', $table)) {
                $propertyTables[] = $table;
            }
        }

        return $propertyTables;
    }

    /**
     * Check if a table has a room_type column
     */
    private function hasRoomTypeColumn($tableName) {
        try {
            $stmt = $this->pdo->query("DESCRIBE `{$tableName}`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return in_array('room_type', $columns);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get distinct room types from a property table
     */
    private function getRoomTypesFromTable($tableName) {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT room_type
                FROM `{$tableName}`
                WHERE room_type IS NOT NULL
                AND room_type != ''
                ORDER BY room_type
            ");
            $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $rooms;
        } catch (Exception $e) {
            echo "<div class='error'>Error getting rooms from {$tableName}: " . $e->getMessage() . "</div>";
            return [];
        }
    }

    /**
     * Get predefined room lists (from create_exact_room_tables.php)
     */
    private function getPredefinedRoomLists() {
        return [
            'iwatoyama' => [
                '岩戸山全体', 'ファミリー401', '共用D402A', '共用D402B', '共用D402C',
                '共用D402D', '共用D402E', '共用D402F', 'ダブル403', 'ダブル404',
                'ダブル405', 'ユニーク406', 'ユニーク407', 'ツイン408', 'ファミリー301',
                '女子D302A', '女子D302B', '女子D302C', '女子D302D', '女子D302E',
                '女子D302F', 'ダブル303', 'ダブル304', 'ダブル305', 'ユニーク306',
                'ユニーク307', 'ツイン308', '男子D202A', '男子D202B', '男子D202C',
                '男子D202D', '男子D202E', '男子D202F', 'ダブル203', 'ダブル204',
                'ダブル205', 'ユニーク206', 'ユニーク207', 'ツイン208'
            ],
            'goettingen' => [
                '月沈原101', '月沈原102', '月沈原201', '月沈原202', '月沈原203',
                '月沈原204', '月沈原205', '月沈原301', '月沈原302', '月沈原303',
                '月沈原304'
            ],
            'littlehouse' => [
                'いぬねこ1F', 'いぬねこ2F', '秘密の部屋'
            ]
        ];
    }

    /**
     * Analyze a property and determine if it's a hostel or guesthouse
     * Uses predefined room lists for known hostels
     */
    private function analyzeProperty($tableName) {
        // Get predefined room lists
        $predefinedRooms = $this->getPredefinedRoomLists();

        // Check if this is a known hostel with predefined rooms
        if (isset($predefinedRooms[$tableName])) {
            $rooms = $predefinedRooms[$tableName];
            // Count non-staff rooms for total_rooms (exclude rooms ending with 'sf')
            $nonStaffRooms = array_filter($rooms, function($room) {
                return !preg_match('/sf$/i', trim($room));
            });
            return [
                'property_name' => $tableName,
                'property_type' => 'hostel',
                'room_list' => implode(',', $rooms),
                'total_rooms' => count($nonStaffRooms),
                'rooms' => $rooms
            ];
        }

        // For other properties, check if they have room_type column with multiple rooms
        $hasRoomType = $this->hasRoomTypeColumn($tableName);

        if (!$hasRoomType) {
            // No room_type column = guesthouse
            return [
                'property_name' => $tableName,
                'property_type' => 'guesthouse',
                'room_list' => null,
                'total_rooms' => 1,
                'rooms' => []
            ];
        }

        $rooms = $this->getRoomTypesFromTable($tableName);

        if (empty($rooms)) {
            // Has room_type column but no distinct rooms = guesthouse
            return [
                'property_name' => $tableName,
                'property_type' => 'guesthouse',
                'room_list' => null,
                'total_rooms' => 1,
                'rooms' => []
            ];
        }

        if (count($rooms) > 1) {
            // Multiple distinct room types = hostel
            // Count non-staff rooms for total_rooms (exclude rooms ending with 'sf')
            $nonStaffRooms = array_filter($rooms, function($room) {
                return !preg_match('/sf$/i', trim($room));
            });
            return [
                'property_name' => $tableName,
                'property_type' => 'hostel',
                'room_list' => implode(',', $rooms),
                'total_rooms' => count($nonStaffRooms),
                'rooms' => $rooms
            ];
        } else {
            // Only one room type = guesthouse
            return [
                'property_name' => $tableName,
                'property_type' => 'guesthouse',
                'room_list' => null,
                'total_rooms' => 1,
                'rooms' => []
            ];
        }
    }

    /**
     * Update property_sheets table with room information
     */
    private function updatePropertySheet($propertyInfo) {
        try {
            // Check if property exists in property_sheets
            $stmt = $this->pdo->prepare("
                SELECT id, google_sheet_url, sheet_description
                FROM property_sheets
                WHERE property_name = ?
            ");
            $stmt->execute([$propertyInfo['property_name']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing property
                $updateStmt = $this->pdo->prepare("
                    UPDATE property_sheets
                    SET property_type = ?,
                        room_list = ?,
                        total_rooms = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE property_name = ?
                ");
                $updateStmt->execute([
                    $propertyInfo['property_type'],
                    $propertyInfo['room_list'],
                    $propertyInfo['total_rooms'],
                    $propertyInfo['property_name']
                ]);

                $propertyInfo['action'] = 'updated';
                $this->stats['updated']++;
            } else {
                // Insert new property
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO property_sheets (
                        property_name,
                        google_sheet_url,
                        sheet_description,
                        property_type,
                        room_list,
                        total_rooms
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $propertyInfo['property_name'],
                    'https://docs.google.com/spreadsheets/d/YOUR_SHEET_ID/edit',
                    ucfirst($propertyInfo['property_name']) . ' - Auto-detected ' . $propertyInfo['property_type'],
                    $propertyInfo['property_type'],
                    $propertyInfo['room_list'],
                    $propertyInfo['total_rooms']
                ]);

                $propertyInfo['action'] = 'inserted';
                $this->stats['updated']++;
            }

            // Update stats
            $this->stats['total_properties']++;
            $this->stats['total_rooms'] += $propertyInfo['total_rooms'];

            if ($propertyInfo['property_type'] === 'hostel') {
                $this->stats['hostels_found']++;
            } else {
                $this->stats['guesthouses_found']++;
            }

            return $propertyInfo;

        } catch (Exception $e) {
            $propertyInfo['action'] = 'error';
            $propertyInfo['error'] = $e->getMessage();
            $this->stats['errors']++;
            return $propertyInfo;
        }
    }

    /**
     * Run the auto-update process
     */
    public function run() {
        echo "<h2>📊 Scanning Property Tables...</h2>";

        $propertyTables = $this->getPropertyTables();

        echo "<div class='info'>Found <strong>" . count($propertyTables) . "</strong> property tables to analyze.</div>";

        echo "<table>";
        echo "<tr>
                <th>Property Name</th>
                <th>Type</th>
                <th>Rooms</th>
                <th>Room List</th>
                <th>Action</th>
              </tr>";

        foreach ($propertyTables as $tableName) {
            $propertyInfo = $this->analyzeProperty($tableName);
            $result = $this->updatePropertySheet($propertyInfo);
            $this->results[] = $result;

            $typeBadge = $result['property_type'] === 'hostel'
                ? "<span class='badge badge-hostel'>HOSTEL</span>"
                : "<span class='badge badge-guesthouse'>GUESTHOUSE</span>";

            $roomListDisplay = $result['room_list']
                ? "<div class='room-list' title='" . htmlspecialchars($result['room_list']) . "'>" . htmlspecialchars($result['room_list']) . "</div>"
                : "<em>N/A</em>";

            $actionIcon = $result['action'] === 'updated' ? '✅ Updated'
                : ($result['action'] === 'inserted' ? '➕ Inserted'
                : '❌ Error');

            echo "<tr>
                    <td><strong>{$result['property_name']}</strong></td>
                    <td>{$typeBadge}</td>
                    <td><strong>{$result['total_rooms']}</strong></td>
                    <td>{$roomListDisplay}</td>
                    <td>{$actionIcon}</td>
                  </tr>";
        }

        echo "</table>";

        return $this->results;
    }

    /**
     * Get statistics summary
     */
    public function getStats() {
        return $this->stats;
    }

    /**
     * Display statistics
     */
    public function displayStats() {
        echo "<h2>📈 Summary Statistics</h2>";

        echo "<div class='stats'>";

        echo "<div class='stat-card'>
                <div class='stat-number'>{$this->stats['total_properties']}</div>
                <div class='stat-label'>Total Properties</div>
              </div>";

        echo "<div class='stat-card'>
                <div class='stat-number'>{$this->stats['hostels_found']}</div>
                <div class='stat-label'>Hostels</div>
              </div>";

        echo "<div class='stat-card'>
                <div class='stat-number'>{$this->stats['guesthouses_found']}</div>
                <div class='stat-label'>Guesthouses</div>
              </div>";

        echo "<div class='stat-card'>
                <div class='stat-number'>{$this->stats['total_rooms']}</div>
                <div class='stat-label'>Total Rooms</div>
              </div>";

        echo "</div>";

        if ($this->stats['errors'] > 0) {
            echo "<div class='warning'>⚠️ <strong>{$this->stats['errors']}</strong> properties had errors during update.</div>";
        }
    }

    /**
     * Verify the updates
     */
    public function verify() {
        echo "<h2>🔍 Verification</h2>";

        try {
            $stmt = $this->pdo->query("
                SELECT
                    property_name,
                    property_type,
                    total_rooms,
                    LEFT(room_list, 100) as room_sample,
                    is_active
                FROM property_sheets
                ORDER BY display_order, property_name
            ");
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr>
                    <th>Property Name</th>
                    <th>Type</th>
                    <th>Total Rooms</th>
                    <th>Room Sample</th>
                    <th>Status</th>
                  </tr>";

            foreach ($properties as $prop) {
                $typeBadge = $prop['property_type'] === 'hostel'
                    ? "<span class='badge badge-hostel'>HOSTEL</span>"
                    : "<span class='badge badge-guesthouse'>GUESTHOUSE</span>";

                $statusBadge = $prop['is_active']
                    ? "✅ Active"
                    : "❌ Inactive";

                $roomSample = $prop['room_sample']
                    ? "<div class='room-list'>" . htmlspecialchars($prop['room_sample']) . "...</div>"
                    : "<em>N/A</em>";

                echo "<tr>
                        <td><strong>{$prop['property_name']}</strong></td>
                        <td>{$typeBadge}</td>
                        <td><strong>{$prop['total_rooms']}</strong></td>
                        <td>{$roomSample}</td>
                        <td>{$statusBadge}</td>
                      </tr>";
            }

            echo "</table>";

        } catch (Exception $e) {
            echo "<div class='error'>Verification error: " . $e->getMessage() . "</div>";
        }
    }
}

// Main execution
try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8mb4");

    echo "<div class='success'>✅ Connected to database successfully!</div>";

    $updater = new PropertyRoomUpdater($pdo);

    // Run the auto-update
    $updater->run();

    // Display statistics
    $updater->displayStats();

    // Verify the updates
    $updater->verify();

    echo "<h2>✅ Auto-Update Complete!</h2>";
    echo "<div class='success'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Review the results above<br>";
    echo "2. Update Google Sheet URLs in property_sheets if needed<br>";
    echo "3. Upload the updated <code>occupancy_metrics_api.php</code> to your server<br>";
    echo "4. Test your dashboard to ensure everything works correctly";
    echo "</div>";

    echo "<h2>📝 SQL Queries for Manual Verification</h2>";
    echo "<div class='info'>";
    echo "<strong>Check hostels:</strong><br>";
    echo "<code>SELECT * FROM property_sheets WHERE property_type = 'hostel';</code><br><br>";
    echo "<strong>Check guesthouses:</strong><br>";
    echo "<code>SELECT * FROM property_sheets WHERE property_type = 'guesthouse';</code><br><br>";
    echo "<strong>View all with room counts:</strong><br>";
    echo "<code>SELECT property_name, property_type, total_rooms FROM property_sheets ORDER BY total_rooms DESC;</code>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Database Error: " . $e->getMessage() . "</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>
