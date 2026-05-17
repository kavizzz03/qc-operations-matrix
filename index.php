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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>ASB Fashion | QC & Return Management System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: #020617;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .login-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }
        
        .input-field {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }
        
        .glow-title {
            text-shadow: 0 0 30px rgba(220, 38, 38, 0.3);
        }

        .gradient-mesh {
            background-image: 
                radial-gradient(at 0% 0%, rgba(220, 38, 38, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 27, 75, 0.4) 0px, transparent 50%);
        }
    </style>
</head>
<body class="gradient-mesh flex flex-col justify-between min-h-screen">

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
            
            // Log successful login
            logLoginAttempt($pdo, $userId, 'success', $sessionId);
            updateUserLastLogin($pdo, $userId, $ip_address);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = "Super Administrator";
            $_SESSION['role'] = "Super Admin";
            $_SESSION['is_super_admin'] = 1;
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['ip_address'] = $ip_address;
            
            error_log("Super Admin login at " . date('Y-m-d H:i:s') . " from IP: " . $ip_address);
            
            header("Location: dashboard.php");
            exit;
        }

        // Check for user in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['user_id'] == 1) {
            // Log failed attempt for protected account
            logLoginAttempt($pdo, 1, 'failed', null);
            $inline_error = "🔒 Access Restricted. User ID 1 is protected. Use Super Admin credentials.";
        } 
        // Standard User Check (Plain Text Comparison)
        elseif ($user && $user['password'] === $password) {
            if ($user['is_active'] == 1) {
                $sessionId = session_id();
                
                // Log successful login
                logLoginAttempt($pdo, $user['user_id'], 'success', $sessionId);
                updateUserLastLogin($pdo, $user['user_id'], $ip_address);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_super_admin'] = 0;
                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                $_SESSION['ip_address'] = $ip_address;
                
                // Load user tasks
                if (function_exists('getUserTasks')) {
                    $_SESSION['user_tasks'] = getUserTasks($pdo, $user['user_id']);
                }
                
                header("Location: dashboard.php");
                exit;
            } else { 
                // Log failed attempt for inactive account
                logLoginAttempt($pdo, $user['user_id'], 'failed', null);
                $inline_error = "⛔ Account Deactivated. Please contact System Administrator."; 
            }
        } else { 
            // Log failed attempt for invalid credentials
            if ($user && isset($user['user_id'])) {
                logLoginAttempt($pdo, $user['user_id'], 'failed', null);
            } else {
                // Log as user_id 0 for unknown username
                logLoginAttempt($pdo, 0, 'failed', null);
            }
            $inline_error = "❌ Invalid Credentials. Please check your username and password."; 
        }
    }
    ?>

    <div class="flex-1 flex items-center justify-center p-4 md:p-10 lg:p-0">
        <div class="w-full max-w-sm lg:max-w-5xl login-card rounded-[2.5rem] shadow-2xl overflow-hidden grid grid-cols-1 lg:grid-cols-12 min-h-[620px] my-auto">
            
            <!-- DESKTOP VIEW SIDE PANEL -->
            <div class="hidden lg:flex lg:col-span-5 bg-slate-950/60 p-12 flex-col justify-between border-r border-slate-800/60 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-red-600/5 to-transparent pointer-events-none"></div>
                
                <div>
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-red-600/10 border border-red-500/20 mb-8 shadow-inner">
                        <span class="text-2xl font-black text-red-600 tracking-tighter">ASB</span>
                    </div>
                    <h1 class="text-4xl font-black text-white italic tracking-tighter uppercase leading-tight glow-title">
                        ASB <br><span class="text-red-600">FASHION</span>
                    </h1>
                    <p class="text-slate-400 text-[10px] uppercase tracking-[0.25em] font-black mt-4 leading-relaxed">
                        Quality Control & <br>Return Management System
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-4">
                        <p class="text-white text-xs font-bold uppercase mb-1 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                            System Terminal Active
                        </p>
                        <p class="text-[11px] text-slate-500 leading-normal">Enter authorized operational credentials to access your processing workspace dashboard.</p>
                    </div>
                    <div class="flex gap-4 text-[9px] text-slate-600 font-mono">
                        <span>NODE: EX-04</span>
                        <span>SECURE: SSL</span>
                    </div>
                </div>
            </div>

            <!-- LOGIN FORM PANEL -->
            <div class="lg:col-span-7 p-8 md:p-12 lg:p-16 flex flex-col justify-center bg-slate-900/10">
                
                <!-- Mobile View Brand Header -->
                <div class="text-center mb-8 lg:hidden">
                    <h2 class="text-3xl font-black text-white tracking-tighter uppercase">ASB <span class="text-red-600">FASHION</span></h2>
                    <p class="text-slate-500 text-[9px] uppercase tracking-[0.15em] font-bold mt-1">QC & Return Management System</p>
                </div>

                <div class="mb-6">
                    <h3 class="text-xl lg:text-2xl font-black text-white uppercase tracking-tight hidden lg:block">System Login</h3>
                    <p class="text-slate-400 text-xs mt-1 hidden lg:block">Provide system operator metrics below</p>
                </div>

                <!-- Error Messages -->
                <?php if($error_message || $inline_error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-xs font-semibold mb-6 flex items-start gap-2.5 backdrop-blur-md">
                        <span class="mt-0.5">⚠️</span>
                        <div><?= htmlspecialchars($inline_error ?: $error_message) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <div>
                        <label class="text-slate-400 text-[10px] font-black uppercase tracking-widest block mb-2">Username</label>
                        <input type="text" name="username" 
                               class="input-field w-full bg-slate-950/40 border border-slate-800 focus:border-red-600 rounded-xl p-4 text-sm text-white focus:outline-none" 
                               placeholder="Enter operator username" 
                               required
                               autocomplete="off">
                    </div>
                    
                    <div>
                        <label class="text-slate-400 text-[10px] font-black uppercase tracking-widest block mb-2">Password</label>
                        <div class="relative">
                            <input type="password" id="passwordField" name="password" 
                                   class="input-field w-full bg-slate-950/40 border border-slate-800 focus:border-red-600 rounded-xl p-4 pr-12 text-sm text-white focus:outline-none" 
                                   placeholder="Enter security key" 
                                   required>
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-red-500 transition-colors">
                                <svg id="eyeVisibleIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="eyeHiddenIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 014.132-5.4M9.9 4.24a9.122 9.122 0 012.1-.24c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.4M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9.9 9l13.8-13.8"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-black py-4 rounded-xl transition-all shadow-xl shadow-red-600/10 hover:shadow-red-600/20 active:scale-[0.99] mt-2">
                        <span class="text-[10px] font-black uppercase tracking-widest">🔐 Access Secure Dashboard</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="w-full py-6 text-center border-t border-slate-900/60 bg-slate-950/20 backdrop-blur-sm z-10 px-4">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4 text-[9px]">
            <div class="flex items-center gap-3">
                <span class="text-red-500 font-black tracking-wider">ASB FASHION</span>
                <span class="text-slate-800">|</span>
                <span class="text-slate-500">QC & Return Management System v4.0</span>
            </div>
            
            <div class="flex items-center gap-6">
                <a href="https://vexelit.xyz" target="_blank" class="text-slate-500 hover:text-red-500 transition flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4-3-9s1.34-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    vexelit.xyz
                </a>
                <a href="mailto:vexelit.sl@gmail.com" class="text-slate-500 hover:text-red-500 transition flex items-center gap-1.5">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    vexelit.sl@gmail.com
                </a>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="text-slate-600">Engineered by</span>
                <span class="text-red-500 font-black tracking-widest uppercase">Vexel IT</span>
                <span class="text-slate-700 text-[8px]">| Kavizz</span>
            </div>
        </div>
        <div class="text-[8px] text-slate-700 mt-2">
            &copy; <?= date('Y') ?> Vexel IT Solutions. All rights reserved. | Enterprise Architecture Security Verified
        </div>
    </footer>

    <script>
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('passwordField');
            const eyeVisibleIcon = document.getElementById('eyeVisibleIcon');
            const eyeHiddenIcon = document.getElementById('eyeHiddenIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeVisibleIcon.classList.remove('hidden');
                eyeHiddenIcon.classList.add('hidden');
            } else {
                passwordField.type = 'password';
                eyeVisibleIcon.classList.add('hidden');
                eyeHiddenIcon.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>