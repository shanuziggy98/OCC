<?php
/**
 * Automated Import Script for Cron Job
 * This script runs automatically to import data from Google Sheets to database
 *
 * Usage: Set up as a cron job to run every hour (or your preferred interval)
 * Example cron: 0 * * * * /usr/bin/php /path/to/auto_import_cron.php
 */

// Allow direct access from browser (no auth key required)
// If you want to add security back, uncomment the lines below
$isCLI = php_sapi_name() === 'cli';

/*
// Uncomment these lines to require auth key:
$hasAuthKey = isset($_GET['auth_key']) && $_GET['auth_key'] === 'exseed_auto_import_2025';
if (!$isCLI && !$hasAuthKey) {
    http_response_code(403);
    die("Access denied. This script can only be run via cron job or with valid auth key.");
}
*/

// Log file path
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/auto_import_' . date('Y-m') . '.log';

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

/**
 * Write to log file
 */
function writeLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // Also output to console if running in CLI
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

writeLog("========== AUTO IMPORT STARTED ==========", $logFile);

class AutoImporter {
    private $pdo;
    private $logFile;
    private $hostelProperties = ['iwatoyama', 'Goettingen', 'littlehouse'];

    public function __construct($pdo, $logFile) {
        $this->pdo = $pdo;
        $this->logFile = $logFile;
    }

    private function log($message) {
        writeLog($message, $this->logFile);
    }

    public function sanitizeTableName($propertyName) {
        $tableName = strtolower($propertyName);
        $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
        $tableName = preg_replace('/_+/', '_', $tableName);
        $tableName = trim($tableName, '_');
        return $tableName;
    }

    public function isHostelProperty($propertyName) {
        // Try to get from database first
        try {
            $stmt = $this->pdo->prepare("
                SELECT property_type
                FROM property_sheets
                WHERE property_name = ?
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['property_type'] === 'hostel';
            }
        } catch (Exception $e) {
            // Fallback to hardcoded list if database check fails
        }

        // Fallback to hardcoded list
        return in_array($propertyName, $this->hostelProperties);
    }

    public function importAllProperties() {
        try {
            // Get all active properties
            $stmt = $this->pdo->query("SELECT property_name, google_sheet_url FROM property_sheets WHERE is_active = TRUE ORDER BY display_order, property_name");
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($properties)) {
                $this->log("ERROR: No active properties found in property_sheets table");
                return false;
            }

            $this->log("Found " . count($properties) . " active properties to import");

            $successCount = 0;
            $errorCount = 0;
            $totalImported = 0;

            foreach ($properties as $property) {
                $propertyName = $property['property_name'];
                $googleSheetUrl = $property['google_sheet_url'];

                $this->log("Processing: {$propertyName}");

                $result = $this->importProperty($propertyName, $googleSheetUrl);

                if ($result['success']) {
                    $successCount++;
                    $totalImported += $result['imported_count'];
                    $this->log("✓ SUCCESS: {$propertyName} - Imported {$result['imported_count']} records");
                } else {
                    $errorCount++;
                    $this->log("✗ ERROR: {$propertyName} - {$result['error']}");
                }
            }

            $this->log("========== IMPORT SUMMARY ==========");
            $this->log("Total Properties: " . count($properties));
            $this->log("Successful: {$successCount}");
            $this->log("Failed: {$errorCount}");
            $this->log("Total Records Imported: {$totalImported}");
            $this->log("========================================");

            return true;

        } catch (Exception $e) {
            $this->log("FATAL ERROR: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if table exists, create if not
     */
    private function ensureTableExists($tableName) {
        try {
            // Check if table exists
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);

            if ($stmt->rowCount() > 0) {
                $this->log("Table `{$tableName}` already exists");
                return true;
            }

            // Table doesn't exist, create it
            $this->log("Creating new table: `{$tableName}`");

            $createTableSQL = "
                CREATE TABLE `{$tableName}` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    check_in DATE NOT NULL,
                    check_out DATE NOT NULL,
                    accommodation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    night_count INT NOT NULL DEFAULT 0,
                    booking_date DATE,
                    lead_time INT DEFAULT 0,
                    room_type VARCHAR(100),
                    people_count INT DEFAULT 0 COMMENT 'Number of people from column K',
                    guest_name VARCHAR(255),
                    guest_email VARCHAR(255),
                    special_requests TEXT,
                    raw_data JSON,
                    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_check_in (check_in),
                    INDEX idx_check_out (check_out),
                    INDEX idx_booking_date (booking_date),
                    INDEX idx_date_range (check_in, check_out),
                    INDEX idx_room_type (room_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Auto-created table for property bookings'
            ";

            $this->pdo->exec($createTableSQL);
            $this->log("✓ Successfully created table: `{$tableName}`");

            return true;

        } catch (Exception $e) {
            $this->log("ERROR creating table `{$tableName}`: " . $e->getMessage());
            throw $e;
        }
    }

    private function importProperty($propertyName, $googleSheetUrl) {
        $tableName = $this->sanitizeTableName($propertyName);
        $isHostel = $this->isHostelProperty($propertyName);

        try {
            // Ensure table exists (create if needed)
            $this->ensureTableExists($tableName);

            // Fetch CSV data first (before deleting)
            $csvData = $this->fetchCSVData($googleSheetUrl);
            if (!$csvData) {
                throw new Exception("Failed to fetch data from Google Sheets");
            }

            // Begin transaction to ensure atomicity
            $this->pdo->beginTransaction();

            try {
                // Clear existing data
                $this->pdo->exec("DELETE FROM `{$tableName}`");
                $this->log("Cleared existing data from {$tableName}");

                // Parse and import data
                $importedCount = $this->parseByPosition($csvData, $tableName, $isHostel);

                // Update timestamp
                $updateStmt = $this->pdo->prepare("UPDATE property_sheets SET last_imported = NOW() WHERE property_name = ?");
                $updateStmt->execute([$propertyName]);

                // Commit transaction
                $this->pdo->commit();

                return [
                    'success' => true,
                    'imported_count' => $importedCount
                ];

            } catch (Exception $e) {
                // Rollback on error
                $this->pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function fetchCSVData($csvUrl) {
        // First try with cURL (better at handling redirects)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $csvUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

            $csvData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($csvData && $httpCode == 200) {
                return $csvData;
            }

            $this->log("cURL failed (HTTP {$httpCode}): {$error}");
        }

        // Fallback to file_get_contents
        $context = stream_context_create([
            "http" => [
                "timeout" => 30,
                "user_agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
                "follow_location" => true,
                "max_redirects" => 5
            ],
            "ssl" => [
                "verify_peer" => true,
                "verify_peer_name" => true
            ]
        ]);

        return @file_get_contents($csvUrl, false, $context);
    }

    private function parseByPosition($csvData, $tableName, $isHostel) {
        $lines = explode("\n", $csvData);
        array_shift($lines); // Skip header

        $importedCount = 0;

        $stmt = $this->pdo->prepare("
            INSERT INTO `{$tableName}` (
                check_in, check_out, accommodation_fee,
                night_count, booking_date, lead_time, room_type, people_count, raw_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($lines as $lineIndex => $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line);
            $minColumns = $isHostel ? 9 : 8;
            if (count($data) < $minColumns) continue;

            try {
                if ($isHostel) {
                    $checkIn = $this->parseDate($data[3] ?? '');
                    $checkOut = $this->parseDate($data[4] ?? '');
                    $accommodationFee = $this->parseNumber($data[5] ?? 0);
                    $nightCount = intval($data[6] ?? 0);
                    $bookingDate = $this->parseBookingDate($data[7] ?? '', $checkIn);
                    $leadTime = intval($data[8] ?? 0);
                    $roomNumber = trim($data[9] ?? '');
                    $peopleCount = intval($data[10] ?? 0); // Column K
                } else {
                    $checkIn = $this->parseDate($data[3] ?? '');
                    $checkOut = $this->parseDate($data[4] ?? '');
                    $accommodationFee = $this->parseNumber($data[5] ?? 0);
                    $nightCount = intval($data[6] ?? 0);
                    $bookingDate = $this->parseBookingDate($data[7] ?? '', $checkIn);
                    $leadTime = intval($data[8] ?? 0);
                    $roomNumber = '';
                    $peopleCount = intval($data[10] ?? 0); // Column K
                }

                $rawData = json_encode([
                    'original_row' => $data,
                    'line_number' => $lineIndex + 2,
                    'is_hostel' => $isHostel
                ], JSON_UNESCAPED_UNICODE);

                if ($checkIn && $checkOut) {
                    $stmt->execute([
                        $checkIn,
                        $checkOut,
                        $accommodationFee,
                        $nightCount,
                        $bookingDate,
                        $leadTime,
                        $roomNumber,
                        $peopleCount,
                        $rawData
                    ]);
                    $importedCount++;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $importedCount;
    }

    private function parseDate($dateString) {
        if (empty($dateString)) return null;

        $dateString = trim($dateString);
        if ($dateString === '0' || $dateString === '') return null;

        $formats = ['Y/m/d', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d H:i:s'];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseBookingDate($dateString, $checkInDate) {
        if (empty($dateString)) return null;

        $dateString = trim($dateString);
        if ($dateString === '0' || $dateString === '') return null;

        $standardFormats = ['Y/m/d', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d H:i:s'];
        foreach ($standardFormats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $dateString, $matches)) {
            $month = intval($matches[1]);
            $day = intval($matches[2]);

            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                if ($checkInDate) {
                    $checkInYear = date('Y', strtotime($checkInDate));
                    $bookingYear = $checkInYear;

                    $checkInMonth = date('n', strtotime($checkInDate));
                    if ($month > $checkInMonth) {
                        $bookingYear = $checkInYear - 1;
                    }

                    try {
                        $date = DateTime::createFromFormat('Y-n-j', "{$bookingYear}-{$month}-{$day}");
                        if ($date) {
                            return $date->format('Y-m-d');
                        }
                    } catch (Exception $e) {
                        return null;
                    }
                }
            }
        }

        return null;
    }

    private function parseNumber($value) {
        if (empty($value)) return 0;
        $value = str_replace(',', '', $value);
        $value = preg_replace('/[^0-9.-]/', '', $value);
        return floatval($value);
    }
}

/**
 * Import Kaguya Monthly Commission Data from Google Sheets
 */
function importKaguyaCommission($pdo, $logFile) {
    // Google Sheets configuration for Kaguya commission
    $spreadsheetId = '1B0at-W68i5AwUOmrrc3l0RjMNlQdysOR';
    $gid = '1404137725';
    $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";

    writeLog("Fetching Kaguya commission data from Google Sheets...", $logFile);

    try {
        // Fetch CSV data
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: PHP Script',
                'follow_location' => true
            ]
        ]);

        $csvData = file_get_contents($csvUrl, false, $context);

        if ($csvData === false) {
            writeLog("ERROR: Failed to fetch Kaguya commission data", $logFile);
            return ['success' => false, 'error' => 'Failed to fetch data'];
        }

        // Parse CSV
        $lines = explode("\n", $csvData);
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $rows[] = str_getcsv($line);
        }

        writeLog("Fetched " . count($rows) . " rows from Kaguya commission sheet", $logFile);

        // Find header row
        $headerRowIndex = 0;
        for ($i = 0; $i < count($rows); $i++) {
            $firstCell = strtolower(trim($rows[$i][0] ?? ''));
            if (strpos($firstCell, 'month') !== false || strpos($firstCell, 'total') !== false) {
                $headerRowIndex = $i;
                break;
            }
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        // Prepare statement
        $stmt = $pdo->prepare("
            INSERT INTO kaguya_monthly_commission
            (year, month, total_sales, owner_payment, exseed_commission, commission_percentage, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_sales = VALUES(total_sales),
                owner_payment = VALUES(owner_payment),
                exseed_commission = VALUES(exseed_commission),
                commission_percentage = VALUES(commission_percentage),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ");

        // Process data rows (skip header)
        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty($row[0]) || trim($row[0]) === '') {
                continue;
            }

            $monthStr = $row[0] ?? '';

            // Parse month format: "25/01" -> year: 2025, month: 1
            if (preg_match('/^(\d{2})\/(\d{1,2})$/', trim($monthStr), $matches)) {
                $year = 2000 + intval($matches[1]);
                $month = intval($matches[2]);
            } else {
                writeLog("Skipping row $i: Invalid month format '$monthStr'", $logFile);
                $skipped++;
                continue;
            }

            // Parse values
            $totalSales = parseNumericValue($row[1] ?? '0');
            $ownerPayment = parseNumericValue($row[2] ?? '0');
            $exseedCommission = parseNumericValue($row[3] ?? '0');
            $commissionPercentage = parsePercentageValue($row[4] ?? '0');

            // Create notes
            $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
            $notes = $monthNames[$month] . ' ' . $year;

            // Skip if no sales data
            if ($totalSales <= 0) {
                $skipped++;
                continue;
            }

            // Check if it was an insert or update
            $checkStmt = $pdo->prepare("SELECT id FROM kaguya_monthly_commission WHERE year = ? AND month = ?");
            $checkStmt->execute([$year, $month]);
            $existingRecord = $checkStmt->fetch();

            // Execute insert/update
            $stmt->execute([
                $year,
                $month,
                $totalSales,
                $ownerPayment,
                $exseedCommission,
                $commissionPercentage,
                $notes
            ]);

            if ($existingRecord) {
                $updated++;
                writeLog("Updated: {$notes} - Sales=¥" . number_format($totalSales) .
                        ", Commission=¥" . number_format($exseedCommission) .
                        " ({$commissionPercentage}%)", $logFile);
            } else {
                $inserted++;
                writeLog("Inserted: {$notes} - Sales=¥" . number_format($totalSales) .
                        ", Commission=¥" . number_format($exseedCommission) .
                        " ({$commissionPercentage}%)", $logFile);
            }
        }

        writeLog("Kaguya Commission Summary: Inserted={$inserted}, Updated={$updated}, Skipped={$skipped}", $logFile);

        return [
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped
        ];

    } catch (Exception $e) {
        writeLog("ERROR importing Kaguya commission: " . $e->getMessage(), $logFile);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Parse numeric value (remove commas, handle empty values)
 */
function parseNumericValue($value) {
    $value = trim($value);
    if ($value === '' || $value === '-') {
        return 0;
    }
    return floatval(str_replace(',', '', $value));
}

/**
 * Parse percentage value
 */
function parsePercentageValue($value) {
    $value = trim($value);
    if ($value === '' || $value === '-' || $value === '#DIV/0!') {
        return 0;
    }
    return floatval(str_replace('%', '', $value));
}

// Main execution
try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    writeLog("Database connection established", $logFile);

    $importer = new AutoImporter($pdo, $logFile);
    $result = $importer->importAllProperties();

    // Import Kaguya monthly commission data
    writeLog("---------- Starting Kaguya Commission Import ----------", $logFile);
    $kaguyaResult = importKaguyaCommission($pdo, $logFile);
    writeLog("---------- Kaguya Commission Import Completed ----------", $logFile);

    if ($result) {
        writeLog("========== AUTO IMPORT COMPLETED SUCCESSFULLY ==========", $logFile);
        if (!$isCLI) {
            echo json_encode([
                'success' => true,
                'message' => 'Import completed successfully',
                'kaguya_commission' => $kaguyaResult
            ]);
        }
    } else {
        writeLog("========== AUTO IMPORT COMPLETED WITH ERRORS ==========", $logFile);
        if (!$isCLI) {
            echo json_encode(['success' => false, 'message' => 'Import completed with errors']);
        }
    }

} catch (PDOException $e) {
    writeLog("DATABASE ERROR: " . $e->getMessage(), $logFile);
    if (!$isCLI) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit(1);
}
?>
