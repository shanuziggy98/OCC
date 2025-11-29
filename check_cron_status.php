<?php
/**
 * Cron Job Status Checker
 * This script checks if automatic imports are working
 *
 * Upload this to: /home/users/2/main.jp-exseed/web/WG/analysis/OCC/
 * Access via: https://exseed.main.jp/WG/analysis/OCC/check_cron_status.php
 */

header('Content-Type: text/html; charset=utf-8');

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cron Job Status Check</title>
    <meta http-equiv="refresh" content="30">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 18px;
        }
        .status-active {
            background: #dcfce7;
            border: 2px solid #22c55e;
            color: #15803d;
        }
        .status-inactive {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }
        .status-warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            color: #92400e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .timestamp {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .indicator-green {
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
        }
        .indicator-red {
            background: #ef4444;
        }
        .indicator-yellow {
            background: #f59e0b;
        }
        .refresh-notice {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #2563eb;
        }
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .time-diff {
            font-weight: bold;
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Automatic Import Status Checker</h1>

        <?php
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("set names utf8");

            // Get last import times for all properties
            $stmt = $pdo->query("
                SELECT
                    property_name,
                    last_imported,
                    TIMESTAMPDIFF(MINUTE, last_imported, NOW()) as minutes_ago,
                    is_active
                FROM property_sheets
                WHERE is_active = TRUE
                ORDER BY last_imported DESC
            ");

            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($properties) > 0) {
                // Get the most recent import
                $mostRecent = $properties[0];
                $minutesAgo = intval($mostRecent['minutes_ago']);

                // Determine status
                $isWorking = false;
                $statusClass = 'status-inactive';
                $statusMessage = '';
                $indicator = 'indicator-red';

                if ($minutesAgo <= 20) {
                    $isWorking = true;
                    $statusClass = 'status-active';
                    $statusMessage = '✅ Cron job is WORKING! Imports are running automatically.';
                    $indicator = 'indicator-green';
                } elseif ($minutesAgo <= 60) {
                    $statusClass = 'status-warning';
                    $statusMessage = '⚠️ Cron job might be working, but last import was ' . $minutesAgo . ' minutes ago.';
                    $indicator = 'indicator-yellow';
                } else {
                    $statusMessage = '❌ Cron job is NOT working. Last import was ' . $minutesAgo . ' minutes ago.';
                    $indicator = 'indicator-red';
                }

                echo "<div class='status-box {$statusClass}'>";
                echo "<span class='indicator {$indicator}'></span>";
                echo $statusMessage;
                echo "</div>";

                // Show current time and last import time
                $currentTime = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
                $lastImportTime = new DateTime($mostRecent['last_imported'], new DateTimeZone('Asia/Tokyo'));

                echo "<div class='info-box'>";
                echo "<strong>Current Server Time:</strong> <span class='timestamp'>" . $currentTime->format('Y-m-d H:i:s') . "</span><br>";
                echo "<strong>Last Import Time:</strong> <span class='timestamp'>" . $lastImportTime->format('Y-m-d H:i:s') . "</span><br>";
                echo "<strong>Time Since Last Import:</strong> <span class='time-diff'>" . $minutesAgo . " minutes ago</span>";
                echo "</div>";

                // Show all properties and their last import times
                echo "<h2>📊 All Properties Import Status</h2>";
                echo "<table>";
                echo "<thead><tr>";
                echo "<th>Property Name</th>";
                echo "<th>Last Import Time</th>";
                echo "<th>Minutes Ago</th>";
                echo "<th>Status</th>";
                echo "</tr></thead>";
                echo "<tbody>";

                foreach ($properties as $prop) {
                    $propMinutes = intval($prop['minutes_ago']);
                    $propStatus = '';
                    $propIndicator = 'indicator-red';

                    if ($propMinutes <= 20) {
                        $propStatus = '✅ Recent';
                        $propIndicator = 'indicator-green';
                    } elseif ($propMinutes <= 60) {
                        $propStatus = '⚠️ Warning';
                        $propIndicator = 'indicator-yellow';
                    } else {
                        $propStatus = '❌ Old';
                        $propIndicator = 'indicator-red';
                    }

                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($prop['property_name']) . "</strong></td>";
                    echo "<td><span class='timestamp'>" . $prop['last_imported'] . "</span></td>";
                    echo "<td>" . $propMinutes . " min</td>";
                    echo "<td><span class='indicator {$propIndicator}'></span>{$propStatus}</td>";
                    echo "</tr>";
                }

                echo "</tbody></table>";

                // Instructions based on status
                if (!$isWorking) {
                    echo "<div class='info-box' style='background: #fef3c7; border-color: #f59e0b;'>";
                    echo "<h3>🔧 Troubleshooting Steps:</h3>";
                    echo "<ol>";
                    echo "<li>Check if your cron job is saved in Lolipop control panel</li>";
                    echo "<li>Verify the executable path is: <code>WG/analysis/OCC/auto_import_cron.php</code></li>";
                    echo "<li>Make sure you selected the correct time intervals (every 15 minutes)</li>";
                    echo "<li>Try running a manual import to test:</li>";
                    echo "</ol>";
                    echo "<a href='auto_import_cron.php?auth_key=exseed_auto_import_2025' class='btn'>Run Manual Import Now</a>";
                    echo "</div>";
                } else {
                    echo "<div class='info-box' style='background: #dcfce7; border-color: #22c55e;'>";
                    echo "<h3>✅ Everything is working correctly!</h3>";
                    echo "<p>Your data is being automatically imported every 15 minutes from Google Sheets.</p>";
                    echo "<p>The dashboard will always show fresh data.</p>";
                    echo "</div>";
                }

            } else {
                echo "<div class='status-box status-inactive'>";
                echo "❌ No properties found in database.";
                echo "</div>";
            }

        } catch (PDOException $e) {
            echo "<div class='status-box status-inactive'>";
            echo "❌ Database connection error: " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
        ?>

        <div class="refresh-notice">
            🔄 This page auto-refreshes every 30 seconds
            <br>
            <a href="?" class="btn">Refresh Now</a>
            <a href="https://exseed.main.jp/WG/analysis/OCC/" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
