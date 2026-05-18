<?php 
require 'db.php';
require_once 'task_helper.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in - BUT with a flag to prevent loops
if (isset($_SESSION['user_id']) && !isset($_GET['logout'])) {
    header("Location: dashboard.php");
    exit;
}

// Clear any existing session if logout is requested
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
}

// Check for any error messages from previous attempts
$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Function to log login attempts
function logLoginAttempt($pdo, $userId, $status, $sessionId = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO user_login_logs (user_id, login_time, ip_address, user_agent, login_status, session_id) 
            VALUES (?, NOW(), ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $ip_address, $user_agent, $status, $sessionId]);
    } catch (PDOException $e) {
        error_log("Login log error: " . $e->getMessage());
        return false;
    }
}

// Function to update user's last login info
function updateUserLastLogin($pdo, $userId, $ipAddress) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = NOW(), 
                last_login_ip = ?,
                total_logins = total_logins + 1
            WHERE user_id = ?
        ");
        return $stmt->execute([$ipAddress, $userId]);
    } catch (PDOException $e) {
        error_log("Update last login error: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>ASB Fashion | QC & Return Management System</title>
</head>
<body>
    <!-- Simple Background -->
    <div class="bg-gradient"></div>
    
    <!-- Form Processing Logic -->
    <?php
    $inline_error = '';
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Super Admin Login (User ID 1)
        if (($username === "asbsuper" || $username === "super_admin_asb") && 
            ($password === "ASB2026" || $password === "ASB_2026")) {
            
            $userId = 1;
            $sessionId = session_id();
            
            logLoginAttempt($pdo, $userId, 'success', $sessionId);
            updateUserLastLogin($pdo, $userId, $ip_address);
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = "Super Administrator";
            $_SESSION['role'] = "Super Admin";
            $_SESSION['is_super_admin'] = 1;
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['ip_address'] = $ip_address;
            
            header("Location: dashboard.php");
            exit;
        }

        // Check for user in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['user_id'] == 1) {
            logLoginAttempt($pdo, 1, 'failed', null);
            $inline_error = "Access Restricted. User ID 1 is protected. Use Super Admin credentials.";
        } 
        elseif ($user && $user['password'] === $password) {
            if ($user['is_active'] == 1) {
                $sessionId = session_id();
                logLoginAttempt($pdo, $user['user_id'], 'success', $sessionId);
                updateUserLastLogin($pdo, $user['user_id'], $ip_address);
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_super_admin'] = 0;
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['ip_address'] = $ip_address;
                
                if (function_exists('getUserTasks')) {
                    $_SESSION['user_tasks'] = getUserTasks($pdo, $user['user_id']);
                }
                
                header("Location: dashboard.php");
                exit;
            } else { 
                logLoginAttempt($pdo, $user['user_id'], 'failed', null);
                $inline_error = "Account Deactivated. Please contact System Administrator."; 
            }
        } else { 
            if ($user && isset($user['user_id'])) {
                logLoginAttempt($pdo, $user['user_id'], 'failed', null);
            } else {
                logLoginAttempt($pdo, 0, 'failed', null);
            }
            $inline_error = "Invalid Credentials. Please check your username and password."; 
        }
    }
    ?>

    <div class="container">
        <div class="login-card">
            <!-- Left Side - Brand -->
            <div class="brand-side">
                <div class="brand-content">
                    <div class="logo">
                        <div class="logo-mark">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                                <rect x="6" y="10" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M10 16L14 20L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="logo-text">
                            <span>ASB</span>
                            <span>FASHION</span>
                        </div>
                    </div>
                    
                    <h1>Quality Control &<br>Return Management</h1>
                    
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-number">98.7%</div>
                            <div class="stat-label">Accuracy</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">500K+</div>
                            <div class="stat-label">Processed</div>
                        </div>
                    </div>
                </div>
                
                <div class="brand-footer">
                    <div class="security">
                        <span class="dot"></span>
                        System Online • Secure
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="form-side">
                <div class="form-container">
                    <div class="form-header">
                        <h2>Welcome back</h2>
                        <p>Sign in to continue to your workspace</p>
                    </div>
                    
                    <?php if($error_message || $inline_error): ?>
                        <div class="error-message">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <span><?= htmlspecialchars($inline_error ?: $error_message) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="login-form">
                        <div class="input-group">
                            <input type="text" id="username" name="username" required autocomplete="off">
                            <label for="username">Username</label>
                        </div>
                        
                        <div class="input-group">
                            <input type="password" id="password" name="password" required>
                            <label for="password">Password</label>
                            <button type="button" class="toggle-pwd" onclick="togglePassword()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="form-options">
                            <label class="checkbox">
                                <input type="checkbox" name="remember">
                                <span class="checkmark"></span>
                                <span>Remember me</span>
                            </label>
                            <a href="#" class="forgot">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="login-btn">
                            <span>Sign In</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/>
                                <polyline points="12 5 19 12 12 19"/>
                            </svg>
                        </button>
                    </form>
                    
                    <div class="form-footer">
                        <div class="divider">
                            <span>Secure Enterprise Login</span>
                        </div>
                        <div class="badges">
                            <span>🔒 256-bit SSL</span>
                            <span>🛡️ ISO Certified</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <span>© <?= date('Y') ?> ASB Fashion | Powered by Vexel IT Solutions v4.0</span>
    </footer>

    <script>
        // Floating labels
        document.querySelectorAll('.input-group input').forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', () => {
                if (!input.value) {
                    input.parentElement.classList.remove('focused');
                }
            });
            if (input.value) {
                input.parentElement.classList.add('focused');
            }
        });
        
        // Toggle password
        function togglePassword() {
            const pwd = document.getElementById('password');
            const btn = document.querySelector('.toggle-pwd');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                btn.classList.add('visible');
            } else {
                pwd.type = 'password';
                btn.classList.remove('visible');
            }
        }
        
        // Form submit animation
        document.querySelector('.login-form')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('.login-btn');
            btn.classList.add('loading');
            btn.innerHTML = '<span>Signing in...</span><div class="spinner"></div>';
        });
        
        // Auto-hide error after 5 seconds
        const errorMsg = document.querySelector('.error-message');
        if (errorMsg) {
            setTimeout(() => {
                errorMsg.style.opacity = '0';
                setTimeout(() => errorMsg.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>