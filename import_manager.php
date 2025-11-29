<?php
/**
 * Import Manager - User-friendly interface for managing automatic imports
 */
session_start();

// Simple password protection (change this!)
$ADMIN_PASSWORD = 'exseed2025';

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "Invalid password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: import_manager.php');
    exit;
}

// Check if logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Manager - Exseed Occupancy System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
            margin-bottom: 20px;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .status-card h3 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .status-card p {
            opacity: 0.9;
        }
        .log-viewer {
            background: #1e293b;
            color: #10b981;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
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
            background: #f3f4f6;
            font-weight: 600;
        }
        .login-form {
            max-width: 400px;
            margin: 50px auto;
        }
        .login-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .code-box {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .flex {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mb-20 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Import Manager</h1>
            <p>Google Sheets to Database Synchronization</p>
        </div>

        <?php if (!$isLoggedIn): ?>
            <!-- Login Form -->
            <div class="card login-form">
                <h2 style="margin-bottom: 20px; text-align: center;">Login Required</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Enter admin password" required autofocus>
                    <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
                <p style="text-align: center; margin-top: 20px; color: #6b7280; font-size: 14px;">
                    Default password: exseed2025
                </p>
            </div>

        <?php else: ?>
            <!-- Main Dashboard -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Dashboard</h2>
                    <a href="?logout" class="btn btn-secondary">Logout</a>
                </div>

                <!-- Action Buttons -->
                <div class="flex mb-20">
                    <button onclick="runImportNow()" class="btn btn-success" id="importBtn">
                        ▶️ Run Import Now
                    </button>
                    <button onclick="viewLogs()" class="btn btn-primary">
                        📋 View Logs
                    </button>
                    <button onclick="checkStatus()" class="btn btn-warning">
                        🔍 Check Status
                    </button>
                    <a href="#setup" class="btn btn-secondary">
                        ⚙️ Cron Setup
                    </a>
                </div>

                <!-- Status Display -->
                <div id="statusArea"></div>

                <!-- Import Status Cards -->
                <?php
                try {
                    $pdo = new PDO("mysql:host={$host};dbname={$db_name}", $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->exec("set names utf8");

                    // Get last import times
                    $stmt = $pdo->query("SELECT COUNT(*) as total, MAX(last_imported) as last_import FROM property_sheets WHERE is_active = TRUE");
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Get total records
                    $stmt = $pdo->query("SELECT property_name FROM property_sheets WHERE is_active = TRUE");
                    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $totalRecords = 0;
                    foreach ($properties as $prop) {
                        $tableName = strtolower(preg_replace('/[^a-z0-9_]/', '_', $prop['property_name']));
                        try {
                            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `{$tableName}`");
                            $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                            $totalRecords += $count['count'];
                        } catch (Exception $e) {
                            // Skip if table doesn't exist
                        }
                    }

                    echo '<div class="status-grid">';
                    echo '<div class="status-card">';
                    echo '<h3>' . $stats['total'] . '</h3>';
                    echo '<p>Active Properties</p>';
                    echo '</div>';
                    echo '<div class="status-card">';
                    echo '<h3>' . number_format($totalRecords) . '</h3>';
                    echo '<p>Total Records</p>';
                    echo '</div>';
                    echo '<div class="status-card">';
                    echo '<h3>' . ($stats['last_import'] ? date('M d, H:i', strtotime($stats['last_import'])) : 'Never') . '</h3>';
                    echo '<p>Last Import</p>';
                    echo '</div>';
                    echo '</div>';

                } catch (PDOException $e) {
                    echo '<div class="alert alert-error">Database Error: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>

            <!-- Property Status Table -->
            <div class="card">
                <h2 style="margin-bottom: 20px;">Property Import Status</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Property Name</th>
                            <th>Last Imported</th>
                            <th>Records</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT property_name, last_imported FROM property_sheets WHERE is_active = TRUE ORDER BY property_name");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $tableName = strtolower(preg_replace('/[^a-z0-9_]/', '_', $row['property_name']));
                                $recordCount = 0;
                                try {
                                    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `{$tableName}`");
                                    $count = $countStmt->fetch(PDO::FETCH_ASSOC);
                                    $recordCount = $count['count'];
                                } catch (Exception $e) {
                                    $recordCount = 'N/A';
                                }

                                $lastImported = $row['last_imported'] ? date('Y-m-d H:i:s', strtotime($row['last_imported'])) : 'Never';
                                $status = $row['last_imported'] ? '✅ Active' : '⚠️ Never imported';

                                echo "<tr>";
                                echo "<td><strong>{$row['property_name']}</strong></td>";
                                echo "<td>{$lastImported}</td>";
                                echo "<td>" . number_format($recordCount) . "</td>";
                                echo "<td>{$status}</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='4'>Error loading properties</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Cron Setup Instructions -->
            <div class="card" id="setup">
                <h2 style="margin-bottom: 20px;">⚙️ Automatic Import Setup (Cron Job)</h2>

                <div class="alert alert-info">
                    <strong>📌 What is a Cron Job?</strong><br>
                    A cron job is an automated task that runs at scheduled intervals. Once set up, your data will automatically sync from Google Sheets without any manual intervention.
                </div>

                <h3 style="margin: 20px 0 10px;">Step 1: Choose Your Schedule</h3>
                <p style="margin-bottom: 15px;">Select how often you want to import data:</p>

                <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p><strong>🚀 Every 15 minutes (Recommended for real-time):</strong></p>
                    <div class="code-box">*/15 * * * * /usr/bin/php <?php echo __DIR__; ?>/auto_import_cron.php</div>

                    <p><strong>⏰ Every 30 minutes:</strong></p>
                    <div class="code-box">*/30 * * * * /usr/bin/php <?php echo __DIR__; ?>/auto_import_cron.php</div>

                    <p><strong>🕐 Every hour:</strong></p>
                    <div class="code-box">0 * * * * /usr/bin/php <?php echo __DIR__; ?>/auto_import_cron.php</div>

                    <p><strong>🌙 Daily at 3:00 AM:</strong></p>
                    <div class="code-box">0 3 * * * /usr/bin/php <?php echo __DIR__; ?>/auto_import_cron.php</div>
                </div>

                <h3 style="margin: 20px 0 10px;">Step 2: Set Up in Lolipop</h3>
                <ol style="line-height: 1.8; margin-left: 20px;">
                    <li>Login to <a href="https://user.lolipop.jp/" target="_blank" style="color: #667eea;">Lolipop Control Panel</a></li>
                    <li>Navigate to <strong>"定期実行設定"</strong> (Scheduled Execution Settings)</li>
                    <li>Click <strong>"新規作成"</strong> (Create New)</li>
                    <li>Copy one of the cron commands above</li>
                    <li>Paste it into the command field</li>
                    <li>Save the configuration</li>
                </ol>

                <h3 style="margin: 20px 0 10px;">Step 3: Verify It's Working</h3>
                <p>After 15-30 minutes, check the logs or use the "Check Status" button above to verify the cron job is running.</p>

                <div class="alert alert-success" style="margin-top: 20px;">
                    <strong>✅ Alternative Method:</strong> You can also trigger imports manually using this URL:<br>
                    <div class="code-box" style="margin-top: 10px;">
                        <?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/auto_import_cron.php?auth_key=exseed_auto_import_2025'; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function runImportNow() {
            const btn = document.getElementById('importBtn');
            const statusArea = document.getElementById('statusArea');

            btn.disabled = true;
            btn.innerHTML = '⏳ Importing...';

            statusArea.innerHTML = '<div class="loading"><div class="spinner"></div><p>Running import... This may take 30-60 seconds</p></div>';

            fetch('auto_import_cron.php?auth_key=exseed_auto_import_2025')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusArea.innerHTML = '<div class="alert alert-success"><strong>✅ Import Successful!</strong><br>All data has been synced from Google Sheets. Refresh the page to see updated statistics.</div>';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        statusArea.innerHTML = '<div class="alert alert-error"><strong>❌ Import Failed</strong><br>' + (data.error || data.message) + '</div>';
                    }
                })
                .catch(error => {
                    statusArea.innerHTML = '<div class="alert alert-error"><strong>❌ Error</strong><br>Failed to run import: ' + error.message + '</div>';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '▶️ Run Import Now';
                });
        }

        function viewLogs() {
            const statusArea = document.getElementById('statusArea');
            statusArea.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading logs...</p></div>';

            fetch('view_logs.php')
                .then(response => response.text())
                .then(data => {
                    statusArea.innerHTML = '<h3 style="margin-bottom: 10px;">📋 Import Logs</h3><div class="log-viewer">' + (data || 'No logs available yet') + '</div>';
                })
                .catch(error => {
                    statusArea.innerHTML = '<div class="alert alert-error">Failed to load logs: ' + error.message + '</div>';
                });
        }

        function checkStatus() {
            location.reload();
        }
    </script>
</body>
</html>
