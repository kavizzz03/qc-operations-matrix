<?php 
require 'db.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Function to check if user is super admin
function isSuperAdmin() {
    return (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) || 
           (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1);
}

// Function to check if user has a specific task
function userHasTask($pdo, $userId, $taskCode) {
    if ($userId == 1) return true;
    $stmt = $pdo->prepare("
        SELECT 1 FROM user_tasks ut 
        INNER JOIN tasks t ON ut.task_id = t.task_id 
        WHERE ut.user_id = ? AND t.task_code = ?
    ");
    $stmt->execute([$userId, $taskCode]);
    return (bool)$stmt->fetch();
}

function getAllTasks($pdo) {
    return $pdo->query("SELECT * FROM tasks WHERE is_active = 1 ORDER BY task_id ASC")->fetchAll();
}

// Permission check
if (!isSuperAdmin() && !userHasTask($pdo, $_SESSION['user_id'], 'USER_MGMT')) {
    die("Access denied. You don't have permission to manage users.");
}

// Handle Add User
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $name = trim($_POST['name']);
    $role = $_POST['role'];
    
    $check = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        $error_msg = "Username already exists!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$username, $password, $name, $role]);
        $new_user_id = $pdo->lastInsertId();
        
        if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
            $assign_stmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
            foreach ($_POST['tasks'] as $task_id) {
                $assign_stmt->execute([$new_user_id, $task_id, $_SESSION['user_id']]);
            }
        }
        
        header("Location: users.php?msg=added");
        exit;
    }
}

// Handle Edit User Tasks
if (isset($_POST['edit_tasks'])) {
    $user_id = $_POST['user_id'];
    
    if ($user_id == 1) {
        $error_msg = "Cannot modify root user tasks!";
    } else {
        $pdo->prepare("DELETE FROM user_tasks WHERE user_id = ?")->execute([$user_id]);
        
        if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
            $assign_stmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
            foreach ($_POST['tasks'] as $task_id) {
                $assign_stmt->execute([$user_id, $task_id, $_SESSION['user_id']]);
            }
        }
        
        header("Location: users.php?msg=updated");
        exit;
    }
}

// Handle Edit User Details
if (isset($_POST['edit_user_details'])) {
    $user_id = $_POST['user_id'];
    
    if ($user_id == 1 && !isSuperAdmin()) {
        $error_msg = "Cannot modify root user!";
    } else {
        $name = trim($_POST['name']);
        $role = $_POST['role'];
        $password = trim($_POST['password']);
        
        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$name, $role, $password, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ? WHERE user_id = ?");
            $stmt->execute([$name, $role, $user_id]);
        }
        
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['username'] = $name;
            $_SESSION['role'] = $role;
        }
        
        header("Location: users.php?msg=details_updated");
        exit;
    }
}

// Handle Status Toggle
if (isset($_GET['toggle']) && $_GET['toggle'] != 1) {
    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
    $stmt->execute([$_GET['toggle']]);
    header("Location: users.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete']) && $_GET['delete'] != 1) {
    $pdo->prepare("DELETE FROM user_tasks WHERE user_id = ?")->execute([$_GET['delete']]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: users.php?msg=deleted");
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY user_id")->fetchAll();
$all_tasks = getAllTasks($pdo);

// Get edit data if edit parameter is set
$edit_user = null;
$edit_user_tasks = [];
if (isset($_GET['edit_user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['edit_user_id']]);
    $edit_user = $stmt->fetch();
    
    $task_stmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
    $task_stmt->execute([$_GET['edit_user_id']]);
    $edit_user_tasks = $task_stmt->fetchAll(PDO::FETCH_COLUMN);
}

$success_msg = '';
$error_msg = $error_msg ?? '';

if (isset($_GET['msg'])) {
    switch($_GET['msg']) {
        case 'added': $success_msg = '✓ User added successfully!'; break;
        case 'updated': $success_msg = '✓ User tasks updated successfully!'; break;
        case 'details_updated': $success_msg = '✓ User details updated successfully!'; break;
        case 'deleted': $success_msg = '✓ User deleted successfully!'; break;
    }
}

$totalUsers = count($users);
$activeUsers = count(array_filter($users, function($u) { return $u['is_active']; }));
$totalTasksAssigned = $pdo->query("SELECT COUNT(*) FROM user_tasks")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>ASB Fashion | User Management</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: #cbd5e1; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #dc2626; border-radius: 10px; }
        .footer-gradient { background: linear-gradient(135deg, #0f172a 0%, #020617 100%); }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .modal-transition { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-[#020617] text-slate-300">
    <div class="flex min-h-screen flex-col">
        <div class="flex flex-1">
            <!-- Sidebar -->
            <aside class="w-72 bg-[#0f172a] border-r border-slate-800 flex flex-col fixed h-full z-40 overflow-y-auto custom-scroll">
                <div class="p-8">
                    <h2 class="text-2xl font-black text-white italic tracking-tighter uppercase">ASB <span class="text-red-600">FASHION</span></h2>
                    <p class="text-[9px] text-slate-500 tracking-[0.3em] font-bold mt-1 uppercase">QC & Return System v4.0</p>
                </div>
                <nav class="flex-1 px-6 space-y-1.5 pb-6">
                    <a href="dashboard.php" class="flex items-center p-4 hover:bg-slate-800/50 rounded-2xl transition group text-slate-500 hover:text-white">
                        <span class="text-[10px] uppercase font-black tracking-widest">📊 Dashboard</span>
                    </a>
                    <a href="users.php" class="flex items-center p-4 bg-red-600/10 text-red-500 rounded-2xl border border-red-600/20 transition">
                        <span class="text-[10px] uppercase font-black tracking-widest">👥 User Management</span>
                    </a>
                    <a href="logout.php" class="flex items-center p-4 hover:bg-slate-800/50 rounded-2xl transition group text-slate-500 hover:text-white mt-4">
                        <span class="text-[10px] uppercase font-black tracking-widest">🔒 Logout</span>
                    </a>
                </nav>
                <div class="p-6 bg-slate-900/50 border-t border-slate-800">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 rounded-xl bg-red-600 flex items-center justify-center text-white font-black italic shadow-lg shadow-red-600/20 uppercase">
                            <?= substr($_SESSION['username'] ?? 'U', 0, 1) ?>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-[10px] font-black text-white uppercase truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></p>
                            <p class="text-[9px] text-red-500 font-bold uppercase italic"><?= htmlspecialchars($_SESSION['role'] ?? 'Standard') ?></p>
                            <?php if(isSuperAdmin()): ?>
                                <span class="text-[8px] bg-red-600/20 text-red-500 px-2 py-0.5 rounded-full mt-1 inline-block">SUPER ADMIN</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 ml-72 p-10">
                <header class="flex justify-between items-start mb-12">
                    <div>
                        <h1 class="text-6xl font-black text-white italic tracking-tighter uppercase leading-none">User <span class="text-red-600">Management</span></h1>
                        <div class="flex items-center gap-4 mt-3">
                            <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.4em] flex items-center">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                Manage system operators and their permissions
                            </p>
                            <a href="dashboard.php" class="text-xs bg-slate-800 hover:bg-slate-700 text-white font-bold py-1 px-3 rounded-xl border border-slate-700 transition flex items-center gap-1.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="bg-slate-900/80 px-10 py-6 rounded-[2.5rem] border border-slate-800 text-right shadow-2xl glass-card">
                        <p class="text-4xl font-black text-red-600 tracking-tighter italic" id="clock">00:00:00</p>
                        <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mt-1"><?= date('D, d M Y') ?></p>
                    </div>
                </header>

                <!-- Action Buttons -->
                <div class="flex justify-between items-center mb-6">
                    <div class="flex gap-3">
                        <a href="?add=1" class="bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider px-6 py-3.5 rounded-xl transition shadow-lg shadow-red-600/10 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add New Operator
                        </a>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                    <div class="stat-card bg-gradient-to-br from-red-600/20 to-transparent p-6 rounded-2xl border border-red-900/30">
                        <p class="text-[10px] font-black text-red-500 uppercase tracking-widest">Total Operators</p>
                        <h3 class="text-3xl font-black text-white mt-2"><?= $totalUsers ?></h3>
                    </div>
                    <div class="stat-card bg-gradient-to-br from-green-600/20 to-transparent p-6 rounded-2xl border border-green-900/30">
                        <p class="text-[10px] font-black text-green-500 uppercase tracking-widest">Active Users</p>
                        <h3 class="text-3xl font-black text-white mt-2"><?= $activeUsers ?></h3>
                    </div>
                    <div class="stat-card bg-gradient-to-br from-blue-600/20 to-transparent p-6 rounded-2xl border border-blue-900/30">
                        <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Total Tasks</p>
                        <h3 class="text-3xl font-black text-white mt-2"><?= count($all_tasks) ?></h3>
                    </div>
                    <div class="stat-card bg-gradient-to-br from-purple-600/20 to-transparent p-6 rounded-2xl border border-purple-900/30">
                        <p class="text-[10px] font-black text-purple-500 uppercase tracking-widest">Tasks Assigned</p>
                        <h3 class="text-3xl font-black text-white mt-2"><?= $totalTasksAssigned ?></h3>
                    </div>
                </div>

                <!-- Messages -->
                <?php if($success_msg): ?>
                    <div class="bg-green-500/10 border border-green-500/20 text-green-500 p-4 rounded-2xl mb-6">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?= $success_msg ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if($error_msg): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-4 rounded-2xl mb-6">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?= $error_msg ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="bg-[#0f172a] rounded-3xl border border-slate-800 overflow-hidden shadow-2xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-900/50 text-slate-500 uppercase text-xs border-b border-slate-800">
                                <tr>
                                    <th class="p-6">Operator</th>
                                    <th class="p-6">Role</th>
                                    <th class="p-6">Assigned Tasks</th>
                                    <th class="p-6">Status</th>
                                    <th class="p-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php foreach($users as $u): ?>
                                <tr class="hover:bg-slate-800/30 transition group">
                                    <td class="p-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600/20 to-red-600/5 flex items-center justify-center text-red-500 font-bold text-lg">
                                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <span class="text-white font-bold block"><?= htmlspecialchars($u['name']) ?></span>
                                                <span class="text-xs text-slate-500">@<?= htmlspecialchars($u['username']) ?></span>
                                                <?php if($u['user_id'] == 1): ?>
                                                    <span class="text-[8px] bg-yellow-600/20 text-yellow-500 px-2 py-0.5 rounded-full ml-2 inline-block">ROOT</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-6">
                                        <span class="bg-slate-800 px-3 py-1.5 rounded-full text-xs font-bold"><?= htmlspecialchars($u['role']) ?></span>
                                    </td>
                                    <td class="p-6">
                                        <?php
                                        $task_stmt = $pdo->prepare("
                                            SELECT t.task_code FROM tasks t
                                            INNER JOIN user_tasks ut ON t.task_id = ut.task_id
                                            WHERE ut.user_id = ?
                                        ");
                                        $task_stmt->execute([$u['user_id']]);
                                        $tasks = $task_stmt->fetchAll();
                                        ?>
                                        <div class="flex flex-wrap gap-1.5">
                                            <?php if(count($tasks) > 0): ?>
                                                <?php foreach($tasks as $task): ?>
                                                    <span class="bg-blue-600/20 text-blue-400 px-2.5 py-1 rounded-lg text-[9px] font-bold uppercase tracking-wider">
                                                        <?= htmlspecialchars($task['task_code']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-slate-600 text-[10px] italic">No tasks assigned</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-6">
                                        <?php if($u['user_id'] == 1): ?>
                                            <span class="text-yellow-500 text-xs font-bold flex items-center gap-1">
                                                <div class="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></div>
                                                PROTECTED
                                            </span>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $u['user_id'] ?>" 
                                               onclick="return confirm('Toggle user status?')"
                                               class="<?= $u['is_active'] ? 'text-green-500' : 'text-red-500' ?> text-xs font-bold underline hover:no-underline flex items-center gap-1">
                                                <div class="w-2 h-2 rounded-full <?= $u['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                                <?= $u['is_active'] ? 'Active' : 'Deactivated' ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-6 text-right space-x-3">
                                        <?php if($u['user_id'] != 1): ?>
                                            <a href="?edit_user_id=<?= $u['user_id'] ?>" class="text-blue-500 hover:text-blue-400 text-xs font-bold uppercase tracking-wider transition">✏️ Edit</a>
                                            <a href="?edit_tasks_id=<?= $u['user_id'] ?>" class="text-purple-500 hover:text-purple-400 text-xs font-bold uppercase tracking-wider transition">📋 Tasks</a>
                                            <a href="?delete=<?= $u['user_id'] ?>" 
                                               onclick="return confirm('⚠️ Delete this user?')" 
                                               class="text-red-500 hover:text-red-400 text-xs font-bold uppercase tracking-wider transition">🗑 Delete</a>
                                        <?php else: ?>
                                            <span class="text-slate-600 text-[10px] uppercase tracking-wider italic">System Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>

        <footer class="footer-gradient border-t border-slate-800 no-print ml-72">
            <div class="max-w-full px-10 py-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-red-600/20 rounded-xl flex items-center justify-center">
                            <span class="text-red-500 font-black text-xs">VX</span>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 font-black uppercase tracking-wider">
                                Developed by <span class="text-red-500">Vexel IT</span> | System Architect: Kavizz
                            </p>
                            <p class="text-[8px] text-slate-600 font-mono mt-1">
                                ASB Fashion Quality Control & Return Management System v4.0
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Add User Modal -->
    <?php if(isset($_GET['add'])): ?>
    <div id="addModal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-50 flex items-center justify-center p-4 modal-transition" style="display: flex;">
        <div class="bg-[#0f172a] border border-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto custom-scroll">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-white">Create System Operator</h3>
                        <p class="text-slate-500 text-xs mt-1">Assign roles and permissions</p>
                    </div>
                    <a href="users.php" class="text-slate-500 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </a>
                </div>
                <form method="POST" class="space-y-5">
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Full Name *</label>
                        <input type="text" name="name" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Username *</label>
                        <input type="text" name="username" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Password *</label>
                        <input type="text" name="password" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Role *</label>
                        <select name="role" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition">
                            <option value="QC Operator">QC Operator</option>
                            <option value="Store Manager">Store Manager</option>
                            <option value="Admin">Admin</option>
                            <option value="Supervisor">Supervisor</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-3">Assign Tasks</label>
                        <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 space-y-2 max-h-60 overflow-y-auto custom-scroll">
                            <?php foreach($all_tasks as $task): ?>
                                <label class="flex items-start gap-3 cursor-pointer hover:bg-slate-800/50 p-3 rounded-lg transition">
                                    <input type="checkbox" name="tasks[]" value="<?= $task['task_id'] ?>" class="w-4 h-4 mt-1 rounded border-slate-600">
                                    <div class="flex-1">
                                        <span class="text-white text-sm font-bold block"><?= htmlspecialchars($task['task_name']) ?></span>
                                        <p class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($task['description'] ?? 'No description') ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_user" class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black text-white transition text-xs uppercase tracking-wider">CREATE OPERATOR</button>
                        <a href="users.php" class="px-8 bg-slate-800 hover:bg-slate-700 rounded-xl text-white transition text-xs font-black uppercase tracking-wider flex items-center justify-center">CANCEL</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit User Details Modal -->
    <?php if(isset($_GET['edit_user_id']) && $edit_user): ?>
    <div id="editDetailsModal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-50 flex items-center justify-center p-4 modal-transition" style="display: flex;">
        <div class="bg-[#0f172a] border border-slate-800 rounded-3xl w-full max-w-md">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-white">Edit User Details</h3>
                    <a href="users.php" class="text-slate-500 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </a>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="user_id" value="<?= $edit_user['user_id'] ?>">
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit_user['name']) ?>" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Role</label>
                        <select name="role" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition">
                            <option value="QC Operator" <?= $edit_user['role'] == 'QC Operator' ? 'selected' : '' ?>>QC Operator</option>
                            <option value="Store Manager" <?= $edit_user['role'] == 'Store Manager' ? 'selected' : '' ?>>Store Manager</option>
                            <option value="Admin" <?= $edit_user['role'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="Supervisor" <?= $edit_user['role'] == 'Supervisor' ? 'selected' : '' ?>>Supervisor</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">New Password (Leave blank to keep current)</label>
                        <input type="text" name="password" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" placeholder="Enter new password">
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="edit_user_details" class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black text-white transition text-xs uppercase tracking-wider">UPDATE USER</button>
                        <a href="users.php" class="px-8 bg-slate-800 hover:bg-slate-700 rounded-xl text-white transition text-xs font-black uppercase tracking-wider flex items-center justify-center">CANCEL</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Tasks Modal -->
    <?php if(isset($_GET['edit_tasks_id'])): 
        $tasks_user_id = (int)$_GET['edit_tasks_id'];
        $tasks_user_stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $tasks_user_stmt->execute([$tasks_user_id]);
        $tasks_user = $tasks_user_stmt->fetch();
        
        $user_tasks_ids = [];
        $task_stmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
        $task_stmt->execute([$tasks_user_id]);
        $user_tasks_ids = $task_stmt->fetchAll(PDO::FETCH_COLUMN);
    ?>
    <div id="editModal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-50 flex items-center justify-center p-4 modal-transition" style="display: flex;">
        <div class="bg-[#0f172a] border border-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto custom-scroll">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-white">Manage User Tasks</h3>
                        <p class="text-slate-500 text-xs mt-1">Editing tasks for: <span class="text-red-500 font-bold"><?= htmlspecialchars($tasks_user['name'] ?? 'User') ?></span></p>
                    </div>
                    <a href="users.php" class="text-slate-500 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </a>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="user_id" value="<?= $tasks_user_id ?>">
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-3">Select Tasks to Assign</label>
                        <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 space-y-2 max-h-60 overflow-y-auto custom-scroll">
                            <?php foreach($all_tasks as $task): ?>
                                <label class="flex items-start gap-3 cursor-pointer hover:bg-slate-800/50 p-3 rounded-lg transition">
                                    <input type="checkbox" name="tasks[]" value="<?= $task['task_id'] ?>" <?= in_array($task['task_id'], $user_tasks_ids) ? 'checked' : '' ?> class="w-4 h-4 mt-1 rounded border-slate-600">
                                    <div class="flex-1">
                                        <span class="text-white text-sm font-bold block"><?= htmlspecialchars($task['task_name']) ?></span>
                                        <p class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($task['description'] ?? 'No description') ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="edit_tasks" class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black text-white transition text-xs uppercase tracking-wider">UPDATE TASKS</button>
                        <a href="users.php" class="px-8 bg-slate-800 hover:bg-slate-700 rounded-xl text-white transition text-xs font-black uppercase tracking-wider flex items-center justify-center">CANCEL</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function updateTime() {
            const options = { timeZone: 'Asia/Colombo', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            document.getElementById('clock').innerText = new Intl.DateTimeFormat('en-GB', options).format(new Date());
        }
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>