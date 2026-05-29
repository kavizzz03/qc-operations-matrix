<?php
/**
 * ASB Fashion Quality Control & Return Management System
 * =======================================================
 * Developed By: Vexel IT
 * Email: vexelit.sl@gmail.com
 * Version: 2.0.0
 * 
 * Entry Point - Redirects to login page
 */

session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role'])) {
    // User is logged in - redirect to dashboard
    header('Location: dashboard.php');
    exit();
} else {
    // User is not logged in - redirect to login page
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASB Fashion | Loading...</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loader-container {
            text-align: center;
            color: white;
        }
        .loader {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loader-container h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .loader-container p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="loader-container">
        <div class="loader"></div>
        <h2>ASB Fashion</h2>
        <p>Redirecting to login page...</p>
    </div>
</body>
</html>