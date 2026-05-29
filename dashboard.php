<?php
require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Dashboard';

// Get statistics
$total_suppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$total_reasons = $pdo->query("SELECT COUNT(*) FROM return_reasons")->fetchColumn();
$total_modes = $pdo->query("SELECT COUNT(*) FROM qc_modes")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Get recent activities (last 5 users added)
$recent_users = $pdo->query("SELECT * FROM users ORDER BY user_id DESC LIMIT 5")->fetchAll();

include 'includes/header.php';
?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_suppliers; ?></h3>
            <p>Total Suppliers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_reasons; ?></h3>
            <p>Return Reasons</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
            <i class="fas fa-microscope"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_modes; ?></h3>
            <p>QC Modes</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_users; ?></h3>
            <p>System Users</p>
        </div>
    </div>
</div>

<!-- Welcome Banner -->
<div class="welcome-card">
    <h2><i class="fas fa-waveform"></i> Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
    <p>Welcome to ASB Fashion Quality Control and Return Management System. Use the sidebar menu to navigate through different modules. Monitor your quality metrics and manage returns efficiently.</p>
</div>

<!-- Info Grid -->
<div class="info-grid">
    <!-- Recent Users Card -->
    <div class="info-card">
        <h3><i class="fas fa-user-plus"></i> Recently Added Users</h3>
        <?php if (count($recent_users) > 0): ?>
            <?php foreach ($recent_users as $user): ?>
                <div class="recent-user-item">
                    <div class="user-detail">
                        <i class="fas fa-user-circle"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                            <br>
                            <small><?php echo htmlspecialchars($user['username']); ?></small>
                        </div>
                    </div>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #6b7280; text-align: center; padding: 20px;">No users found</p>
        <?php endif; ?>
    </div>
    
    <!-- System Information Card -->
    <div class="info-card">
        <h3><i class="fas fa-info-circle"></i> System Information</h3>
        <div class="system-info-item">
            <span><i class="fas fa-building"></i> Company</span>
            <span>ASB Fashion</span>
        </div>
        <div class="system-info-item">
            <span><i class="fas fa-code-branch"></i> Version</span>
            <span>2.0.0</span>
        </div>
        <div class="system-info-item">
            <span><i class="fas fa-user-shield"></i> Your Role</span>
            <span><?php echo ucfirst($_SESSION['role']); ?></span>
        </div>
        <div class="system-info-item">
            <span><i class="fas fa-calendar-alt"></i> Login Date</span>
            <span><?php echo date('Y-m-d H:i:s'); ?></span>
        </div>
        <div class="system-info-item">
            <span><i class="fas fa-envelope"></i> Support Email</span>
            <span>vexelit.sl@gmail.com</span>
        </div>
    </div>
    
    <!-- Quick Actions Card -->
    <div class="info-card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="users.php" style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #fef2f2; border-radius: 12px; text-decoration: none; color: #1f2937; transition: all 0.3s;">
                    <i class="fas fa-user-plus" style="color: #dc2626;"></i>
                    <span>Add New User</span>
                </a>
            <?php endif; ?>
            <a href="suppliers.php" style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #fef2f2; border-radius: 12px; text-decoration: none; color: #1f2937; transition: all 0.3s;">
                <i class="fas fa-truck" style="color: #dc2626;"></i>
                <span>Manage Suppliers</span>
            </a>
            <a href="return_reasons.php" style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #fef2f2; border-radius: 12px; text-decoration: none; color: #1f2937; transition: all 0.3s;">
                <i class="fas fa-exchange-alt" style="color: #dc2626;"></i>
                <span>Manage Return Reasons</span>
            </a>
            <a href="qc_modes.php" style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #fef2f2; border-radius: 12px; text-decoration: none; color: #1f2937; transition: all 0.3s;">
                <i class="fas fa-microscope" style="color: #dc2626;"></i>
                <span>Manage QC Modes</span>
            </a>
        </div>
    </div>
</div>

<style>
    /* Dashboard specific styles */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: transform 0.3s, box-shadow 0.3s;
        cursor: pointer;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(220,38,38,0.15);
    }

    .stat-icon {
        width: 65px;
        height: 65px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }

    .stat-info h3 {
        font-size: 2rem;
        font-weight: 700;
        color: #1f2937;
    }

    .stat-info p {
        color: #6b7280;
        font-size: 0.85rem;
    }

    .welcome-card {
        background: linear-gradient(135deg, #dc2626, #991b1b);
        border-radius: 24px;
        padding: 30px;
        margin-bottom: 30px;
        color: white;
        box-shadow: 0 10px 30px rgba(220,38,38,0.2);
    }

    .welcome-card h2 {
        font-size: 1.5rem;
        margin-bottom: 10px;
    }

    .welcome-card p {
        opacity: 0.9;
        font-size: 0.9rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .info-card {
        background: white;
        border-radius: 24px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .info-card h3 {
        font-size: 1.1rem;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #fef2f2;
        color: #1f2937;
    }

    .info-card h3 i {
        color: #dc2626;
        margin-right: 10px;
    }

    .recent-user-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .recent-user-item:last-child {
        border-bottom: none;
    }

    .user-detail {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-detail i {
        width: 35px;
        height: 35px;
        background: #fef2f2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc2626;
    }

    .user-detail strong {
        font-size: 0.9rem;
    }

    .user-detail small {
        font-size: 0.7rem;
        color: #6b7280;
    }

    .role-badge {
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .role-admin {
        background: #dc2626;
        color: white;
    }

    .role-manager {
        background: #f59e0b;
        color: white;
    }

    .role-user {
        background: #6b7280;
        color: white;
    }

    .system-info-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .system-info-item:last-child {
        border-bottom: none;
    }

    .system-info-item span:first-child {
        color: #6b7280;
        font-size: 0.85rem;
    }

    .system-info-item span:last-child {
        font-weight: 600;
        color: #1f2937;
    }

    @media (max-width: 768px) {
        .stats-grid, .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>