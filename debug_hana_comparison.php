<?php
// Compare different calculation methods for 花の間 bookings
header("Content-Type: text/html; charset=UTF-8");

$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$year = 2025;
$month = 9;
$roomName = '花の間';
$tableName = 'kaguya';

$firstDayOfMonth = "{$year}-09-01";
$lastDayOfMonth = "{$year}-09-30";

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; }
h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th { background: #2196F3; color: white; padding: 12px; text-align: left; }
td { padding: 10px; border-bottom: 1px solid #ddd; }
.error { background: #ffebee; }
.correct { background: #e8f5e9; }
.warning { background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; }
.code { background: #f5f5f5; padding: 15px; font-family: monospace; margin: 10px 0; border-left: 3px solid #2196F3; }
</style></head><body><div class='container'>";

echo "<h2>🔍 Detailed Analysis: 花の間 September 2025 Calculation</h2>";

// Get raw data from database
$stmt = $pdo->prepare("
    SELECT
        id,
        check_in,
        check_out,
        night_count,
        accommodation_fee,
        room_type
    FROM `{$tableName}`
    WHERE room_type = ?
    AND accommodation_fee > 0
    AND check_in <= ?
    AND check_out > ?
    ORDER BY check_in
");
$stmt->execute([$roomName, $lastDayOfMonth, $firstDayOfMonth]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>📊 Raw Database Data</h3>";
echo "<table>";
echo "<tr><th>ID</th><th>Check-In</th><th>Check-Out</th><th>Night Count (DB)</th><th>Fee</th></tr>";
foreach ($bookings as $booking) {
    echo "<tr>";
    echo "<td>{$booking['id']}</td>";
    echo "<td>{$booking['check_in']}</td>";
    echo "<td>{$booking['check_out']}</td>";
    echo "<td>{$booking['night_count']}</td>";
    echo "<td>¥" . number_format($booking['accommodation_fee']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>🧮 Calculation Comparison: Method 1 (Current/Buggy) vs Method 2 (Correct)</h3>";

$method1_total = 0; // Current implementation (has bugs)
$method2_total = 0; // Correct implementation
$totalRevenue1 = 0;
$totalRevenue2 = 0;

echo "<table>";
echo "<tr>";
echo "<th>Booking ID</th>";
echo "<th>Check-In → Check-Out</th>";
echo "<th>DB Nights</th>";
echo "<th>Method 1<br>(min + modify)</th>";
echo "<th>Method 2<br>(correct)</th>";
echo "<th>Explanation</th>";
echo "</tr>";

foreach ($bookings as $booking) {
    $checkIn = new DateTime($booking['check_in']);
    $checkOut = new DateTime($booking['check_out']);
    $dbNightCount = intval($booking['night_count']);
    $fee = floatval($booking['accommodation_fee']);

    // Method 1: Current implementation (buggy)
    $monthStart = new DateTime($firstDayOfMonth);
    $monthEnd = new DateTime($lastDayOfMonth);
    $monthEnd->setTime(23, 59, 59);

    $effectiveStart1 = max($checkIn, $monthStart);
    $effectiveEnd1 = min($checkOut, clone($monthEnd)->modify('+1 day'));
    $nights1 = $effectiveStart1->diff($effectiveEnd1)->days;

    // Method 2: Correct implementation
    // The checkout date should NOT be counted as a night
    // We need to count nights where the guest actually sleeps
    $effectiveStart2 = max($checkIn, new DateTime($firstDayOfMonth));
    $effectiveCheckOut2 = min($checkOut, (new DateTime($lastDayOfMonth))->modify('+1 day'));

    // Create a proper end date for night counting (don't include checkout day)
    $nightEnd2 = min($checkOut, (new DateTime($lastDayOfMonth))->modify('+1 day'));

    // Count actual nights in the month
    $nights2 = 0;
    $currentDate = clone $effectiveStart2;
    $monthEndDate = new DateTime($lastDayOfMonth);
    $monthEndDate->setTime(23, 59, 59);

    while ($currentDate < $checkOut && $currentDate <= $monthEndDate) {
        $nights2++;
        $currentDate->modify('+1 day');
    }

    // Method 3: Simple correct logic
    // Count = min(actual_nights_in_booking, nights_that_fall_in_september)
    $nights3 = 0;
    $currentNight = clone $checkIn;
    $septEnd = new DateTime('2025-09-30');
    $septEnd->setTime(23, 59, 59);

    while ($currentNight < $checkOut) {
        if ($currentNight >= new DateTime($firstDayOfMonth) && $currentNight <= $septEnd) {
            $nights3++;
        }
        $currentNight->modify('+1 day');
    }

    $method1_total += $nights1;
    $method2_total += $nights3;

    $revenue1 = ($dbNightCount > 0) ? ($nights1 / $dbNightCount) * $fee : 0;
    $revenue2 = ($dbNightCount > 0) ? ($nights3 / $dbNightCount) * $fee : 0;

    $totalRevenue1 += $revenue1;
    $totalRevenue2 += $revenue2;

    $rowClass = ($nights1 != $nights3) ? 'error' : 'correct';

    echo "<tr class='{$rowClass}'>";
    echo "<td>{$booking['id']}</td>";
    echo "<td>{$booking['check_in']} → {$booking['check_out']}</td>";
    echo "<td>{$dbNightCount}</td>";
    echo "<td><strong>{$nights1}</strong> nights</td>";
    echo "<td><strong>{$nights3}</strong> nights</td>";
    echo "<td>";

    if ($nights1 != $nights3) {
        echo "❌ Method 1 is wrong! ";
        if ($nights1 > $nights3) {
            echo "Over-counting by " . ($nights1 - $nights3) . " night(s).";
        }
    } else {
        echo "✅ Both methods agree";
    }

    // Show which dates are counted
    echo "<br><small>Nights in Sept: ";
    $currentNight = clone $checkIn;
    $septDates = [];
    while ($currentNight < $checkOut) {
        if ($currentNight >= new DateTime($firstDayOfMonth) && $currentNight <= $septEnd) {
            $septDates[] = $currentNight->format('m/d');
        }
        $currentNight->modify('+1 day');
    }
    echo implode(', ', $septDates);
    echo "</small>";

    echo "</td>";
    echo "</tr>";
}

echo "<tr style='background: #e3f2fd; font-weight: bold;'>";
echo "<td colspan='3'>TOTAL</td>";
echo "<td>{$method1_total} nights</td>";
echo "<td>{$method2_total} nights</td>";
echo "<td>";
if ($method1_total != $method2_total) {
    echo "❌ Difference: " . ($method1_total - $method2_total) . " nights";
} else {
    echo "✅ Match!";
}
echo "</td>";
echo "</tr>";

echo "<tr style='background: #fff3cd;'>";
echo "<td colspan='3'>REVENUE</td>";
echo "<td>¥" . number_format($totalRevenue1, 2) . "</td>";
echo "<td>¥" . number_format($totalRevenue2, 2) . "</td>";
echo "<td>";
if (abs($totalRevenue1 - $totalRevenue2) > 0.01) {
    echo "❌ Difference: ¥" . number_format($totalRevenue1 - $totalRevenue2, 2);
} else {
    echo "✅ Match!";
}
echo "</td>";
echo "</tr>";

echo "</table>";

echo "<div class='warning'>";
echo "<strong>🎯 Correct Answer (Expected from Web App):</strong><br>";
echo "Based on database night_count values:<br>";
echo "• Booking #1: 1 night in September (check actual dates)<br>";
echo "• Booking #2: 4 nights in September (check actual dates)<br>";
echo "• Booking #3: 2 nights in September (Sept 29-30 only, not Oct 1)<br>";
echo "<strong>Total: 7 nights</strong> (matching web app)<br>";
echo "<strong>Revenue: ¥322,656</strong> (matching web app)<br>";
echo "</div>";

echo "<div class='code'>";
echo "<strong>❌ THE BUG in occupancy_metrics_api.php line 131:</strong><br><br>";
echo "<code>\$effectiveEnd = min(\$checkOut, \$monthEnd->modify('+1 day'));</code><br><br>";
echo "This causes checkout dates to be incorrectly included in the night count!<br><br>";
echo "<strong>✅ CORRECT LOGIC:</strong><br>";
echo "Count nights where the guest actually sleeps (check-in date through day before check-out).<br>";
echo "A booking from Sept 13 to Sept 14 = 1 night (the night of Sept 13).<br>";
echo "A booking from Sept 29 to Oct 3 = 2 nights in September (Sept 29 and 30 only).";
echo "</div>";

echo "<h3>🔍 Verify Actual Database Values</h3>";
echo "<p>Let's check if the check_out dates in the database match what we see:</p>";
$stmt = $pdo->prepare("
    SELECT id, check_in, check_out, night_count,
           DATEDIFF(check_out, check_in) as calculated_nights
    FROM `{$tableName}`
    WHERE room_type = ?
    AND accommodation_fee > 0
    AND check_in <= ?
    AND check_out > ?
");
$stmt->execute([$roomName, $lastDayOfMonth, $firstDayOfMonth]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Check-In</th><th>Check-Out</th><th>night_count (DB)</th><th>DATEDIFF</th><th>Match?</th></tr>";
foreach ($bookings as $b) {
    $match = ($b['night_count'] == $b['calculated_nights']) ? '✅' : '❌';
    $rowClass = ($b['night_count'] == $b['calculated_nights']) ? 'correct' : 'error';
    echo "<tr class='{$rowClass}'>";
    echo "<td>{$b['id']}</td>";
    echo "<td>{$b['check_in']}</td>";
    echo "<td>{$b['check_out']}</td>";
    echo "<td>{$b['night_count']}</td>";
    echo "<td>{$b['calculated_nights']}</td>";
    echo "<td>{$match}</td>";
    echo "</tr>";
}
echo "</table>";

echo "</div></body></html>";
?>
