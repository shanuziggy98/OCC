<?php
/**
 * Save Daily Occupancy Records - Full Year VERSION
 * This script calculates and saves daily Full Year sales snapshots for all properties
 *
 * Method: Gets ALL bookings that OVERLAP with the year, then calculates occupancy
 * based on the full year (365 days) - matches Property Owner Dashboard
 *
 * Trigger URL: https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025
 */

// Security: Only allow access with auth key
$hasAuthKey = isset($_GET['auth_key']) && $_GET['auth_key'] === 'exseed_daily_occ_2025';

if (!$hasAuthKey) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Access denied. Valid auth key required.']));
}

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

// Get parameters
$targetDate = $_GET['date'] ?? date('Y-m-d'); // Default to today
$daysToSave = intval($_GET['days'] ?? 1); // Default to 1 day

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    // Get all active properties
    $stmt = $pdo->query("
        SELECT property_name, property_type, room_list, total_rooms
        FROM property_sheets
        WHERE is_active = TRUE
        ORDER BY property_name
    ");
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($properties)) {
        throw new Exception("No active properties found");
    }

    $totalSaved = 0;
    $totalUpdated = 0;
    $errors = [];

    // Generate dates to process
    $dates = [];
    $currentDate = new DateTime($targetDate);
    for ($i = 0; $i < $daysToSave; $i++) {
        $dates[] = $currentDate->format('Y-m-d');
        $currentDate->modify('-1 day'); // Go backwards to save historical data
    }

    // Prepare insert/update statement
    $saveStmt = $pdo->prepare("
        INSERT INTO daily_occupancy_records
        (property_name, record_date, occupied_rooms, total_rooms, occupancy_rate, booked_nights, booking_count, room_revenue)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            occupied_rooms = VALUES(occupied_rooms),
            total_rooms = VALUES(total_rooms),
            occupancy_rate = VALUES(occupancy_rate),
            booked_nights = VALUES(booked_nights),
            booking_count = VALUES(booking_count),
            room_revenue = VALUES(room_revenue),
            updated_at = CURRENT_TIMESTAMP
    ");

    // Check if record exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM daily_occupancy_records
        WHERE property_name = ? AND record_date = ?
    ");

    // Process each date
    foreach ($dates as $date) {
        $dateObj = new DateTime($date);
        $year = intval($dateObj->format('Y'));

        // YEAR RANGE (Jan 1 - Dec 31) - Get all bookings, but calculate occupancy YTD
        $firstDayOfYear = "{$year}-01-01";
        $lastDayOfYear = "{$year}-12-31";

        // Process each property
        foreach ($properties as $property) {
            $propertyName = $property['property_name'];

            // Calculate total rooms excluding staff rooms (ending with 'sf')
            $totalRooms = intval($property['total_rooms']);
            if ($property['property_type'] === 'hostel' && !empty($property['room_list'])) {
                $rooms = explode(',', $property['room_list']);
                $nonStaffRooms = array_filter($rooms, function($room) {
                    return !preg_match('/sf$/i', trim($room));
                });
                $totalRooms = count($nonStaffRooms);
            }

            $tableName = sanitizeTableName($propertyName);

            // Check if property table exists
            $checkTableStmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $checkTableStmt->execute([$tableName]);

            if ($checkTableStmt->rowCount() == 0) {
                $errors[] = "Table '{$tableName}' does not exist for property '{$propertyName}'";
                continue;
            }

            try {
                // *** CORRECTED: Get ALL bookings that OVERLAP with the year ***
                // This matches the Vertical View calculation method
                $stmt = $pdo->prepare("
                    SELECT
                        check_in,
                        check_out,
                        night_count,
                        accommodation_fee,
                        booking_date
                    FROM `{$tableName}`
                    WHERE accommodation_fee > 0
                    AND check_in <= ?
                    AND check_out > ?
                ");

                $stmt->execute([$lastDayOfYear, $firstDayOfYear]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate metrics from all bookings that overlap with the year
                $bookedNights = 0;
                $bookingCount = 0;
                $revenue = 0;

                $yearStart = new DateTime($firstDayOfYear);
                $yearEnd = new DateTime($lastDayOfYear);
                $yearEnd->setTime(23, 59, 59);

                foreach ($bookings as $booking) {
                    $checkIn = new DateTime($booking['check_in']);
                    $checkOut = new DateTime($booking['check_out']);

                    // Determine the actual start and end dates within the year
                    $effectiveStart = max($checkIn, $yearStart);
                    $effectiveEnd = min($checkOut, (clone $yearEnd)->modify('+1 day'));

                    // Calculate nights in this period
                    $nightsInPeriod = $effectiveStart->diff($effectiveEnd)->days;

                    if ($nightsInPeriod > 0) {
                        $bookedNights += $nightsInPeriod;

                        // Calculate proportional revenue
                        $totalNights = intval($booking['night_count']);
                        if ($totalNights > 0) {
                            $proportionalRevenue = ($nightsInPeriod / $totalNights) * floatval($booking['accommodation_fee']);
                            $revenue += $proportionalRevenue;
                        }

                        // Count booking if check-in is in this year
                        if ($checkIn >= $yearStart && $checkIn <= $yearEnd) {
                            $bookingCount++;
                        }
                    }
                }

                // Calculate occupancy rate for the full year (matches Property Owner Dashboard)
                $daysInYear = $yearStart->diff($yearEnd)->days + 1; // 365 or 366 days
                $availableRooms = $daysInYear * $totalRooms;
                $occupancyRate = $availableRooms > 0 ? ($bookedNights / $availableRooms) * 100 : 0;
                $occupiedRooms = $totalRooms > 0 ? round(($bookedNights / $daysInYear), 2) : 0;

                // Check if record exists
                $checkStmt->execute([$propertyName, $date]);
                $exists = $checkStmt->rowCount() > 0;

                // Save to database
                $saveStmt->execute([
                    $propertyName,
                    $date,
                    $occupiedRooms,
                    $totalRooms,
                    round($occupancyRate, 2),
                    $bookedNights,
                    $bookingCount,
                    $revenue
                ]);

                if ($exists) {
                    $totalUpdated++;
                } else {
                    $totalSaved++;
                }

            } catch (Exception $e) {
                $errors[] = "Error processing {$propertyName} on {$date}: " . $e->getMessage();
            }
        }

        // Calculate TOTAL across ALL properties for this date
        try {
            $totalStmt = $pdo->prepare("
                SELECT
                    SUM(room_revenue) as total_revenue,
                    SUM(booked_nights) as total_booked_nights,
                    SUM(booking_count) as total_booking_count,
                    SUM(occupied_rooms) as total_occupied_rooms,
                    SUM(total_rooms) as sum_total_rooms
                FROM daily_occupancy_records
                WHERE record_date = ?
                AND property_name != 'ALL_PROPERTIES'
            ");
            $totalStmt->execute([$date]);
            $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);

            $totalRevenue = floatval($totalRow['total_revenue'] ?? 0);
            $totalBookedNights = floatval($totalRow['total_booked_nights'] ?? 0);
            $totalBookingCount = intval($totalRow['total_booking_count'] ?? 0);
            $totalOccupiedRooms = floatval($totalRow['total_occupied_rooms'] ?? 0);
            $sumTotalRooms = intval($totalRow['sum_total_rooms'] ?? 0);

            // Calculate overall occupancy rate (Full Year - matches Property Owner Dashboard)
            $daysInYear = (new DateTime($firstDayOfYear))->diff(new DateTime($lastDayOfYear))->days + 1;
            $totalAvailableRooms = $daysInYear * $sumTotalRooms;
            $totalOccupancyRate = $totalAvailableRooms > 0 ? ($totalBookedNights / $totalAvailableRooms) * 100 : 0;

            // Check if TOTAL record exists
            $checkStmt->execute(['ALL_PROPERTIES', $date]);
            $exists = $checkStmt->rowCount() > 0;

            // Save TOTAL record
            $saveStmt->execute([
                'ALL_PROPERTIES',  // Special property name for total
                $date,
                $totalOccupiedRooms,
                $sumTotalRooms,
                round($totalOccupancyRate, 2),
                $totalBookedNights,
                $totalBookingCount,
                $totalRevenue  // This is the TOTAL SALES NUMBER
            ]);

            if ($exists) {
                $totalUpdated++;
            } else {
                $totalSaved++;
            }

        } catch (Exception $e) {
            $errors[] = "Error calculating total for {$date}: " . $e->getMessage();
        }
    }

    // Return success response
    $response = [
        'success' => true,
        'message' => 'Daily Full Year sales snapshots saved successfully',
        'calculation_method' => 'Full Year Method - Occupancy based on entire year (365 days)',
        'dates_processed' => $dates,
        'properties_count' => count($properties),
        'records_saved' => $totalSaved,
        'records_updated' => $totalUpdated,
        'total_records' => $totalSaved + $totalUpdated,
        'note' => 'Uses Full Year calculation matching Property Owner Dashboard. Gets ALL bookings that overlap with the year and calculates occupancy rate based on full 365 days.'
    ];

    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }

    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

function sanitizeTableName($propertyName) {
    $tableName = strtolower($propertyName);
    $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
    $tableName = preg_replace('/_+/', '_', $tableName);
    $tableName = trim($tableName, '_');
    return $tableName;
}
?>
