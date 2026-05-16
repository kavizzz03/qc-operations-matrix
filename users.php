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

// Only super admin or users with USER_MGMT task can access
if (!isSuperAdmin() && !userHasTask($pdo, $_SESSION['user_id'], 'USER_MGMT')) {
    die("Access denied. You don't have permission to manage users.");
}

// Handle Add User
if (isset($_POST['add_user'])) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, is_active, is_super_admin) VALUES (?, ?, ?, ?, 1, 0)");
    $stmt->execute([
        $_POST['username'], 
        $_POST['password'], 
        $_POST['name'], 
        $_POST['role']
    ]);
    $new_user_id = $pdo->lastInsertId();
    
    // Assign selected tasks
    if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
        $assign_stmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id, assigned_by) VALUES (?, ?, ?)");
        foreach ($_POST['tasks'] as $task_id) {
            $assign_stmt->execute([$new_user_id, $task_id, $_SESSION['user_id']]);
        }
    }
    
    header("Location: users.php?msg=added");
    exit;
}

// Handle Edit User Tasks
if (isset($_POST['edit_tasks'])) {
    // Remove existing assignments
    $pdo->prepare("DELETE FROM user_tasks WHERE user_id = ?")->execute([$_POST['user_id']]);
    
    // Add new assignments
    if (isset($_POST['tasks']) && is_array($_POST['tasks'])) {
        $assign_stmt = $pdo->prepare("INSERT INTO user_tasks (user_id, task_id, assigned_by) VALUES (?, ?, ?)");
        foreach ($_POST['tasks'] as $task_id) {
            $assign_stmt->execute([$_POST['user_id'], $task_id, $_SESSION['user_id']]);
        }
    }
    
    header("Location: users.php?msg=updated");
    exit;
}

// Handle Status Toggle (Protect user_id 1)
if (isset($_GET['toggle']) && $_GET['toggle'] != 1) {
    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
    $stmt->execute([$_GET['toggle']]);
    header("Location: users.php");
    exit;
}

// Handle Delete (Protect user_id 1)
if (isset($_GET['delete']) && $_GET['delete'] != 1) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: users.php?msg=deleted");
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY user_id")->fetchAll();
$all_tasks = getAllTasks($pdo);

// Get user's assigned tasks for edit modal
$user_tasks = [];
if (isset($_GET['edit_user'])) {
    $stmt = $pdo->prepare("SELECT task_id FROM user_tasks WHERE user_id = ?");
    $stmt->execute([$_GET['edit_user']]);
    $user_tasks = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$success_msg = '';
if (isset($_GET['msg'])) {
    switch($_GET['msg']) {
        case 'added': $success_msg = 'User added successfully!'; break;
        case 'updated': $success_msg = 'User tasks updated successfully!'; break;
        case 'deleted': $success_msg = 'User deleted successfully!'; break;
    }
}

// Get statistics
$totalUsers = count($users);
$activeUsers = count(array_filter($users, function($u) { return $u['is_active']; }));
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
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
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
                        <span class="text-[10px] uppercase font-black tracking-widest">Dashboard</span>
                    </a>
                    <a href="users.php" class="flex items-center p-4 bg-red-600/10 text-red-500 rounded-2xl border border-red-600/20 transition">
                        <span class="text-[10px] uppercase font-black tracking-widest">User Management</span>
                    </a>
                    <a href="logout.php" class="flex items-center p-4 hover:bg-slate-800/50 rounded-2xl transition group text-slate-500 hover:text-white mt-4">
                        <span class="text-[10px] uppercase font-black tracking-widest">Logout</span>
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
                    <a href="logout.php" class="mt-6 text-[9px] font-black uppercase text-slate-600 hover:text-red-500 tracking-tighter block transition">End Secure Session</a>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 ml-72 p-10">
                <header class="flex justify-between items-start mb-12">
                    <div>
                        <h1 class="text-6xl font-black text-white italic tracking-tighter uppercase leading-none">User <span class="text-red-600">Management</span></h1>
                        <p class="text-slate-500 text-[10px] font-black uppercase tracking-[0.4em] mt-3 flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                            Manage system operators and their permissions
                        </p>
                    </div>
                    <div class="bg-slate-900/80 px-10 py-6 rounded-[2.5rem] border border-slate-800 text-right shadow-2xl glass-card">
                        <p class="text-4xl font-black text-red-600 tracking-tighter italic" id="clock">00:00:00</p>
                        <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mt-1"><?= date('D, d M Y') ?></p>
                    </div>
                </header>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
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
                </div>

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
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="p-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600/20 to-red-600/5 flex items-center justify-center text-red-500 font-bold">
                                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <span class="text-white font-bold block"><?= htmlspecialchars($u['name']) ?></span>
                                                <span class="text-xs text-slate-500">@<?= htmlspecialchars($u['username']) ?></span>
                                                <?php if($u['user_id'] == 1): ?>
                                                    <span class="text-[8px] bg-yellow-600/20 text-yellow-500 px-2 py-0.5 rounded-full ml-2">ROOT</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-6">
                                        <span class="bg-slate-800 px-3 py-1 rounded-full text-xs font-bold"><?= htmlspecialchars($u['role']) ?></span>
                                    </td>
                                    <td class="p-6">
                                        <?php
                                        $task_stmt = $pdo->prepare("
                                            SELECT t.task_name, t.task_code FROM tasks t
                                            INNER JOIN user_tasks ut ON t.task_id = ut.task_id
                                            WHERE ut.user_id = ?
                                        ");
                                        $task_stmt->execute([$u['user_id']]);
                                        $tasks = $task_stmt->fetchAll();
                                        ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php if(count($tasks) > 0): ?>
                                                <?php foreach($tasks as $task): ?>
                                                    <span class="bg-blue-600/20 text-blue-400 px-2 py-1 rounded text-[10px] font-bold"><?= htmlspecialchars($task['task_name']) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-slate-600 text-[10px] italic">No tasks assigned</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-6">
                                        <?php if($u['user_id'] == 1): ?>
                                            <span class="text-yellow-500 text-xs font-bold flex items-center gap-1">
                                                <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                                PROTECTED
                                            </span>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $u['user_id'] ?>" class="<?= $u['is_active'] ? 'text-green-500' : 'text-red-500' ?> text-xs font-bold underline hover:no-underline flex items-center gap-1">
                                                <div class="w-2 h-2 rounded-full <?= $u['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                                <?= $u['is_active'] ? 'Active' : 'Deactivated' ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-6 text-right space-x-3">
                                        <?php if($u['user_id'] != 1): ?>
                                            <button onclick="editUserTasks(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['name']) ?>')" 
                                                    class="text-blue-500 hover:text-blue-400 text-xs font-bold uppercase tracking-wider transition">
                                                Edit Tasks
                                            </button>
                                            <a href="?delete=<?= $u['user_id'] ?>" 
                                               onclick="return confirm('⚠️ WARNING: This action cannot be undone!\n\nAre you sure you want to delete this operator?')" 
                                               class="text-red-500 hover:text-red-400 text-xs font-bold uppercase tracking-wider transition">
                                                Delete
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-600 text-[10px] uppercase tracking-wider">System Protected</span>
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

        <!-- Footer -->
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
                    <div class="flex gap-6">
                        <a href="https://vexelit.xyz" target="_blank" class="text-[10px] text-slate-500 hover:text-red-500 transition font-black uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4-3-9s1.34-9 3-9m-9 9a9 9 0 019-9"></path>
                            </svg>
                            vexelit.xyz
                        </a>
                        <a href="mailto:vexelit.sl@gmail.com" class="text-[10px] text-slate-500 hover:text-red-500 transition font-black uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            vexelit.sl@gmail.com
                        </a>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p class="text-[7px] text-slate-700 font-mono tracking-wider">
                        &copy; <?= date('Y') ?> Vexel IT Solutions. All rights reserved. | Secure Enterprise Grade System
                    </p>
                </div>
            </div>
        </footer>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#0f172a] border border-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-white">Create System Operator</h3>
                    <button onclick="closeAddModal()" class="text-slate-500 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Full Name</label>
                        <input type="text" name="name" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Username</label>
                        <input type="text" name="username" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Password (Plain Text)</label>
                        <input type="text" name="password" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition" required>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Role</label>
                        <select name="role" class="w-full bg-slate-900 border border-slate-700 p-4 rounded-xl text-white focus:outline-none focus:border-red-600 transition">
                            <option>QC Operator</option>
                            <option>Store Manager</option>
                            <option>Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Assign Tasks</label>
                        <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 space-y-2 max-h-60 overflow-y-auto custom-scroll">
                            <?php foreach($all_tasks as $task): ?>
                                <label class="flex items-start gap-3 cursor-pointer hover:bg-slate-800 p-3 rounded-lg transition">
                                    <input type="checkbox" name="tasks[]" value="<?= $task['task_id'] ?>" class="w-4 h-4 mt-1 rounded border-slate-600">
                                    <div class="flex-1">
                                        <span class="text-white text-sm font-bold block"><?= htmlspecialchars($task['task_name']) ?></span>
                                        <p class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($task['description']) ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_user" class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black text-white transition text-xs uppercase tracking-wider">CREATE OPERATOR</button>
                        <button type="button" onclick="closeAddModal()" class="px-8 bg-slate-800 hover:bg-slate-700 rounded-xl text-white transition text-xs font-black uppercase tracking-wider">CANCEL</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Tasks Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-50 hidden flex items-center justify-center p-4">
        <div class="bg-[#0f172a] border border-slate-800 rounded-3xl w-full max-w-2xl">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-white">Edit User Tasks</h3>
                    <button onclick="closeEditModal()" class="text-slate-500 hover:text-red-500 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-slate-400 text-sm mb-6" id="editUserName"></p>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div>
                        <label class="text-slate-400 text-xs font-black uppercase tracking-wider block mb-2">Assigned Tasks</label>
                        <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 space-y-2 max-h-60 overflow-y-auto custom-scroll" id="tasksList">
                            <!-- Tasks will be loaded here -->
                        </div>
                    </div>
                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="edit_tasks" class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black text-white transition text-xs uppercase tracking-wider">UPDATE TASKS</button>
                        <button type="button" onclick="closeEditModal()" class="px-8 bg-slate-800 hover:bg-slate-700 rounded-xl text-white transition text-xs font-black uppercase tracking-wider">CANCEL</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Real-time Clock
        function updateTime() {
            const options = { timeZone: 'Asia/Colombo', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            document.getElementById('clock').innerText = new Intl.DateTimeFormat('en-GB', options).format(new Date());
        }
        setInterval(updateTime, 1000);
        updateTime();

        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }
        
        async function editUserTasks(userId, userName) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUserName').innerHTML = `Editing tasks for: <span class="text-red-500 font-bold">${escapeHtml(userName)}</span>`;
            
            // Fetch current user tasks
            try {
                const response = await fetch(`get_user_tasks.php?user_id=${userId}`);
                const data = await response.json();
                
                const tasksList = document.getElementById('tasksList');
                tasksList.innerHTML = '';
                
                if (data.all_tasks && data.all_tasks.length > 0) {
                    data.all_tasks.forEach(task => {
                        const isChecked = data.user_tasks && data.user_tasks.includes(task.task_id);
                        tasksList.innerHTML += `
                            <label class="flex items-start gap-3 cursor-pointer hover:bg-slate-800 p-3 rounded-lg transition">
                                <input type="checkbox" name="tasks[]" value="${task.task_id}" ${isChecked ? 'checked' : ''} class="w-4 h-4 mt-1 rounded border-slate-600">
                                <div class="flex-1">
                                    <span class="text-white text-sm font-bold block">${escapeHtml(task.task_name)}</span>
                                    <p class="text-slate-500 text-xs mt-0.5">${escapeHtml(task.description || 'No description')}</p>
                                </div>
                            </label>
                        `;
                    });
                } else {
                    tasksList.innerHTML = '<p class="text-slate-500 text-center p-4">No tasks available</p>';
                }
                
                document.getElementById('editModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error fetching tasks:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load tasks',
                    background: '#0f172a',
                    color: '#fff'
                });
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (e.target === addModal) closeAddModal();
            if (e.target === editModal) closeEditModal();
        });
    </script>
</body>
</html>