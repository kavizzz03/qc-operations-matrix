<?php
// task_helper.php - Task management functions
// This file works alongside your existing db.php

require_once 'db.php';

/**
 * Get all available tasks for a user
 */
function getUserTasks($pdo, $userId) {
    try {
        // Super admin (user_id = 1) gets all tasks
        if ($userId == 1) {
            $stmt = $pdo->query("SELECT * FROM tasks WHERE is_active = 1 ORDER BY task_id ASC");
            return $stmt->fetchAll();
        }
        
        $stmt = $pdo->prepare("
            SELECT t.task_id, t.task_name, t.task_code, t.description 
            FROM user_tasks ut 
            INNER JOIN tasks t ON ut.task_id = t.task_id 
            WHERE ut.user_id = ? AND t.is_active = 1
            ORDER BY t.task_id ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getUserTasks: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a user has a specific task
 */
function userHasTask($pdo, $userId, $taskCode) {
    try {
        // Super admin (user_id = 1) has all tasks
        if ($userId == 1) {
            return true;
        }
        
        $stmt = $pdo->prepare("
            SELECT 1 FROM user_tasks ut 
            INNER JOIN tasks t ON ut.task_id = t.task_id 
            WHERE ut.user_id = ? AND t.task_code = ? AND t.is_active = 1
        ");
        $stmt->execute([$userId, $taskCode]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in userHasTask: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all available tasks in the system
 */
function getAllTasks($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM tasks WHERE is_active = 1 ORDER BY task_id ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllTasks: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's assigned task IDs
 */
function getUserTaskIds($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error in getUserTaskIds: " . $e->getMessage());
        return [];
    }
}

/**
 * Assign tasks to a user
 */
function assignUserTasks($pdo, $userId, $taskIds, $assignedBy) {
    try {
        $pdo->beginTransaction();
        
        // Remove existing assignments
        $stmt = $pdo->prepare("DELETE FROM user_tasks WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Add new assignments
        if (!empty($taskIds) && is_array($taskIds)) {
            $insertStmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
            foreach ($taskIds as $taskId) {
                $insertStmt->execute([$userId, $taskId, $assignedBy]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in assignUserTasks: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user details by ID
 */
function getUserById($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in getUserById: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user details
 */
function updateUserDetails($pdo, $userId, $name, $role, $password = null) {
    try {
        if ($password && !empty($password)) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, password = ? WHERE user_id = ?");
            return $stmt->execute([$name, $role, $password, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ? WHERE user_id = ?");
            return $stmt->execute([$name, $role, $userId]);
        }
    } catch (PDOException $e) {
        error_log("Error in updateUserDetails: " . $e->getMessage());
        return false;
    }
}

/**
 * Toggle user active status
 */
function toggleUserStatus($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error in toggleUserStatus: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a user and their task assignments
 */
function deleteUser($pdo, $userId) {
    if ($userId == 1) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM user_tasks WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in deleteUser: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user activity for audit trail
 */
function logActivity($pdo, $userId, $action, $details = null) {
    try {
        // Check if audit_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
        if ($stmt->rowCount() > 0) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            return $logStmt->execute([$userId, $action, $details, $ip, $userAgent]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Log activity error: " . $e->getMessage());
        return false;
    }
}

// Auto-load user tasks into session if user is logged in
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_tasks'])) {
    $_SESSION['user_tasks'] = getUserTasks($pdo, $_SESSION['user_id']);
}

// Set super admin flag
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    $_SESSION['is_super_admin'] = 1;
}
?>