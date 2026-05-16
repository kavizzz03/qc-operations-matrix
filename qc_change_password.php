<?php
/**
 * QC Management System - Standalone Administrative Credential Manager
 * Architecture: Single-Record Enforcement Engine (Plain-Text Storage)
 * Target Table: qc_admins (admin_id, username, pass_key, admin_level)
 */

require 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. SESSION ENFORCEMENT GUARD
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access layer. Please log into the main system portal to initialize session tokens.");
}

$message = '';
$status = ''; // 'success' or 'error'

// 2. FETCH CURRENT ADMINISTRATIVE DATA (Single record row)
try {
    $stmt = $pdo->query("SELECT admin_id, username FROM qc_admins ORDER BY admin_id ASC LIMIT 1");
    $adminRecord = $stmt->fetch();

    if (!$adminRecord) {
        die("System configuration error: Base admin configuration record does not exist inside 'qc_admins'.");
    }

    $targetId = $adminRecord['admin_id'];
    $currentUsername = $adminRecord['username'];

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// 3. PROCESS POST MUTATION REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['new_password'] ?? '';

    if (empty($newPass)) {
        $status = 'error';
        $message = 'The new structural password sequence cannot be blank.';
    } else {
        try {
            // Force-Update target row directly with raw plain-text string
            $updateStmt = $pdo->prepare("UPDATE qc_admins SET pass_key = ? WHERE admin_id = ?");
            
            if ($updateStmt->execute([$newPass, $targetId])) {
                $status = 'success';
                $message = 'Access credentials updated successfully inside the database matrix.';
            } else {
                $status = 'error';
                $message = 'Failed to execute structural query update parameters.';
            }
        } catch (PDOException $e) {
            $status = 'error';
            $message = 'System database runtime processing exception encountered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credential Management Core</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #090d16;
            background-image: radial-gradient(circle at top right, rgba(220, 38, 38, 0.08), transparent 45%);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center font-sans antialiased text-slate-200 p-4">

    <div class="w-full max-w-md bg-slate-900/60 backdrop-blur-xl border border-slate-800/80 rounded-2xl shadow-2xl p-8 relative overflow-hidden">
        
        <!-- Top Tech Indicator Accent -->
        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-red-600 to-transparent"></div>

        <!-- Header Module -->
        <div class="mb-6 text-center">
            <span class="text-[10px] bg-red-950/50 text-red-500 border border-red-900/50 font-black tracking-widest uppercase px-3 py-1 rounded-full inline-block mb-3">
                Security Core Layer
            </span>
            <h1 class="text-xl font-black text-white tracking-tight uppercase">Mutate System Keys</h1>
            <p class="text-xs text-slate-400 mt-1">Single-record structural table execution engine</p>
        </div>

        <!-- Status Notification Feedback -->
        <?php if (!empty($message)): ?>
            <div class="mb-5 p-4 rounded-xl border text-xs font-semibold flex items-center space-x-3 
                <?= $status === 'success' ? 'bg-emerald-950/40 border-emerald-800 text-emerald-400' : 'bg-red-950/40 border-red-900 text-red-400' ?>">
                <div class="h-2 w-2 rounded-full shrink-0 <?= $status === 'success' ? 'bg-emerald-500 animate-pulse' : 'bg-red-500 animate-pulse' ?>"></div>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <!-- Form Controller -->
        <form action="" method="POST" class="space-y-5">
            
            <!-- Read-Only Context Display -->
            <div>
                <label class="text-[9px] uppercase tracking-wider font-black text-slate-400 block mb-1">Target Account Index</label>
                <div class="w-full bg-slate-950 border border-slate-800/60 text-slate-400 rounded-xl px-4 py-3 text-sm font-bold select-none cursor-not-allowed flex items-center justify-between">
                    <span><?= htmlspecialchars($currentUsername) ?></span>
                    <span class="text-[9px] text-slate-600 font-mono tracking-normal uppercase bg-slate-900 px-2 py-0.5 rounded border border-slate-800">Admin Row ID: <?= $targetId ?></span>
                </div>
            </div>

            <!-- New Password Mutation Target Input -->
            <div>
                <label for="new_password" class="text-[9px] uppercase tracking-wider font-black text-slate-300 block mb-1">New Plain-Text Password</label>
                <input 
                    type="text" 
                    id="new_password" 
                    name="new_password" 
                    autocomplete="off"
                    required
                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-slate-600 focus:outline-none focus:border-red-600 focus:ring-1 focus:ring-red-600 transition-all duration-200" 
                    placeholder="Input new structural secret sequence"
                >
                <p class="text-[10px] text-slate-500 mt-1.5 leading-relaxed">
                    Notice: Data will be explicitly stored inside the database matrix as clear unhashed strings.
                </p>
            </div>

            <!-- Action Controls -->
            <div class="pt-2">
                <button 
                    type="submit" 
                    class="w-full bg-red-600 hover:bg-red-700 active:scale-[0.99] text-white text-xs font-black uppercase tracking-wider py-3.5 px-4 rounded-xl shadow-lg shadow-red-950/30 transition-all duration-150 cursor-pointer"
                >
                    Commit Structural Parameters
                </button>
            </div>

        </form>

        <!-- Footer Structural Breadcrumb -->
        <div class="mt-6 pt-4 border-t border-slate-800/40 text-center">
            <a href="dashboard.php" class="text-[10px] text-slate-500 hover:text-slate-300 transition-colors duration-150 uppercase tracking-wider font-bold">
                &larr; Return to Central System Dashboard
            </a>
        </div>

    </div>

</body>
</html>