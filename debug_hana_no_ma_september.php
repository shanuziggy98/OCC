<?php
// Debug script to show detailed booking night calculation for 花の間 in September
header("Content-Type: text/html; charset=UTF-8");

// Database configuration
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

// Get parameters from URL or use defaults
$year = isset($_GET['year']) ? intval($_GET['year']) : 2025;
$month = isset($_GET['month']) ? intval($_GET['month']) : 9;  // September
$roomName = isset($_GET['room']) ? $_GET['room'] : '花の間';
$propertyName = isset($_GET['property']) ? $_GET['property'] : 'Kaguya';

// Sanitize table name
$tableName = strtolower($propertyName);
$tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);

// Get month boundaries
$firstDayOfMonth = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
$daysInMonth = date('t', strtotime($firstDayOfMonth));

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Debug: {$roomName} Booking Calculation</title>
    <style>
        body { font-family: 'MS Gothic', 'Arial', sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-left: 4px solid #2196F3; padding-left: 10px; }
        .summary { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #4CAF50; }
        .booking { background: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .booking.active { border-left: 4px solid #4CAF50; background: #f1f8f4; }
        .booking.partial { border-left: 4px solid #FF9800; background: #fff8e1; }
        .booking.outside { border-left: 4px solid #f44336; background: #ffebee; opacity: 0.7; }
        .dates { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; margin: 10px 0; }
        .date-box { padding: 8px; text-align: center; border-radius: 4px; font-size: 12px; }
        .date-box.booked { background: #4CAF50; color: white; font-weight: bold; }
        .date-box.partial { background: #FF9800; color: white; font-weight: bold; }
        .date-box.available { background: #f0f0f0; color: #999; }
        .calculation { background: #e3f2fd; padding: 12px; border-radius: 5px; margin: 10px 0; font-family: 'Courier New', monospace; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2196F3; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        .label { font-weight: bold; color: #555; margin-right: 8px; }
        .value { color: #333; }
        .total { font-size: 1.2em; font-weight: bold; color: #4CAF50; }
        .form-controls { background: #fafafa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .form-controls input, .form-controls select { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .form-controls button { padding: 8px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .form-controls button:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Booking Night Calculation Debug</h1>";
echo "<div class='form-controls'>
    <form method='GET'>
        <label>Property: <input type='text' name='property' value='{$propertyName}'></label>
        <label>Room: <input type='text' name='room' value='{$roomName}'></label>
        <label>Year: <input type='number' name='year' value='{$year}'></label>
        <label>Month: <input type='number' name='month' value='{$month}' min='1' max='12'></label>
        <button type='submit'>Analyze</button>
    </form>
</div>";

echo "<div class='summary'>";
echo "<span class='label'>Property:</span><span class='value'>{$propertyName}</span><br>";
echo "<span class='label'>Room:</span><span class='value'>{$roomName}</span><br>";
echo "<span class='label'>Table:</span><span class='value'>{$tableName}</span><br>";
echo "<span class='label'>Period:</span><span class='value'>{$year}年{$month}月 ({$firstDayOfMonth} to {$lastDayOfMonth})</span><br>";
echo "<span class='label'>Days in Month:</span><span class='value'>{$daysInMonth} days</span>";
echo "</div>";

// Check if table exists
$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
$stmt->execute([$tableName]);
if ($stmt->rowCount() == 0) {
    echo "<div class='warning'>❌ Table '{$tableName}' does not exist!</div>";
    echo "</div></body></html>";
    exit;
}

// Get all bookings for this room that overlap with the target month
$stmt = $pdo->prepare("
    SELECT
        id,
        check_in,
        check_out,
        night_count,
        accommodation_fee,
        booking_date,
        room_type,
        COALESCE(people_count, 0) as people_count
    FROM `{$tableName}`
    WHERE room_type = ?
    AND accommodation_fee > 0
    AND check_in <= ?
    AND check_out > ?
    ORDER BY check_in ASC
");

$stmt->execute([$roomName, $lastDayOfMonth, $firstDayOfMonth]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>📋 Found " . count($bookings) . " Booking(s) Overlapping with Target Month</h2>";

if (count($bookings) == 0) {
    echo "<div class='warning'>⚠️ No bookings found for room '{$roomName}' in {$year}-{$month}</div>";
    echo "</div></body></html>";
    exit;
}

// Initialize totals
$totalBookedNights = 0;
$totalBookingCount = 0;
$totalRevenue = 0;

// Create calendar array for visualization
$calendar = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendar[$day] = ['booked' => false, 'bookings' => []];
}

// Process each booking
$bookingNumber = 0;
foreach ($bookings as $booking) {
    $bookingNumber++;

    $checkIn = new DateTime($booking['check_in']);
    $checkOut = new DateTime($booking['check_out']);
    $monthStart = new DateTime($firstDayOfMonth);
    $monthEnd = new DateTime($lastDayOfMonth);
    $monthEnd->setTime(23, 59, 59);

    // Determine the actual start and end dates within the month
    $effectiveStart = max($checkIn, $monthStart);
    $effectiveEnd = min($checkOut, clone $monthEnd)->modify('+1 day');

    // Calculate nights in this month
    $nightsInMonth = $effectiveStart->diff($effectiveEnd)->days;

    // Calculate proportional revenue
    $totalNights = intval($booking['night_count']);
    $proportionalRevenue = 0;
    if ($totalNights > 0 && $nightsInMonth > 0) {
        $proportionalRevenue = ($nightsInMonth / $totalNights) * floatval($booking['accommodation_fee']);
    }

    // Check if check-in is in this month
    $checkInInThisMonth = ($checkIn >= $monthStart && $checkIn <= $monthEnd);

    // Determine booking status
    $status = 'outside';
    if ($nightsInMonth > 0) {
        $status = ($nightsInMonth == $totalNights) ? 'active' : 'partial';
    }

    // Update totals
    if ($nightsInMonth > 0) {
        $totalBookedNights += $nightsInMonth;
        $totalRevenue += $proportionalRevenue;
    }
    if ($checkInInThisMonth) {
        $totalBookingCount++;
    }

    // Mark calendar days
    $currentDate = clone $effectiveStart;
    while ($currentDate < $effectiveEnd && $nightsInMonth > 0) {
        $day = intval($currentDate->format('j'));
        if ($day >= 1 && $day <= $daysInMonth) {
            $calendar[$day]['booked'] = true;
            $calendar[$day]['bookings'][] = $bookingNumber;
        }
        $currentDate->modify('+1 day');
    }

    // Display booking details
    echo "<div class='booking {$status}'>";
    echo "<h3>📌 Booking #{$bookingNumber} (ID: {$booking['id']})</h3>";

    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Check-In</td><td>{$booking['check_in']} " . $checkIn->format('(D)') . "</td></tr>";
    echo "<tr><td>Check-Out</td><td>{$booking['check_out']} " . $checkOut->format('(D)') . "</td></tr>";
    echo "<tr><td>Total Night Count</td><td>{$totalNights} nights</td></tr>";
    echo "<tr><td>Accommodation Fee</td><td>¥" . number_format($booking['accommodation_fee']) . "</td></tr>";
    echo "<tr><td>People Count</td><td>{$booking['people_count']}</td></tr>";
    if ($booking['booking_date']) {
        echo "<tr><td>Booking Date</td><td>{$booking['booking_date']}</td></tr>";
    }
    echo "</table>";

    echo "<div class='calculation'>";
    echo "<strong>🧮 Calculation for {$year}-{$month}:</strong><br><br>";
    echo "Month Period: {$firstDayOfMonth} to {$lastDayOfMonth}<br>";
    echo "Check-In Date: " . $checkIn->format('Y-m-d') . "<br>";
    echo "Check-Out Date: " . $checkOut->format('Y-m-d') . "<br>";
    echo "<br>";
    echo "Effective Start: " . $effectiveStart->format('Y-m-d') . " (max of check-in and month start)<br>";
    echo "Effective End: " . $effectiveEnd->format('Y-m-d') . " (min of check-out and month end+1)<br>";
    echo "<br>";
    echo "<strong>Nights in This Month: {$nightsInMonth} nights</strong><br>";

    if ($nightsInMonth > 0) {
        echo "Proportional Revenue: ({$nightsInMonth} / {$totalNights}) × ¥" . number_format($booking['accommodation_fee']) . " = ¥" . number_format($proportionalRevenue, 2) . "<br>";
    }

    echo "Check-In in This Month: " . ($checkInInThisMonth ? '✅ Yes (counts toward booking count)' : '❌ No') . "<br>";
    echo "</div>";

    // Show which specific days are counted
    if ($nightsInMonth > 0) {
        echo "<div><strong>📅 Days Counted in {$year}-{$month}:</strong><br>";
        $currentDate = clone $effectiveStart;
        $daysArray = [];
        while ($currentDate < $effectiveEnd) {
            $daysArray[] = $currentDate->format('Y-m-d (D)');
            $currentDate->modify('+1 day');
        }
        echo implode(' → ', $daysArray);
        echo "</div>";
    }

    echo "</div>";
}

// Display summary
echo "<h2>📊 Calculation Summary</h2>";
echo "<div class='summary'>";
echo "<span class='label'>Total Booked Nights:</span><span class='value total'>{$totalBookedNights} nights</span><br>";
echo "<span class='label'>Total Booking Count:</span><span class='value total'>{$totalBookingCount} bookings</span><br>";
echo "<span class='label'>Total Revenue (Proportional):</span><span class='value total'>¥" . number_format($totalRevenue, 2) . "</span><br>";
echo "<span class='label'>Occupancy Rate:</span><span class='value total'>" . number_format(($totalBookedNights / $daysInMonth) * 100, 2) . "%</span>";
echo "</div>";

// Display calendar visualization
echo "<h2>📅 Calendar View: {$year}-{$month}</h2>";
echo "<div class='dates'>";
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
    $dayOfWeek = date('D', strtotime($dateStr));

    $class = $calendar[$day]['booked'] ? 'booked' : 'available';
    $title = $calendar[$day]['booked'] ? 'Booking(s): #' . implode(', #', $calendar[$day]['bookings']) : 'Available';

    echo "<div class='date-box {$class}' title='{$title}'>";
    echo "<div>{$day}</div>";
    echo "<div style='font-size: 10px;'>{$dayOfWeek}</div>";
    if ($calendar[$day]['booked']) {
        echo "<div style='font-size: 9px;'>✓</div>";
    }
    echo "</div>";
}
echo "</div>";

echo "<h2>🔍 SQL Query Used</h2>";
echo "<div class='calculation'>";
echo "SELECT * FROM `{$tableName}`<br>";
echo "WHERE room_type = '{$roomName}'<br>";
echo "AND accommodation_fee > 0<br>";
echo "AND check_in <= '{$lastDayOfMonth}'<br>";
echo "AND check_out > '{$firstDayOfMonth}'<br>";
echo "ORDER BY check_in ASC";
echo "</div>";

echo "<div class='warning'>";
echo "<strong>💡 How the Calculation Works:</strong><br><br>";
echo "1. <strong>Overlapping Bookings Query:</strong> Finds all bookings where check_in ≤ last_day AND check_out > first_day<br>";
echo "2. <strong>Effective Dates:</strong> Uses max(check_in, month_start) and min(check_out, month_end+1)<br>";
echo "3. <strong>Night Count:</strong> Calculates effectiveEnd->diff(effectiveStart)->days<br>";
echo "4. <strong>Booking Count:</strong> Only counts bookings where check-in date falls within the target month<br>";
echo "5. <strong>Proportional Revenue:</strong> (nights_in_month / total_nights) × accommodation_fee<br>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>
