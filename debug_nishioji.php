<?php
// Debug script for nishioji fujita data import issue
header("Content-Type: text/html; charset=utf-8");

// Test data from the user
$testData = [
    "2024年12月\t12\t2024\t2024/12/30\t2024/12/31\t570000\t2\t2024/09/29\t92",
    "2025年1月\t01\t2025\t2025/01/01\t2025/01/03\t570000\t2\t2024/09/29\t92",
    "2025年3月\t03\t2025\t2025/03/13\t2025/03/14\t240000\t1\t2025/03/05\t8",
    "2025年3月\t03\t2025\t2025/03/29\t2025/03/30\t180000\t1\t2025/02/28\t29",
    "2025年4月\t04\t2025\t2025/04/04\t2025/04/05\t170000\t1\t2025/01/18\t76",
    "2025年4月\t04\t2025\t2025/04/05\t2025/04/06\t205020\t1\t2025/03/15\t21",
    "2025年4月\t04\t2025\t2025/04/23\t2025/04/27\t383035\t4\t2025/03/04\t50",
    "2025年4月\t04\t2025\t2025/04/29\t2025/04/30\t360000\t2\t2025/04/05\t24",
    "2025年5月\t05\t2025\t2025/05/01\t2025/05/01\t0\t0\t2025/04/05\t24",
    "2025年10月\t10\t2025\t2025/10/04\t2025/10/05\t189719\t1\t2025/09/26\t8",
    "2025年10月\t10\t2025\t2025/10/11\t2025/10/13\t237151\t2\t2025/09/29\t12", // Problematic row
    "2025年10月\t10\t2025\t2025/10/27\t2025/10/29\t270000\t2\t2025/09/24\t33"
];

echo "<h2>Debug: Nishioji Fujita Data Parsing</h2>";
echo "<hr>";

// Copied from import_all_final.php
function parseDate($dateString) {
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

function parseBookingDate($dateString, $checkInDate) {
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

function parseNumber($value) {
    if (empty($value)) return 0;
    $value = str_replace(',', '', $value);
    $value = preg_replace('/[^0-9.-]/', '', $value);
    return floatval($value);
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Row</th><th>Check In</th><th>Check Out</th><th>Fee</th><th>Nights</th><th>Booking Date</th><th>Lead Time</th><th>Valid?</th><th>Issue</th>";
echo "</tr>";

$validCount = 0;
$invalidCount = 0;

foreach ($testData as $index => $line) {
    $data = str_getcsv($line, "\t");
    $rowNumber = $index + 1;

    $isHostel = false; // nishioji_fujita is not in hostel list
    $minColumns = 8;

    $issues = [];

    // Check minimum columns
    if (count($data) < $minColumns) {
        $issues[] = "Not enough columns (" . count($data) . " < {$minColumns})";
    }

    // Parse fields
    $checkIn = parseDate($data[3] ?? '');
    $checkOut = parseDate($data[4] ?? '');
    $accommodationFee = parseNumber($data[5] ?? 0);
    $nightCount = intval($data[6] ?? 0);
    $bookingDate = parseBookingDate($data[7] ?? '', $checkIn);
    $leadTime = intval($data[8] ?? 0);

    // Validation
    $isValid = true;

    if (!$checkIn) {
        $issues[] = "Check-in date failed to parse: '{$data[3]}'";
        $isValid = false;
    }

    if (!$checkOut) {
        $issues[] = "Check-out date failed to parse: '{$data[4]}'";
        $isValid = false;
    }

    if ($accommodationFee == 0) {
        $issues[] = "Zero accommodation fee (will import but flagged)";
    }

    if (!$bookingDate) {
        $issues[] = "Booking date failed to parse: '{$data[7]}'";
    }

    // Would this record be imported?
    $wouldImport = ($checkIn && $checkOut);

    if ($wouldImport) {
        $validCount++;
    } else {
        $invalidCount++;
    }

    // Highlight the problematic row (row 11)
    $style = ($rowNumber == 11) ? "background-color: #ffffcc; font-weight: bold;" : "";

    echo "<tr style='{$style}'>";
    echo "<td>{$rowNumber}</td>";
    echo "<td>" . ($checkIn ?: '<span style="color:red;">FAIL</span>') . "</td>";
    echo "<td>" . ($checkOut ?: '<span style="color:red;">FAIL</span>') . "</td>";
    echo "<td>{$accommodationFee}</td>";
    echo "<td>{$nightCount}</td>";
    echo "<td>" . ($bookingDate ?: '<span style="color:orange;">null</span>') . "</td>";
    echo "<td>{$leadTime}</td>";
    echo "<td>" . ($wouldImport ? '<span style="color:green;">✓ YES</span>' : '<span style="color:red;">✗ NO</span>') . "</td>";
    echo "<td>" . (empty($issues) ? 'None' : implode(', ', $issues)) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>✅ Records that would be imported: <strong>{$validCount}</strong></p>";
echo "<p>❌ Records that would be skipped: <strong>{$invalidCount}</strong></p>";

echo "<hr>";
echo "<h3>Conclusion</h3>";
echo "<p>Row 11 (2025/10/11 - 2025/10/13) should be imported if the dates parse correctly.</p>";
echo "<p>If this record is missing from the database, possible reasons:</p>";
echo "<ul>";
echo "<li>The data in Google Sheets might have different column positions</li>";
echo "<li>There might be duplicate detection at database level</li>";
echo "<li>The import might have been run before this row was added to the sheet</li>";
echo "<li>Check the property_sheets table to verify when 'nishioji_fujita' was last imported</li>";
echo "</ul>";
?>
