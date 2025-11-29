<?php
/**
 * Property Owner API
 * Provides detailed metrics, year-over-year comparisons, and charts for property owners
 */

session_start();

header("Access-Control-Allow-Origin: https://exseed.main.jp");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

class PropertyOwnerAPI {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . $GLOBALS['host'] . ";dbname=" . $GLOBALS['db_name'],
                $GLOBALS['username'],
                $GLOBALS['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("set names utf8mb4");
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function checkAuth() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            $this->sendResponse(['error' => 'Unauthorized'], 401);
        }
    }

    private function sanitizeTableName($propertyName) {
        $tableName = strtolower($propertyName);
        $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
        $tableName = preg_replace('/_+/', '_', $tableName);
        $tableName = trim($tableName, '_');
        return $tableName;
    }

    private function getPropertyType($propertyName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT property_type, room_list
                FROM property_sheets
                WHERE property_name = ?
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get monthly metrics for a specific year
     */
    public function getYearlyMetrics($propertyName, $year) {
        $tableName = $this->sanitizeTableName($propertyName);
        $monthlyData = [];

        // Check if table exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        if ($stmt->rowCount() == 0) {
            return null;
        }

        // Get property info
        $propertyInfo = $this->getPropertyType($propertyName);
        $isHostel = $propertyInfo && $propertyInfo['property_type'] === 'hostel';

        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = $this->getMonthMetrics($propertyName, $year, $month, $isHostel);
        }

        return [
            'property_name' => $propertyName,
            'year' => $year,
            'property_type' => $propertyInfo['property_type'] ?? 'guesthouse',
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Get metrics for a specific month
     */
    private function getMonthMetrics($propertyName, $year, $month, $isHostel = false) {
        $tableName = $this->sanitizeTableName($propertyName);
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $firstDayOfMonth = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    check_in,
                    check_out,
                    night_count,
                    accommodation_fee,
                    booking_date,
                    room_type,
                    COALESCE(people_count, 0) as people_count
                FROM `{$tableName}`
                WHERE accommodation_fee > 0
                AND check_in <= ?
                AND check_out > ?
            ");
            $stmt->execute([$lastDayOfMonth, $firstDayOfMonth]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $booked_nights = 0;
            $booking_count = 0;
            $room_revenue = 0;
            $total_people = 0;

            foreach ($bookings as $booking) {
                $checkIn = new DateTime($booking['check_in']);
                $checkOut = new DateTime($booking['check_out']);
                $monthStart = new DateTime($firstDayOfMonth);
                $monthEnd = new DateTime($lastDayOfMonth);
                $monthEnd->setTime(23, 59, 59);

                $effectiveStart = max($checkIn, $monthStart);
                $effectiveEnd = min($checkOut, $monthEnd->modify('+1 day'));

                $nightsInMonth = $effectiveStart->diff($effectiveEnd)->days;

                if ($nightsInMonth > 0) {
                    $booked_nights += $nightsInMonth;

                    $totalNights = intval($booking['night_count']);
                    if ($totalNights > 0) {
                        $proportionalRevenue = ($nightsInMonth / $totalNights) * floatval($booking['accommodation_fee']);
                        $room_revenue += $proportionalRevenue;
                    }

                    if ($checkIn >= $monthStart && $checkIn <= $monthEnd) {
                        $booking_count++;
                        $total_people += intval($booking['people_count']);
                    }
                }
            }

            // Get room count from property_sheets
            $defaultRooms = $this->getDefaultRoomCount($propertyName);
            $available_rooms = $daysInMonth * $defaultRooms;

            $occ_rate = $available_rooms > 0 ? ($booked_nights / $available_rooms) * 100 : 0;
            $adr = $booked_nights > 0 ? $room_revenue / $booked_nights : 0;

            return [
                'month' => $month,
                'booked_nights' => $booked_nights,
                'booking_count' => $booking_count,
                'room_revenue' => round($room_revenue, 2),
                'occ_rate' => round($occ_rate, 2),
                'adr' => round($adr, 2),
                'total_people' => $total_people,
                'available_rooms' => $available_rooms
            ];

        } catch (Exception $e) {
            error_log("Error getting month metrics: " . $e->getMessage());
            return [
                'month' => $month,
                'booked_nights' => 0,
                'booking_count' => 0,
                'room_revenue' => 0,
                'occ_rate' => 0,
                'adr' => 0,
                'total_people' => 0,
                'available_rooms' => 0
            ];
        }
    }

    private function getDefaultRoomCount($propertyName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT property_type, room_list, total_rooms
                FROM property_sheets
                WHERE property_name = ?
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // If hostel with room_list, exclude staff rooms (ending with 'sf') from count
                if ($result['property_type'] === 'hostel' && !empty($result['room_list'])) {
                    $rooms = explode(',', $result['room_list']);
                    $nonStaffRooms = array_filter($rooms, function($room) {
                        $trimmedRoom = trim($room);
                        return !preg_match('/sf$/i', $trimmedRoom);
                    });
                    return count($nonStaffRooms);
                }
                return intval($result['total_rooms']);
            }
            return 1;
        } catch (Exception $e) {
            return 1;
        }
    }

    /**
     * Compare two years side by side
     */
    public function compareYears($propertyName, $year1, $year2) {
        $data1 = $this->getYearlyMetrics($propertyName, $year1);
        $data2 = $this->getYearlyMetrics($propertyName, $year2);

        if (!$data1 || !$data2) {
            return null;
        }

        // Calculate year totals
        $year1Totals = $this->calculateYearTotals($data1['monthly_data']);
        $year2Totals = $this->calculateYearTotals($data2['monthly_data']);

        // Calculate differences
        $differences = [
            'revenue_diff' => $year2Totals['total_revenue'] - $year1Totals['total_revenue'],
            'revenue_diff_percent' => $year1Totals['total_revenue'] > 0
                ? (($year2Totals['total_revenue'] - $year1Totals['total_revenue']) / $year1Totals['total_revenue']) * 100
                : 0,
            'occ_rate_diff' => $year2Totals['avg_occ_rate'] - $year1Totals['avg_occ_rate'],
            'booking_count_diff' => $year2Totals['total_bookings'] - $year1Totals['total_bookings'],
            'adr_diff' => $year2Totals['avg_adr'] - $year1Totals['avg_adr']
        ];

        return [
            'property_name' => $propertyName,
            'year1' => $year1,
            'year2' => $year2,
            'year1_data' => $data1,
            'year2_data' => $data2,
            'year1_totals' => $year1Totals,
            'year2_totals' => $year2Totals,
            'differences' => $differences
        ];
    }

    private function calculateYearTotals($monthlyData) {
        $totalRevenue = 0;
        $totalBookings = 0;
        $totalBookedNights = 0;
        $totalAvailableRooms = 0;
        $totalPeople = 0;
        $monthsWithData = 0;
        $totalADR = 0;

        foreach ($monthlyData as $month => $data) {
            if ($data['room_revenue'] > 0 || $data['booking_count'] > 0) {
                $monthsWithData++;
            }
            $totalRevenue += $data['room_revenue'];
            $totalBookings += $data['booking_count'];
            $totalBookedNights += $data['booked_nights'];
            $totalAvailableRooms += $data['available_rooms'];
            $totalPeople += $data['total_people'];
            if ($data['adr'] > 0) {
                $totalADR += $data['adr'];
            }
        }

        $avgOccRate = $totalAvailableRooms > 0
            ? ($totalBookedNights / $totalAvailableRooms) * 100
            : 0;

        $avgADR = $monthsWithData > 0 ? $totalADR / $monthsWithData : 0;

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_bookings' => $totalBookings,
            'total_booked_nights' => $totalBookedNights,
            'avg_occ_rate' => round($avgOccRate, 2),
            'avg_adr' => round($avgADR, 2),
            'total_people' => $totalPeople
        ];
    }

    /**
     * Get property summary with available years
     */
    public function getPropertySummary($propertyName) {
        $tableName = $this->sanitizeTableName($propertyName);

        // Check if table exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        if ($stmt->rowCount() == 0) {
            return null;
        }

        // Get available years
        $stmt = $this->pdo->query("
            SELECT DISTINCT YEAR(check_in) as year
            FROM `{$tableName}`
            WHERE check_in IS NOT NULL
            ORDER BY year DESC
        ");
        $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get property info
        $propertyInfo = $this->getPropertyType($propertyName);

        // Get last import time and 180-day limit status
        $stmt = $this->pdo->prepare("
            SELECT last_imported, has_180_day_limit
            FROM property_sheets
            WHERE property_name = ?
        ");
        $stmt->execute([$propertyName]);
        $propertyData = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'property_name' => $propertyName,
            'property_type' => $propertyInfo['property_type'] ?? 'guesthouse',
            'total_rooms' => $this->getDefaultRoomCount($propertyName),
            'available_years' => $availableYears,
            'last_imported' => $propertyData['last_imported'] ?? null,
            'has_180_day_limit' => $propertyData['has_180_day_limit'] ?? false
        ];
    }

    public function getUserProperties($username) {
        try {
            // First, get the owner_id for this username
            $stmt = $this->pdo->prepare("
                SELECT owner_id
                FROM property_users
                WHERE username = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $ownerId = $stmt->fetchColumn();

            if (!$ownerId) {
                return ['properties' => []];
            }

            // Now get all properties for this owner_id
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT property_name
                FROM property_users
                WHERE owner_id = ? AND is_active = 1
                ORDER BY property_name
            ");
            $stmt->execute([$ownerId]);
            $properties = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return [
                'properties' => $properties,
                'owner_id' => $ownerId
            ];
        } catch (Exception $e) {
            return [
                'properties' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    public function route() {
        $this->checkAuth();

        $action = $_GET['action'] ?? 'summary';

        try {
            switch ($action) {
                case 'user_properties':
                    $username = $_GET['username'] ?? $_SESSION['username'] ?? '';
                    if (empty($username)) {
                        $this->sendResponse(['error' => 'Username required'], 400);
                        return;
                    }
                    $result = $this->getUserProperties($username);
                    $this->sendResponse($result);
                    break;

                case 'summary':
                    $propertyName = $_GET['property'] ?? $_SESSION['property_name'] ?? '';
                    if (empty($propertyName)) {
                        $this->sendResponse(['error' => 'Property name required'], 400);
                        return;
                    }
                    $result = $this->getPropertySummary($propertyName);
                    if ($result === null) {
                        $this->sendResponse(['error' => 'Property not found or no data'], 404);
                        return;
                    }
                    $this->sendResponse($result);
                    break;

                case 'yearly':
                    $propertyName = $_GET['property'] ?? $_SESSION['property_name'] ?? '';
                    if (empty($propertyName)) {
                        $this->sendResponse(['error' => 'Property name required'], 400);
                        return;
                    }
                    $year = intval($_GET['year'] ?? date('Y'));
                    $result = $this->getYearlyMetrics($propertyName, $year);
                    if ($result === null) {
                        $this->sendResponse(['error' => 'Property not found or no data'], 404);
                        return;
                    }
                    $this->sendResponse($result);
                    break;

                case 'compare':
                    $propertyName = $_GET['property'] ?? $_SESSION['property_name'] ?? '';
                    if (empty($propertyName)) {
                        $this->sendResponse(['error' => 'Property name required'], 400);
                        return;
                    }
                    $year1 = intval($_GET['year1'] ?? date('Y') - 1);
                    $year2 = intval($_GET['year2'] ?? date('Y'));
                    $result = $this->compareYears($propertyName, $year1, $year2);
                    if ($result === null) {
                        $this->sendResponse(['error' => 'Property not found or no data'], 404);
                        return;
                    }
                    $this->sendResponse($result);
                    break;

                default:
                    $this->sendResponse(['error' => 'Unknown action'], 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Initialize and run the API
try {
    $api = new PropertyOwnerAPI();
    $api->route();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
