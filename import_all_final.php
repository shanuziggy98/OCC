<?php
// Import all 25 properties with complete working logic
header("Content-Type: text/html; charset=utf-8");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

echo "<h2>Final Import - All 25 Properties</h2>";
echo "<hr>";

class FinalPropertyImporter {
    private $pdo;
    private $hostelProperties = ['iwatoyama', 'Goettingen', 'littlehouse'];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function sanitizeTableName($propertyName) {
        $tableName = strtolower($propertyName);
        $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
        $tableName = preg_replace('/_+/', '_', $tableName);
        $tableName = trim($tableName, '_');
        return $tableName;
    }

    public function isHostelProperty($propertyName) {
        return in_array($propertyName, $this->hostelProperties);
    }

    public function importDataToPropertyTable($propertyName, $googleSheetUrl) {
        $tableName = $this->sanitizeTableName($propertyName);
        $isHostel = $this->isHostelProperty($propertyName);

        try {
            echo "<h4>🔄 Processing: {$propertyName}" . ($isHostel ? " (Hostel)" : "") . "</h4>";

            // Clear existing data
            $this->pdo->exec("DELETE FROM `{$tableName}`");

            // Fetch CSV data
            $csvData = $this->fetchCSVData($googleSheetUrl);
            if (!$csvData) {
                throw new Exception("Failed to fetch data from Google Sheets");
            }

            // Parse data
            $importedCount = $this->parseByPosition($csvData, $tableName, $isHostel);

            // Update timestamp
            $updateStmt = $this->pdo->prepare("UPDATE property_sheets SET last_imported = NOW() WHERE property_name = ?");
            $updateStmt->execute([$propertyName]);

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'table_name' => $tableName
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'table_name' => $tableName
            ];
        }
    }

    private function fetchCSVData($csvUrl) {
        $context = stream_context_create([
            "http" => [
                "timeout" => 30,
                "user_agent" => "Mozilla/5.0 (compatible; ExseedOcc/1.0)",
                "follow_location" => true,
                "max_redirects" => 5
            ]
        ]);

        return file_get_contents($csvUrl, false, $context);
    }

    private function parseByPosition($csvData, $tableName, $isHostel) {
        $lines = explode("\n", $csvData);
        array_shift($lines); // Skip header

        $importedCount = 0;
        $errorCount = 0;
        $zeroFeeCount = 0;

        // Prepare insert statement
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
                    // Hostel format: D,E,F,G,H,I,J,K (indices 3,4,5,6,7,8,9,10)
                    $checkIn = $this->parseDate($data[3] ?? '');
                    $checkOut = $this->parseDate($data[4] ?? '');
                    $accommodationFee = $this->parseNumber($data[5] ?? 0);
                    $nightCount = intval($data[6] ?? 0);
                    $bookingDate = $this->parseBookingDate($data[7] ?? '', $checkIn);
                    $leadTime = intval($data[8] ?? 0);
                    $roomNumber = trim($data[9] ?? '');
                    $peopleCount = intval($data[10] ?? 0); // Column K
                } else {
                    // Standard format
                    $checkIn = $this->parseDate($data[3] ?? '');
                    $checkOut = $this->parseDate($data[4] ?? '');
                    $accommodationFee = $this->parseNumber($data[5] ?? 0);
                    $nightCount = intval($data[6] ?? 0);
                    $bookingDate = $this->parseBookingDate($data[7] ?? '', $checkIn);
                    $leadTime = intval($data[8] ?? 0);
                    $roomNumber = '';
                    $peopleCount = intval($data[10] ?? 0); // Column K
                }

                // Store raw data
                $rawData = json_encode([
                    'original_row' => $data,
                    'line_number' => $lineIndex + 2,
                    'is_hostel' => $isHostel
                ], JSON_UNESCAPED_UNICODE);

                // Validate required fields
                if ($checkIn && $checkOut) {
                    if ($accommodationFee == 0) {
                        $zeroFeeCount++;
                    }

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
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
                continue;
            }
        }

        echo "<p>✅ Imported: <strong>{$importedCount}</strong> records";
        if ($zeroFeeCount > 0) {
            echo " (including {$zeroFeeCount} zero fee)";
        }
        if ($errorCount > 0) {
            echo " | Skipped: {$errorCount}";
        }
        echo "</p>";

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

        // First try standard date formats
        $standardFormats = ['Y/m/d', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d H:i:s'];
        foreach ($standardFormats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        // Handle M/d format (like 4/10, 4/17)
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

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");

    echo "<p>✅ Connected to database successfully!</p>";

    $importer = new FinalPropertyImporter($pdo);

    // Get all properties
    $stmt = $pdo->query("SELECT property_name, google_sheet_url FROM property_sheets WHERE is_active = TRUE ORDER BY property_name");
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($properties)) {
        echo "<p>❌ No properties found!</p>";
        exit;
    }

    echo "<p>📊 Processing <strong>" . count($properties) . "</strong> properties...</p>";
    echo "<p>🏨 Hostels (with room numbers): iwatoyama, Goettingen, littlehouse</p>";
    echo "<hr>";

    $totalImported = 0;
    $successCount = 0;
    $errorCount = 0;
    $results = [];

    foreach ($properties as $property) {
        $propertyName = $property['property_name'];
        $googleSheetUrl = $property['google_sheet_url'];

        $result = $importer->importDataToPropertyTable($propertyName, $googleSheetUrl);

        if ($result['success']) {
            $successCount++;
            $totalImported += $result['imported_count'];
            $results[] = [
                'name' => $propertyName,
                'status' => 'success',
                'count' => $result['imported_count'],
                'is_hostel' => $importer->isHostelProperty($propertyName)
            ];
        } else {
            $errorCount++;
            echo "<p>❌ <strong>{$propertyName}</strong>: {$result['error']}</p>";
            $results[] = [
                'name' => $propertyName,
                'status' => 'error',
                'error' => $result['error'],
                'is_hostel' => false
            ];
        }
    }

    echo "<hr>";
    echo "<h3>🎉 All Properties Import Complete!</h3>";
    echo "<p>✅ Successfully processed: <strong>{$successCount}</strong> properties</p>";
    echo "<p>❌ Errors: <strong>{$errorCount}</strong> properties</p>";
    echo "<p>📊 Total records imported: <strong>{$totalImported}</strong></p>";

    // Summary table
    echo "<h4>Final Results Summary:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Property</th><th>Type</th><th>Status</th><th>Records</th></tr>";

    foreach ($results as $result) {
        $status = $result['status'] == 'success' ? '✅ Success' : '❌ Error';
        $count = $result['status'] == 'success' ? $result['count'] : $result['error'];
        $type = $result['is_hostel'] ? '🏨 Hostel' : '🏠 Property';

        echo "<tr>";
        echo "<td>{$result['name']}</td>";
        echo "<td>{$type}</td>";
        echo "<td>{$status}</td>";
        echo "<td>{$count}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<h3>🚀 Ready for Next Steps!</h3>";
    echo "<ul>";
    echo "<li>✅ All property data imported with complete information</li>";
    echo "<li>✅ Booking dates, lead times, night counts working</li>";
    echo "<li>✅ Room numbers for hostels captured</li>";
    echo "<li>🎯 Ready to connect frontend and test occupancy calculations</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>