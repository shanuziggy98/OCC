<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Lolipop MySQL configuration
        $this->host = "mysql327.phy.lolipop.lan";
        $this->db_name = "LAA0963548-occ";
        $this->username = "LAA0963548";
        $this->password = "EXseed55";
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// Google Sheets integration class
class GoogleSheetsImporter {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function importFromSheet($sheetUrl, $propertyName) {
        try {
            // Extract sheet ID from URL
            $sheetId = $this->extractSheetId($sheetUrl);
            if (!$sheetId) {
                throw new Exception("Invalid Google Sheets URL");
            }

            // Create CSV export URL
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv";

            // Fetch CSV data
            $csvData = $this->fetchCSVData($csvUrl);
            if (!$csvData) {
                throw new Exception("Failed to fetch data from Google Sheets");
            }

            // Parse and import data
            $importedCount = $this->parseAndImportData($csvData, $propertyName);

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'message' => "Successfully imported {$importedCount} records for {$propertyName}"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function extractSheetId($url) {
        // Extract sheet ID from various Google Sheets URL formats
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function fetchCSVData($csvUrl) {
        $context = stream_context_create([
            "http" => [
                "timeout" => 30
            ]
        ]);

        return file_get_contents($csvUrl, false, $context);
    }

    private function parseAndImportData($csvData, $propertyName) {
        $lines = explode("\n", $csvData);
        $header = str_getcsv(array_shift($lines));

        // Map expected columns
        $columnMap = [
            'check_in' => $this->findColumnIndex($header, ['check in', 'checkin', 'check-in']),
            'check_out' => $this->findColumnIndex($header, ['check out', 'checkout', 'check-out']),
            'accommodation_fee' => $this->findColumnIndex($header, ['accommodation fee', 'fee', 'price', 'cost']),
            'night_count' => $this->findColumnIndex($header, ['night count', 'nights', 'night']),
            'booking_date' => $this->findColumnIndex($header, ['booking date', 'booking', 'reserved']),
            'lead_time' => $this->findColumnIndex($header, ['lead time', 'leadtime', 'lead-time'])
        ];

        $importedCount = 0;
        $conn = $this->db->getConnection();

        // Prepare insert statement
        $stmt = $conn->prepare("
            INSERT INTO bookings (
                property_name, check_in, check_out, accommodation_fee,
                night_count, booking_date, lead_time, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line);
            if (count($data) < max($columnMap)) continue;

            try {
                $checkIn = $this->parseDate($data[$columnMap['check_in']] ?? '');
                $checkOut = $this->parseDate($data[$columnMap['check_out']] ?? '');
                $accommodationFee = floatval($data[$columnMap['accommodation_fee']] ?? 0);
                $nightCount = intval($data[$columnMap['night_count']] ?? 0);
                $bookingDate = $this->parseDate($data[$columnMap['booking_date']] ?? '');
                $leadTime = intval($data[$columnMap['lead_time']] ?? 0);

                if ($checkIn && $checkOut && $accommodationFee > 0) {
                    $stmt->execute([
                        $propertyName,
                        $checkIn,
                        $checkOut,
                        $accommodationFee,
                        $nightCount,
                        $bookingDate,
                        $leadTime
                    ]);
                    $importedCount++;
                }
            } catch (Exception $e) {
                error_log("Error importing row: " . $e->getMessage());
                continue;
            }
        }

        return $importedCount;
    }

    private function findColumnIndex($header, $possibleNames) {
        foreach ($possibleNames as $name) {
            $index = array_search(strtolower($name), array_map('strtolower', $header));
            if ($index !== false) {
                return $index;
            }
        }
        return 0; // Default to first column if not found
    }

    private function parseDate($dateString) {
        if (empty($dateString)) return null;

        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        if (!$date) {
            $date = DateTime::createFromFormat('m/d/Y', $dateString);
        }
        if (!$date) {
            $date = DateTime::createFromFormat('d/m/Y', $dateString);
        }

        return $date ? $date->format('Y-m-d') : null;
    }
}

// Occupancy Calculator class
class OccupancyCalculator {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function calculateOccupancyRate($propertyName, $startDate, $endDate, $totalRooms) {
        $conn = $this->db->getConnection();

        // Get all bookings for the property in the date range
        $stmt = $conn->prepare("
            SELECT check_in, check_out, night_count
            FROM bookings
            WHERE property_name = ?
            AND check_in <= ?
            AND check_out >= ?
            ORDER BY check_in
        ");

        $stmt->execute([$propertyName, $endDate, $startDate]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate occupied room nights
        $occupiedRoomNights = 0;
        $dateRange = [];

        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dateRange[$dateStr] = 0;
        }

        foreach ($bookings as $booking) {
            $checkIn = new DateTime($booking['check_in']);
            $checkOut = new DateTime($booking['check_out']);

            $current = clone $checkIn;
            while ($current < $checkOut) {
                $currentDateStr = $current->format('Y-m-d');
                if (isset($dateRange[$currentDateStr])) {
                    $dateRange[$currentDateStr]++;
                }
                $current->add(new DateInterval('P1D'));
            }
        }

        $totalOccupiedRoomNights = array_sum($dateRange);
        $totalAvailableRoomNights = count($dateRange) * $totalRooms;
        $occupancyRate = $totalAvailableRoomNights > 0 ?
            ($totalOccupiedRoomNights / $totalAvailableRoomNights) * 100 : 0;

        return [
            'property_name' => $propertyName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_rooms' => $totalRooms,
            'occupied_room_nights' => $totalOccupiedRoomNights,
            'available_room_nights' => $totalAvailableRoomNights,
            'occupancy_rate' => round($occupancyRate, 2),
            'daily_occupancy' => $dateRange
        ];
    }

    public function getProperties() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT DISTINCT property_name FROM bookings ORDER BY property_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// API Router
class APIRouter {
    private $db;
    private $importer;
    private $calculator;

    public function __construct() {
        $this->db = new Database();
        $this->importer = new GoogleSheetsImporter($this->db);
        $this->calculator = new OccupancyCalculator($this->db);
    }

    public function route() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['action'] ?? '';

        switch ($method) {
            case 'POST':
                $this->handlePost($path);
                break;
            case 'GET':
                $this->handleGet($path);
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }

    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'import':
                $sheetUrl = $input['sheet_url'] ?? '';
                $propertyName = $input['property_name'] ?? '';

                if (empty($sheetUrl) || empty($propertyName)) {
                    $this->sendResponse(['error' => 'Missing required parameters'], 400);
                    return;
                }

                $result = $this->importer->importFromSheet($sheetUrl, $propertyName);
                $this->sendResponse($result);
                break;

            case 'calculate':
                $propertyName = $input['property_name'] ?? '';
                $startDate = $input['start_date'] ?? '';
                $endDate = $input['end_date'] ?? '';
                $totalRooms = intval($input['total_rooms'] ?? 0);

                if (empty($propertyName) || empty($startDate) || empty($endDate) || $totalRooms <= 0) {
                    $this->sendResponse(['error' => 'Missing required parameters'], 400);
                    return;
                }

                $result = $this->calculator->calculateOccupancyRate($propertyName, $startDate, $endDate, $totalRooms);
                $this->sendResponse($result);
                break;

            default:
                $this->sendResponse(['error' => 'Unknown action'], 404);
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'properties':
                $properties = $this->calculator->getProperties();
                $this->sendResponse(['properties' => $properties]);
                break;

            case 'health':
                $this->sendResponse(['status' => 'OK', 'timestamp' => date('Y-m-d H:i:s')]);
                break;

            default:
                $this->sendResponse(['error' => 'Unknown action'], 404);
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// Initialize and run the API
try {
    $api = new APIRouter();
    $api->route();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>