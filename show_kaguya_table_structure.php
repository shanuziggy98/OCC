<?php
/**
 * Show Kaguya Table Structure
 */

$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

header("Content-Type: text/plain; charset=utf-8");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== KAGUYA TABLE STRUCTURE ===\n\n";

    $stmt = $pdo->query("DESCRIBE kaguya");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Columns in kaguya table:\n";
    echo str_repeat("-", 80) . "\n";
    echo sprintf("%-25s %-20s %-8s %-8s %-8s %s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 80) . "\n";

    foreach ($columns as $column) {
        echo sprintf("%-25s %-20s %-8s %-8s %-8s %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Default'] ?? 'NULL',
            $column['Extra']
        );
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
