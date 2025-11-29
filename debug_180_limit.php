<?php
// Debug script to check has_180_day_limit values

header("Content-Type: application/json");

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all properties with their 180-day limit status
    $stmt = $pdo->query("
        SELECT
            property_name,
            has_180_day_limit,
            is_active
        FROM property_sheets
        ORDER BY property_name
    ");

    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'properties' => $properties,
        'properties_with_limit' => array_filter($properties, function($p) {
            return $p['has_180_day_limit'] == 1 || $p['has_180_day_limit'] == true;
        })
    ], JSON_PRETTY_PRINT);

} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
