<?php
/**
 * C-Panel API for Property Management System
 *
 * This API handles all C-Panel operations including:
 * - Property management (CRUD)
 * - User management (CRUD)
 *
 * Upload this file to: https://exseed.main.jp/WG/analysis/OCC/cpanel_api.php
 */

header("Access-Control-Allow-Origin: https://exseed.main.jp");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration - Same as auth_api.php
$db_host = 'mysql327.phy.lolipop.lan';
$db_name = 'LAA0963548-occ';
$db_user = 'LAA0963548';
$db_pass = 'EXseed55';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8mb4");
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Start session for authentication
session_start();

// Check if user is authenticated as cpanel
function checkCPanelAuth() {
    // Check session variables set by auth_api.php
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['error' => 'Unauthorized. Please log in.']);
        exit();
    }

    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'cpanel') {
        echo json_encode(['error' => 'Unauthorized. C-Panel access required.']);
        exit();
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Debug: Log received action
// error_log("CPanel API - Action: " . $action . " | Session: " . print_r($_SESSION, true));

switch ($action) {
    case 'get_properties':
        checkCPanelAuth();
        getProperties($pdo);
        break;

    case 'add_property':
        checkCPanelAuth();
        addProperty($pdo);
        break;

    case 'update_property':
        checkCPanelAuth();
        updateProperty($pdo);
        break;

    case 'delete_property':
        checkCPanelAuth();
        deleteProperty($pdo);
        break;

    case 'get_users':
        checkCPanelAuth();
        getUsers($pdo);
        break;

    case 'get_property_owners':
        checkCPanelAuth();
        getPropertyOwners($pdo);
        break;

    case 'add_user':
        checkCPanelAuth();
        addUser($pdo);
        break;

    case 'update_user':
        checkCPanelAuth();
        updateUser($pdo);
        break;

    case 'delete_user':
        checkCPanelAuth();
        deleteUser($pdo);
        break;

    case '':
        echo json_encode(['error' => 'No action specified', 'received_get' => $_GET]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action: ' . $action]);
        break;
}

/**
 * Get all properties from the database
 */
function getProperties($pdo) {
    try {
        // Fetch from property_sheets table with settings from property_settings
        // Also join with property_users to get owner information
        $stmt = $pdo->query("
            SELECT
                ps.id,
                ps.property_name,
                ps.property_type,
                ps.total_rooms,
                ps.has_180_day_limit,
                ps.room_list,
                ps.commission_method,
                ps.google_sheet_url,
                ps.display_order,
                COALESCE(pset.commission_percent, 15.00) as commission_rate,
                COALESCE(pset.cleaning_fee, 0.00) as cleaning_fee,
                pset.commission_calculation_method,
                pu.owner_id as owner_username
            FROM property_sheets ps
            LEFT JOIN property_settings pset ON ps.property_name = pset.property_name AND pset.room_name IS NULL AND pset.is_active = TRUE
            LEFT JOIN property_users pu ON ps.property_name = pu.property_name AND pu.user_type = 'owner' AND pu.is_active = 1
            WHERE ps.is_active = TRUE
            ORDER BY ps.display_order, ps.property_name
        ");
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format properties for frontend
        $formattedProperties = [];
        foreach ($properties as $prop) {
            // Determine commission method display
            $commissionMethod = 'percentage';
            if ($prop['commission_method'] === 'fixed') {
                $commissionMethod = 'fixed';
            } elseif (strtolower($prop['property_name']) === 'kaguya') {
                $commissionMethod = 'kaguya_monthly';
            }

            $formattedProperties[] = [
                'id' => $prop['id'] ?? null,
                'property_name' => $prop['property_name'],
                'property_type' => $prop['property_type'] ?? 'guesthouse',
                'total_rooms' => (int)($prop['total_rooms'] ?? 1),
                'commission_rate' => (float)$prop['commission_rate'],
                'cleaning_fee' => (float)$prop['cleaning_fee'],
                'has_180_day_limit' => (bool)($prop['has_180_day_limit'] ?? false),
                'room_types' => !empty($prop['room_list']) ? array_map('trim', explode(',', $prop['room_list'])) : [],
                'owner_username' => $prop['owner_username'] ?? null,
                'commission_method' => $commissionMethod,
                'google_sheet_url' => $prop['google_sheet_url'] ?? '',
                'display_order' => (int)($prop['display_order'] ?? 0)
            ];
        }

        echo json_encode(['properties' => $formattedProperties]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to fetch properties: ' . $e->getMessage()]);
    }
}

/**
 * Add a new property
 */
function addProperty($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['property_name'])) {
        echo json_encode(['error' => 'Property name is required']);
        return;
    }

    if (empty($data['google_sheet_url'])) {
        echo json_encode(['error' => 'Google Sheet URL is required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Insert into property_sheets
        $roomList = is_array($data['room_types']) ? implode(',', $data['room_types']) : '';

        $stmt = $pdo->prepare("
            INSERT INTO property_sheets
            (property_name, property_type, total_rooms, has_180_day_limit, room_list, google_sheet_url, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
        ");

        $stmt->execute([
            $data['property_name'],
            $data['property_type'] ?? 'guesthouse',
            $data['total_rooms'] ?? 1,
            $data['has_180_day_limit'] ?? false,
            $roomList,
            $data['google_sheet_url'],
            $data['display_order'] ?? 0
        ]);

        $propertyId = $pdo->lastInsertId();

        // Insert into property_settings
        $stmt = $pdo->prepare("
            INSERT INTO property_settings
            (property_name, room_name, commission_percent, cleaning_fee, is_active)
            VALUES (?, NULL, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE
            commission_percent = VALUES(commission_percent),
            cleaning_fee = VALUES(cleaning_fee)
        ");

        $stmt->execute([
            $data['property_name'],
            $data['commission_rate'] ?? 15,
            $data['cleaning_fee'] ?? 0
        ]);

        // Create the booking table for this property
        $tableName = sanitizeTableName($data['property_name']);
        createPropertyTable($pdo, $tableName);

        // If an existing owner was selected, update their property_name
        // owner_username now contains the owner_id value
        if (!empty($data['owner_username'])) {
            $stmt = $pdo->prepare("
                UPDATE property_users
                SET property_name = ?
                WHERE owner_id = ? AND user_type = 'owner'
            ");
            $stmt->execute([$data['property_name'], $data['owner_username']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $propertyId, 'table_created' => $tableName]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to add property: ' . $e->getMessage()]);
    }
}

/**
 * Sanitize property name to create valid table name
 */
function sanitizeTableName($propertyName) {
    $tableName = strtolower($propertyName);
    $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
    $tableName = preg_replace('/_+/', '_', $tableName);
    $tableName = trim($tableName, '_');
    return $tableName;
}

/**
 * Create booking table for a property
 */
function createPropertyTable($pdo, $tableName) {
    // Check if table already exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);

    if ($stmt->rowCount() > 0) {
        return; // Table already exists
    }

    // Create the table
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

    $pdo->exec($createTableSQL);
}

/**
 * Update an existing property
 */
function updateProperty($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['id'])) {
        echo json_encode(['error' => 'Property ID is required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Get original property name for updating property_settings
        $stmt = $pdo->prepare("SELECT property_name FROM property_sheets WHERE id = ?");
        $stmt->execute([$data['id']]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        $originalName = $original ? $original['property_name'] : $data['property_name'];

        // Update property_sheets
        $roomList = is_array($data['room_types']) ? implode(',', $data['room_types']) : '';

        // Build update query - include google_sheet_url if provided
        if (!empty($data['google_sheet_url'])) {
            $stmt = $pdo->prepare("
                UPDATE property_sheets
                SET property_name = ?, property_type = ?, total_rooms = ?,
                    has_180_day_limit = ?, room_list = ?, google_sheet_url = ?, display_order = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['property_name'],
                $data['property_type'] ?? 'guesthouse',
                $data['total_rooms'] ?? 1,
                $data['has_180_day_limit'] ?? false,
                $roomList,
                $data['google_sheet_url'],
                $data['display_order'] ?? 0,
                $data['id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE property_sheets
                SET property_name = ?, property_type = ?, total_rooms = ?,
                    has_180_day_limit = ?, room_list = ?, display_order = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['property_name'],
                $data['property_type'] ?? 'guesthouse',
                $data['total_rooms'] ?? 1,
                $data['has_180_day_limit'] ?? false,
                $roomList,
                $data['display_order'] ?? 0,
                $data['id']
            ]);
        }

        // Update property_settings
        $stmt = $pdo->prepare("
            INSERT INTO property_settings
            (property_name, room_name, commission_percent, cleaning_fee, is_active)
            VALUES (?, NULL, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE
            property_name = VALUES(property_name),
            commission_percent = VALUES(commission_percent),
            cleaning_fee = VALUES(cleaning_fee)
        ");

        $stmt->execute([
            $data['property_name'],
            $data['commission_rate'] ?? 15,
            $data['cleaning_fee'] ?? 0
        ]);

        // If property name changed, update old record
        if ($originalName !== $data['property_name']) {
            $stmt = $pdo->prepare("UPDATE property_settings SET property_name = ? WHERE property_name = ?");
            $stmt->execute([$data['property_name'], $originalName]);

            // Also update property_users with the new property name
            $stmt = $pdo->prepare("UPDATE property_users SET property_name = ? WHERE property_name = ?");
            $stmt->execute([$data['property_name'], $originalName]);
        }

        // Update owner assignment if provided
        // owner_username now contains the owner_id value
        if (!empty($data['owner_username'])) {
            // First, clear the property_name from any previous owner of this property
            $stmt = $pdo->prepare("
                UPDATE property_users
                SET property_name = NULL
                WHERE property_name = ? AND user_type = 'owner'
            ");
            $stmt->execute([$data['property_name']]);

            // Then assign the new owner by owner_id
            $stmt = $pdo->prepare("
                UPDATE property_users
                SET property_name = ?
                WHERE owner_id = ? AND user_type = 'owner'
            ");
            $stmt->execute([$data['property_name'], $data['owner_username']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to update property: ' . $e->getMessage()]);
    }
}

/**
 * Delete a property
 */
function deleteProperty($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || (empty($data['id']) && empty($data['property_name']))) {
        echo json_encode(['error' => 'Property ID or name is required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $propertyName = $data['property_name'] ?? null;

        // Get property name if only ID provided
        if (!empty($data['id']) && empty($propertyName)) {
            $stmt = $pdo->prepare("SELECT property_name FROM property_sheets WHERE id = ?");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $propertyName = $result ? $result['property_name'] : null;
        }

        // Delete from property_sheets
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("DELETE FROM property_sheets WHERE id = ?");
            $stmt->execute([$data['id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM property_sheets WHERE property_name = ?");
            $stmt->execute([$data['property_name']]);
        }

        // Delete from property_settings
        if ($propertyName) {
            $stmt = $pdo->prepare("DELETE FROM property_settings WHERE property_name = ?");
            $stmt->execute([$propertyName]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Failed to delete property: ' . $e->getMessage()]);
    }
}

/**
 * Get all users from the database
 */
function getUsers($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, username, password, user_type, property_name, full_name, email, owner_id, is_active FROM property_users ORDER BY id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['users' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to fetch users: ' . $e->getMessage()]);
    }
}

/**
 * Get property owners for dropdown
 */
function getPropertyOwners($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT owner_id, full_name
            FROM property_users
            WHERE user_type IN ('owner', 'property_owner') AND is_active = 1
            ORDER BY full_name
        ");
        $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['owners' => $owners]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to fetch owners: ' . $e->getMessage()]);
    }
}

/**
 * Add a new user
 */
function addUser($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['username']) || empty($data['password'])) {
        echo json_encode(['error' => 'Username and password are required']);
        return;
    }

    try {
        // Store password as plain text (matching existing system behavior)
        // Set owner_id to username by default if not provided
        $ownerId = !empty($data['owner_id']) ? $data['owner_id'] : $data['username'];

        $stmt = $pdo->prepare("
            INSERT INTO property_users (username, password, user_type, property_name, full_name, email, owner_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $data['username'],
            $data['password'],  // Plain text password (as per existing system)
            $data['user_type'] ?? 'owner',
            $data['property_name'] ?? null,
            $data['full_name'] ?? '',
            $data['email'] ?? '',
            $ownerId
        ]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['error' => 'Username already exists']);
        } else {
            echo json_encode(['error' => 'Failed to add user: ' . $e->getMessage()]);
        }
    }
}

/**
 * Update an existing user
 */
function updateUser($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['id'])) {
        echo json_encode(['error' => 'User ID is required']);
        return;
    }

    try {
        if (!empty($data['password'])) {
            // Update with new password (plain text as per existing system)
            $stmt = $pdo->prepare("
                UPDATE property_users
                SET username = ?, password = ?, user_type = ?, property_name = ?, full_name = ?, email = ?, owner_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['username'],
                $data['password'],  // Plain text password
                $data['user_type'] ?? 'owner',
                $data['property_name'] ?? null,
                $data['full_name'] ?? '',
                $data['email'] ?? '',
                $data['owner_id'] ?? $data['username'],
                $data['id']
            ]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("
                UPDATE property_users
                SET username = ?, user_type = ?, property_name = ?, full_name = ?, email = ?, owner_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['username'],
                $data['user_type'] ?? 'owner',
                $data['property_name'] ?? null,
                $data['full_name'] ?? '',
                $data['email'] ?? '',
                $data['owner_id'] ?? $data['username'],
                $data['id']
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
    }
}

/**
 * Delete a user
 */
function deleteUser($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['id'])) {
        echo json_encode(['error' => 'User ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM property_users WHERE id = ?");
        $stmt->execute([$data['id']]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
    }
}
?>
