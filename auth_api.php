<?php
/**
 * Authentication API for Property Management System
 * Handles login, logout, and session verification
 */

session_start();

header("Access-Control-Allow-Origin: https://exseed.main.jp");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

class AuthAPI {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . $GLOBALS['host'] . ";dbname=" . $GLOBALS['db_name'],
                $GLOBALS['username'],
                $GLOBALS['password']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("set names utf8mb4");
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function login($username, $password) {
        try {
            // Get user from database
            $stmt = $this->pdo->prepare("
                SELECT
                    id,
                    username,
                    password,
                    user_type,
                    property_name,
                    full_name,
                    email,
                    is_active
                FROM property_users
                WHERE username = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify user exists
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'Invalid username or password'
                ];
            }

            // Verify password (plain text comparison as per database design)
            if ($password !== $user['password']) {
                return [
                    'success' => false,
                    'error' => 'Invalid username or password'
                ];
            }

            // Update last login time
            $updateStmt = $this->pdo->prepare("
                UPDATE property_users
                SET last_login = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateStmt->execute([$user['id']]);

            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['property_name'] = $user['property_name'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;

            // Return user info (without password)
            unset($user['password']);

            // Determine redirect based on user type
            $redirect = '/property-dashboard';
            if ($user['user_type'] === 'admin') {
                $redirect = '/admin-dashboard';
            } elseif ($user['user_type'] === 'cpanel') {
                $redirect = '/cpanel-dashboard';
            }

            return [
                'success' => true,
                'user' => $user,
                'redirect' => $redirect
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Login failed: ' . $e->getMessage()
            ];
        }
    }

    public function logout() {
        session_destroy();
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }

    public function checkSession() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return [
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'user_type' => $_SESSION['user_type'],
                    'property_name' => $_SESSION['property_name'],
                    'full_name' => $_SESSION['full_name'],
                    'email' => $_SESSION['email']
                ]
            ];
        } else {
            return [
                'authenticated' => false
            ];
        }
    }

    public function route() {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'check';

        try {
            switch ($action) {
                case 'login':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $username = $data['username'] ?? '';
                    $password = $data['password'] ?? '';

                    if (empty($username) || empty($password)) {
                        $this->sendResponse([
                            'success' => false,
                            'error' => 'Username and password are required'
                        ], 400);
                        return;
                    }

                    $result = $this->login($username, $password);
                    $this->sendResponse($result);
                    break;

                case 'logout':
                    $result = $this->logout();
                    $this->sendResponse($result);
                    break;

                case 'check':
                    $result = $this->checkSession();
                    $this->sendResponse($result);
                    break;

                default:
                    $this->sendResponse(['error' => 'Unknown action'], 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Initialize and run the API
try {
    $api = new AuthAPI();
    $api->route();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
