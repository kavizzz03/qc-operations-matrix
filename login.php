<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ? AND is_active = 1");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>ASB Fashion | QC & Return Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: float 25s infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            bottom: -300px;
            left: -300px;
            animation: float 20s infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(60px, 60px) rotate(180deg); }
        }

        /* Animated Particles */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            animation: particleFloat 15s infinite;
        }

        @keyframes particleFloat {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            50% { opacity: 0.5; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0,0,0,0.3);
            max-width: 1200px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            animation: slideUp 0.7s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            backdrop-filter: blur(10px);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(60px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Left Panel - Branding */
        .brand-panel {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #1e1b4b 100%);
            padding: 50px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '✧';
            position: absolute;
            font-size: 400px;
            opacity: 0.05;
            bottom: -100px;
            right: -100px;
            font-family: monospace;
            pointer-events: none;
        }

        .brand-panel::after {
            content: '✦';
            position: absolute;
            font-size: 250px;
            opacity: 0.05;
            top: -80px;
            left: -80px;
            font-family: monospace;
            pointer-events: none;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        /* Logo Styles - Replace with your PNG */
        .logo-img {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        /* If you have a PNG logo file, replace the background with background-image */
        .logo-img {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* For actual PNG logo - uncomment and add your logo file */
        /*
        .logo-img {
            background-image: url('assets/images/asb-logo.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background: none;
        }
        .logo-img i {
            display: none;
        }
        */

        .logo-img i {
            font-size: 3.5rem;
            color: white;
        }

        .brand-panel h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff, #e2e8f0);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .brand-panel .tagline {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-bottom: 40px;
            text-align: center;
            letter-spacing: 1px;
        }

        .feature-list {
            list-style: none;
            margin-top: 20px;
        }

        .feature-list li {
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            animation: fadeInLeft 0.5s ease forwards;
            opacity: 0;
        }

        .feature-list li:nth-child(1) { animation-delay: 0.1s; }
        .feature-list li:nth-child(2) { animation-delay: 0.2s; }
        .feature-list li:nth-child(3) { animation-delay: 0.3s; }
        .feature-list li:nth-child(4) { animation-delay: 0.4s; }
        .feature-list li:nth-child(5) { animation-delay: 0.5s; }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .feature-list li i {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .stats-badge {
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 15px;
            margin-top: 30px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stats-badge .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            display: inline-block;
            margin-right: 10px;
        }

        /* Right Panel - Login Form */
        .form-panel {
            padding: 50px 45px;
            background: white;
        }

        .welcome-text {
            margin-bottom: 35px;
            text-align: center;
        }

        .welcome-text h2 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .welcome-text p {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group label i {
            color: #dc2626;
            margin-right: 8px;
        }

        .input-field {
            position: relative;
        }

        .input-field input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .input-field input:focus {
            outline: none;
            border-color: #dc2626;
            background: white;
            box-shadow: 0 0 0 4px rgba(220,38,38,0.1);
        }

        .input-field i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(220,38,38,0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            color: #dc2626;
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            border-left: 4px solid #dc2626;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .demo-card {
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            border-radius: 20px;
            padding: 20px;
            margin-top: 30px;
        }

        .demo-card h4 {
            font-size: 0.8rem;
            color: #374151;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 0.7rem;
        }

        .demo-item {
            background: white;
            padding: 10px 12px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .demo-item:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .demo-item span:first-child {
            font-weight: 700;
            color: #4b5563;
        }

        .demo-item span:last-child {
            color: #dc2626;
            font-family: monospace;
            font-weight: 600;
        }

        .developer-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 0.7rem;
            color: #6b7280;
        }

        .developer-footer a {
            color: #dc2626;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .developer-footer a:hover {
            text-decoration: underline;
        }

        /* Toggle Password Visibility */
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
            transition: all 0.2s;
        }

        .toggle-password:hover {
            color: #dc2626;
        }

        @media (max-width: 992px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            .brand-panel {
                padding: 30px;
                text-align: center;
            }
            .feature-list {
                text-align: left;
                max-width: 300px;
                margin: 0 auto;
            }
            .form-panel {
                padding: 35px;
            }
            .logo-img {
                width: 100px;
                height: 100px;
            }
        }

        @media (max-width: 480px) {
            .demo-grid {
                grid-template-columns: 1fr;
            }
            .form-panel {
                padding: 25px;
            }
            .welcome-text h2 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<!-- Animated Particles -->
<div class="particles" id="particles"></div>

<div class="login-wrapper">
    <div class="login-card">
        <!-- Left Brand Panel -->
        <div class="brand-panel">
            <div class="logo-container">
                <div class="logo-img">
                    <i class="fas fa-tshirt"></i>
                </div>
                <h1>ASB FASHION</h1>
                <div class="tagline">Quality Control & Return Management System</div>
            </div>
            
            <ul class="feature-list">
                <li><i class="fas fa-chart-line"></i> Real-time Quality Tracking</li>
                <li><i class="fas fa-undo-alt"></i> Return Management</li>
                <li><i class="fas fa-building"></i> Supplier Database</li>
                <li><i class="fas fa-clipboard-list"></i> QC Inspection Modes</li>
                <li><i class="fas fa-shield-alt"></i> Role-based Access Control</li>
            </ul>
            
            <div class="stats-badge">
                <i class="fas fa-chart-simple"></i> 
                <span class="stat-number">5000+</span> Returns Processed
            </div>
        </div>
        
        <!-- Right Form Panel -->
        <div class="form-panel">
            <div class="welcome-text">
                <h2>Welcome Back!</h2>
                <p>Sign in to access your dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" required placeholder="Enter your username" autocomplete="off">
                    </div>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" required placeholder="Enter your password">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="demo-card">
                <h4><i class="fas fa-key"></i> Demo Credentials</h4>
                <div class="demo-grid">
                    <div class="demo-item" onclick="fillCredentials('admin', 'admin123')">
                        <span>👑 Admin:</span>
                        <span>admin / admin123</span>
                    </div>
                    <div class="demo-item" onclick="fillCredentials('manager1', 'manager123')">
                        <span>📊 Manager:</span>
                        <span>manager1 / manager123</span>
                    </div>
                    <div class="demo-item" onclick="fillCredentials('user1', 'user123')">
                        <span>👤 User:</span>
                        <span>user1 / user123</span>
                    </div>
                    <div class="demo-item" onclick="fillCredentials('quality_head', 'quality123')">
                        <span>🔍 Quality:</span>
                        <span>quality_head / quality123</span>
                    </div>
                </div>
            </div>
            
            <div class="developer-footer">
                <p>Developed By <strong>Vexel IT</strong> | <i class="fas fa-envelope"></i> <a href="mailto:vexelit.sl@gmail.com">vexelit.sl@gmail.com</a></p>
                <p style="margin-top: 5px; font-size: 0.65rem;">© <?= date('Y') ?> ASB Fashion Group. All Rights Reserved.</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Create animated particles
    function createParticles() {
        const particlesContainer = document.getElementById('particles');
        const particleCount = 50;
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            const size = Math.random() * 6 + 2;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 20 + 's';
            particle.style.animationDuration = Math.random() * 15 + 10 + 's';
            particlesContainer.appendChild(particle);
        }
    }
    
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.toggle-password');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Fill credentials on click
    function fillCredentials(username, password) {
        document.getElementById('username').value = username;
        document.getElementById('password').value = password;
        
        // Highlight effect
        const usernameField = document.getElementById('username');
        const passwordField = document.getElementById('password');
        
        usernameField.style.borderColor = '#10b981';
        passwordField.style.borderColor = '#10b981';
        
        setTimeout(() => {
            usernameField.style.borderColor = '';
            passwordField.style.borderColor = '';
        }, 1000);
    }
    
    // Add loading effect on form submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = document.querySelector('.btn-login');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        btn.disabled = true;
    });
    
    // Initialize particles
    createParticles();
    
    // Add floating animation to logo
    const logoImg = document.querySelector('.logo-img');
    if (logoImg) {
        setInterval(() => {
            logoImg.style.transform = 'scale(1.05)';
            setTimeout(() => {
                logoImg.style.transform = 'scale(1)';
            }, 300);
        }, 3000);
    }
</script>

</body>
</html>