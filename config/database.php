<?php
// Database configuration
$host = 'localhost';
$dbname = 'return_qc';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Better error handling - log error and show user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get user accessible tabs based on role and assignments
 * @param PDO $pdo Database connection
 * @return array Array of tabs
 */
function getUserTabs($pdo) {
    // Return empty array if PDO is null or user not logged in
    if (!$pdo || !($pdo instanceof PDO)) {
        error_log("getUserTabs(): Invalid PDO object provided");
        return [];
    }
    
    if (!isLoggedIn()) {
        return [];
    }
    
    try {
        $user_id = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        
        // Admin gets all master tabs
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM master_tabs WHERE is_active = 1 ORDER BY sort_order");
            $stmt->execute();
            return $stmt->fetchAll();
        } else {
            // Regular users get their assigned tabs
            $stmt = $pdo->prepare("
                SELECT ut.* FROM user_tabs ut 
                WHERE ut.user_id = ? AND ut.is_active = 1 
                ORDER BY ut.sort_order
            ");
            $stmt->execute([$user_id]);
            $tabs = $stmt->fetchAll();
            
            // If no assigned tabs, return empty array
            return $tabs ?: [];
        }
    } catch (PDOException $e) {
        // Log error and return empty array instead of crashing
        error_log("Error fetching user tabs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get master tabs for dropdown
 * @param PDO $pdo Database connection
 * @return array Array of master tabs
 */
function getMasterTabs($pdo) {
    if (!$pdo || !($pdo instanceof PDO)) {
        error_log("getMasterTabs(): Invalid PDO object provided");
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM master_tabs WHERE is_active = 1 ORDER BY sort_order");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching master tabs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user by ID
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array|false User data or false
 */
function getUserById($pdo, $user_id) {
    if (!$pdo || !($pdo instanceof PDO)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all users (for admin)
 * @param PDO $pdo Database connection
 * @return array Array of users
 */
function getAllUsers($pdo) {
    if (!$pdo || !($pdo instanceof PDO)) {
        return [];
    }
    
    try {
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active, created_at FROM users ORDER BY full_name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a tab exists in master_tabs
 * @param PDO $pdo Database connection
 * @param string $tab_name Tab name to check
 * @return bool
 */
function tabExists($pdo, $tab_name) {
    if (!$pdo || !($pdo instanceof PDO)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM master_tabs WHERE tab_name = ?");
        $stmt->execute([$tab_name]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
?>