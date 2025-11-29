<?php
/**
 * Simple log viewer for auto import logs
 */
header('Content-Type: text/plain; charset=utf-8');

$logDir = __DIR__ . '/logs';
$currentMonth = date('Y-m');
$logFile = $logDir . '/auto_import_' . $currentMonth . '.log';

if (file_exists($logFile)) {
    // Get last 100 lines of log
    $lines = file($logFile);
    $totalLines = count($lines);
    $displayLines = array_slice($lines, max(0, $totalLines - 100));

    echo "=== Showing last " . count($displayLines) . " lines of " . $totalLines . " total ===\n\n";
    echo implode('', $displayLines);
} else {
    echo "No log file found for current month ({$currentMonth})\n\n";
    echo "Log file expected at: {$logFile}\n\n";

    // Check if logs directory exists
    if (!file_exists($logDir)) {
        echo "Note: Logs directory does not exist yet.\n";
        echo "It will be created automatically when the first import runs.\n";
    } else {
        // List available log files
        $logFiles = glob($logDir . '/auto_import_*.log');
        if (!empty($logFiles)) {
            echo "Available log files:\n";
            foreach ($logFiles as $file) {
                echo "- " . basename($file) . " (" . filesize($file) . " bytes)\n";
            }
        } else {
            echo "No log files found. The import hasn't run yet.\n";
        }
    }
}
?>
