<?php
/**
 * Debug Kaguya Room Data
 * Check what room names are actually in the database vs what we expect
 */

$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

header("Content-Type: text/plain; charset=utf-8");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "=== KAGUYA ROOM DATA DEBUG ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

    // Check 1: Total records in kaguya table
    echo "Check 1: Total records in kaguya table\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kaguya");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Total records: " . $result['count'] . "\n\n";

    // Check 2: What room names are actually in the database?
    echo "Check 2: Actual room names in kaguya table\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT
            room_type,
            COUNT(*) as count
        FROM kaguya
        GROUP BY room_type
        ORDER BY count DESC
    ");
    $actualRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($actualRooms) . " different room types:\n\n";
    foreach ($actualRooms as $room) {
        $roomName = $room['room_type'] ?: '(empty/null)';
        echo sprintf("  %-30s: %d bookings\n", $roomName, $room['count']);
    }

    echo "\n";

    // Check 3: What room names are configured in property_sheets?
    echo "Check 3: Expected room names from property_sheets\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT room_list
        FROM property_sheets
        WHERE property_name = 'kaguya'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $expectedRooms = $result['room_list'];
    echo "Expected room_list: " . ($expectedRooms ?: '(empty)') . "\n";

    if (!empty($expectedRooms)) {
        $roomArray = explode(',', $expectedRooms);
        echo "\nExpected rooms (split by comma):\n";
        foreach ($roomArray as $room) {
            echo "  - '" . trim($room) . "'\n";
        }
    }

    echo "\n";

    // Check 4: Compare expected vs actual
    echo "Check 4: Comparison\n";
    echo str_repeat("-", 60) . "\n";

    if (!empty($expectedRooms)) {
        $expectedRoomArray = array_map('trim', explode(',', $expectedRooms));
        $actualRoomNames = array_column($actualRooms, 'room_type');

        echo "Expected rooms: " . implode(", ", $expectedRoomArray) . "\n";
        echo "Actual rooms: " . implode(", ", array_filter($actualRoomNames)) . "\n\n";

        echo "Matching analysis:\n";
        foreach ($expectedRoomArray as $expected) {
            $found = in_array($expected, $actualRoomNames);
            echo ($found ? "✅" : "❌") . " Expected: '$expected' ";
            echo ($found ? "- FOUND\n" : "- NOT FOUND\n");
        }
    }

    echo "\n";

    // Check 5: Sample data to see actual room_type values
    echo "Check 5: Sample records with room types\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT check_in, check_out, room_type, accommodation_fee
        FROM kaguya
        WHERE room_type IS NOT NULL AND room_type != ''
        ORDER BY check_in DESC
        LIMIT 10
    ");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $i => $record) {
        echo "\nRecord " . ($i + 1) . ":\n";
        echo "  Check-in: " . $record['check_in'] . "\n";
        echo "  Check-out: " . $record['check_out'] . "\n";
        echo "  Room type: '" . $record['room_type'] . "'\n";
        echo "  Fee: ¥" . number_format($record['accommodation_fee']) . "\n";
    }

    echo "\n";

    // Check 6: Check for encoding issues
    echo "Check 6: Room name character analysis\n";
    echo str_repeat("-", 60) . "\n";

    $stmt = $pdo->query("
        SELECT DISTINCT room_type
        FROM kaguya
        WHERE room_type IS NOT NULL AND room_type != ''
    ");
    $distinctRooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($distinctRooms as $room) {
        echo "\nRoom: '$room'\n";
        echo "  Length: " . mb_strlen($room) . " chars\n";
        echo "  Hex: " . bin2hex($room) . "\n";

        // Check if it matches any expected room
        if (!empty($expectedRooms)) {
            $expectedRoomArray = array_map('trim', explode(',', $expectedRooms));
            foreach ($expectedRoomArray as $expected) {
                $match = ($room === $expected);
                if ($match) {
                    echo "  ✅ Exact match: '$expected'\n";
                }
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";

    // Recommendations
    echo "\nRECOMMENDATIONS:\n\n";

    if (empty($expectedRooms)) {
        echo "❌ PROBLEM: room_list in property_sheets is empty!\n";
        echo "   SOLUTION: Update property_sheets to set room_list correctly\n\n";
        echo "   Run this SQL:\n";
        echo "   UPDATE property_sheets\n";
        echo "   SET room_list = '風の間,鳥の間,花の間'\n";
        echo "   WHERE property_name = 'kaguya';\n";
    } else {
        $expectedRoomArray = array_map('trim', explode(',', $expectedRooms));
        $actualRoomNames = array_column($actualRooms, 'room_type');

        $mismatch = false;
        foreach ($expectedRoomArray as $expected) {
            if (!in_array($expected, $actualRoomNames)) {
                $mismatch = true;
                break;
            }
        }

        if ($mismatch) {
            echo "❌ PROBLEM: Room names in database don't match expected names!\n";
            echo "   Expected: " . implode(", ", $expectedRoomArray) . "\n";
            echo "   Actual: " . implode(", ", array_filter($actualRoomNames)) . "\n\n";
            echo "   SOLUTION: Update room_list to match actual room names in data\n";
        } else {
            echo "✅ Room names match! The problem might be elsewhere.\n";
            echo "   Check the API endpoint or frontend code.\n";
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
