<?php 
require 'db.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Check for any error messages from previous attempts
$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
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
            background: linear-gradient(135deg, #020617 0%, #0f172a 50%, #1e1b4b 100%);
            min-height: 100vh;
            position: relative;
        }
        
        /* Animated background effect */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(220, 38, 38, 0.1) 0%, transparent 50%);
            pointer-events: none;
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        .login-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(220, 38, 38, 0.25);
        }
        
        .input-field {
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
        }
        
        .btn-login {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .glow-effect {
            animation: glow 2s ease-in-out infinite;
        }
        
        @keyframes glow {
            0%, 100% { text-shadow: 0 0 5px rgba(220, 38, 38, 0.5); }
            50% { text-shadow: 0 0 20px rgba(220, 38, 38, 0.8); }
        }
        
        .footer {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .social-link {
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            transform: translateY(-2px);
            color: #ef4444;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-slide-in {
            animation: slideIn 0.6s ease-out;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="animate-slide-in w-full max-w-md">
        <div class="login-card rounded-3xl shadow-2xl p-8 md:p-10">
            <!-- Logo & Brand -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-2xl bg-gradient-to-br from-red-600/20 to-red-600/5 mb-6 shadow-lg">
                    <span class="text-4xl font-black text-red-600">ASB</span>
                </div>
                <h2 class="text-3xl font-black text-white mb-2 tracking-tighter">
                    ASB <span class="text-red-600">FASHION</span>
                </h2>
                <p class="text-slate-400 text-[10px] uppercase tracking-[0.2em] font-bold mt-2">
                    Quality Control & Return Management System
                </p>
                <div class="flex justify-center gap-2 mt-3">
                    <div class="w-1 h-1 rounded-full bg-red-600"></div>
                    <div class="w-1 h-1 rounded-full bg-slate-600"></div>
                    <div class="w-1 h-1 rounded-full bg-red-600"></div>
                </div>
            </div>
            
            <?php if($error_message): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-3 rounded-xl text-sm text-center mb-4 backdrop-blur-sm">
                <span class="font-bold">⚠️ <?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>
            
            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $username = trim($_POST['username']);
                $password = $_POST['password'];

                // Super Admin Login (User ID 1)
                if (($username === "super_admin" || $username === "super_admin_asb") && 
                    ($password === "ASB_Super_2026" || $password === "ASB_Elite_2026")) {
                    
                    $_SESSION['user_id'] = 1;
                    $_SESSION['username'] = "Super Administrator";
                    $_SESSION['role'] = "Super Admin";
                    $_SESSION['is_super_admin'] = 1;
                    $_SESSION['login_time'] = date('Y-m-d H:i:s');
                    
                    // Log super admin access
                    error_log("Super Admin login at " . date('Y-m-d H:i:s'));
                    
                    header("Location: dashboard.php");
                    exit;
                }

                // Check for user_id 1 protection
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && $user['user_id'] == 1) {
                    echo "<div class='bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm text-center mb-4 backdrop-blur-sm'>
                            <span class='font-bold'>🔒 Access Restricted</span><br>
                            User ID 1 is protected. Use Super Admin credentials.
                          </div>";
                } 
                // Standard User Check (Plain Text Comparison)
                elseif ($user && $user['password'] === $password) {
                    if ($user['is_active']) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['is_super_admin'] = 0;
                        
                        // Load user's assigned tasks into session
                        if (function_exists('getUserTasks')) {
                            $_SESSION['user_tasks'] = getUserTasks($pdo, $user['user_id']);
                        }
                        
                        header("Location: dashboard.php");
                        exit;
                    } else { 
                        echo "<div class='bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm text-center mb-4 backdrop-blur-sm'>
                                <span class='font-bold'>⛔ Account Deactivated</span><br>
                                Please contact System Administrator
                              </div>"; 
                    }
                } else { 
                    echo "<div class='bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-xl text-sm text-center mb-4 backdrop-blur-sm'>
                            <span class='font-bold'>❌ Invalid Credentials</span><br>
                            Please check your username and password
                          </div>"; 
                }
            }
            ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="text-slate-400 text-[11px] font-black uppercase tracking-wider block mb-2">
                        Username
                    </label>
                    <input type="text" name="username" 
                           class="input-field w-full bg-slate-800/50 border border-slate-700 rounded-xl p-4 text-white focus:outline-none focus:border-red-600 transition-all" 
                           placeholder="Enter your username" 
                           required
                           autocomplete="off">
                </div>
                
                <div>
                    <label class="text-slate-400 text-[11px] font-black uppercase tracking-wider block mb-2">
                        Password
                    </label>
                    <input type="password" name="password" 
                           class="input-field w-full bg-slate-800/50 border border-slate-700 rounded-xl p-4 text-white focus:outline-none focus:border-red-600 transition-all" 
                           placeholder="Enter your password" 
                           required>
                </div>
                
                <button type="submit" 
                        class="btn-login w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-black py-4 rounded-xl transition-all shadow-lg shadow-red-600/20">
                    <span class="text-[11px] uppercase tracking-widest">🔐 Login to System</span>
                </button>
            </form>
            
            <!-- System Info -->
            <div class="mt-6 pt-4 border-t border-slate-800/50">
                <div class="flex justify-center gap-3 text-[9px] text-slate-600">
                    <span class="flex items-center gap-1">
                        <div class="w-1 h-1 rounded-full bg-green-500"></div>
                        Secure Connection
                    </span>
                    <span class="flex items-center gap-1">
                        <div class="w-1 h-1 rounded-full bg-blue-500"></div>
                        SSL Encrypted
                    </span>
                    <span class="flex items-center gap-1">
                        <div class="w-1 h-1 rounded-full bg-red-500"></div>
                        v4.0 Enterprise
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer fixed bottom-0 left-0 right-0 py-4 text-center">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-3 text-[9px]">
                <div class="flex items-center gap-3">
                    <span class="text-red-500 font-black">ASB FASHION</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-slate-500">QC & Return Management System</span>
                </div>
                
                <div class="flex items-center gap-4">
                    <a href="https://vexelit.xyz" target="_blank" 
                       class="social-link text-slate-500 hover:text-red-500 transition flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4-3-9s1.34-9 3-9m-9 9a9 9 0 019-9"></path>
                        </svg>
                        vexelit.xyz
                    </a>
                    
                    <a href="mailto:vexelit.sl@gmail.com" 
                       class="social-link text-slate-500 hover:text-red-500 transition flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        vexelit.sl@gmail.com
                    </a>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="text-slate-700">Developed by</span>
                    <span class="text-red-500 font-black tracking-wider">VEXEL IT</span>
                    <span class="text-slate-700 text-[8px]">| Kavizz</span>
                </div>
            </div>
            
            <div class="mt-2 text-[7px] text-slate-700">
                &copy; <?= date('Y') ?> Vexel IT Solutions. All rights reserved. | Enterprise Grade System
            </div>
        </div>
    </footer>
    
    <!-- Additional padding for footer -->
    <div class="h-24"></div>
    
    <script>
        // Add floating animation to logo
        const logo = document.querySelector('.inline-flex');
        if (logo) {
            setInterval(() => {
                logo.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    logo.style.transform = 'scale(1)';
                }, 200);
            }, 3000);
        }
        
        // Show welcome message on page load
        window.addEventListener('load', () => {
            console.log('ASB Fashion QC System v4.0 - Secure Login Interface');
        });
        
        // Add input validation
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('scale-[1.01]');
            });
            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('scale-[1.01]');
            });
        });
    </script>
</body>
</html>