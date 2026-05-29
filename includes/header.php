<?php
// This should be at the top of header.php before HTML
$user_tabs = getUserTabs($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>ASB Fashion | <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="icon" type="image/png" href="logo.png">
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
            background: #f0f2f8;
            overflow-x: hidden;
        }

        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ========== SIDEBAR - RED THEME ========== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #991b1b 0%, #7f1d1d 50%, #450a0a 100%);
            color: white;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
        }

        /* Sidebar Header */
        .sidebar-header {
            padding: 28px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            margin-bottom: 10px;
            position: relative;
            flex-shrink: 0;
        }

        .sidebar-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 20%;
            width: 60%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #fbbf24, transparent);
        }

        .sidebar-header h2 {
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff, #fecaca);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .sidebar-header h2 i {
            background: none;
            -webkit-background-clip: unset;
            background-clip: unset;
            color: #fbbf24;
            margin-right: 8px;
        }

        .sidebar-header p {
            font-size: 0.7rem;
            opacity: 0.8;
            letter-spacing: 1px;
            margin-top: 6px;
        }

        /* User Profile */
        .user-profile {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
            margin: 10px 15px;
            border-radius: 20px;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .user-profile:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #7f1a1a;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .user-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .user-info p {
            font-size: 0.7rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Navigation */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 20px 12px;
            min-height: 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
        }

        .nav-section {
            margin-bottom: 25px;
        }

        .nav-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.5);
            padding: 0 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin: 4px 0;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 14px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #fbbf24;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .nav-item:hover::before,
        .nav-item.active::before {
            transform: scaleY(1);
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.12);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .nav-item i {
            width: 24px;
            font-size: 1.1rem;
            text-align: center;
            transition: transform 0.3s;
        }

        .nav-item:hover i {
            transform: scale(1.1);
        }

        .nav-item span {
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Sidebar Footer - Logout */
        .sidebar-footer {
            padding: 20px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(180deg, transparent, rgba(0,0,0,0.2));
            flex-shrink: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 14px;
            transition: all 0.3s;
            background: rgba(220,38,38,0.3);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .logout-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateX(5px);
            border-color: transparent;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 25px 30px;
            min-height: 100vh;
            background: #f0f2f8;
            transition: all 0.3s ease;
        }

        /* Top Bar */
        .top-bar {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 18px 28px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.5);
        }

        .page-title h1 {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #7f1d1d);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .page-title p {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .date-badge {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            padding: 8px 20px;
            border-radius: 40px;
            color: #dc2626;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(220,38,38,0.1);
        }

        /* Mobile Menu Toggle Button */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 200;
            background: #dc2626;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            transition: all 0.3s;
        }

        .mobile-menu-toggle:hover {
            background: #b91c1c;
            transform: scale(1.05);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
            
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }
            
            .top-bar {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .page-title h1 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .top-bar {
                padding: 15px 20px;
            }
            
            .date-badge {
                font-size: 0.7rem;
                padding: 6px 15px;
            }
        }

        /* Footer */
        .footer {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .footer a {
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
        }

        /* Print Styles */
        @media print {
            .sidebar, .mobile-menu-toggle, .sidebar-overlay, .date-badge, .footer {
                display: none;
            }
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            .top-bar {
                background: none;
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-tshirt"></i> ASB</h2>
            <p>QUALITY CONTROL & RETURNS</p>
        </div>
        
        <div class="user-profile">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Guest'); ?></h4>
                <p><i class="fas fa-tag"></i> <?php echo ucfirst($_SESSION['role'] ?? 'Guest'); ?></p>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <?php 
            // Define tab groups with their icons
            $tabGroups = [
                'MAIN' => [
                    'icon' => 'fa-chart-line',
                    'tabs' => ['dashboard', 'users', 'tab_assignments']
                ],
                'QUALITY CONTROL' => [
                    'icon' => 'fa-microscope',
                    'tabs' => ['qc_modes', 'aql', 'inspections']
                ],
                'RETURNS' => [
                    'icon' => 'fa-exchange-alt',
                    'tabs' => ['return_reasons', 'returns']
                ],
                'MANAGEMENT' => [
                    'icon' => 'fa-building',
                    'tabs' => ['suppliers', 'products', 'categories']
                ]
            ];
            
            $current_page = basename($_SERVER['PHP_SELF']);
            
            // Function to render nav items
            function renderNavItems($tabs, $current_page, $user_tabs) {
                foreach ($user_tabs as $tab):
                    if (in_array($tab['tab_name'], $tabs)):
                        $active = ($current_page == $tab['tab_url']) ? 'active' : '';
            ?>
                        <a href="<?php echo $tab['tab_url']; ?>" class="nav-item <?php echo $active; ?>">
                            <i class="<?php echo $tab['tab_icon']; ?>"></i>
                            <span><?php echo htmlspecialchars($tab['show_name']); ?></span>
                        </a>
            <?php 
                    endif;
                endforeach;
            }
            
            // Render grouped sections
            foreach ($tabGroups as $sectionName => $section):
                // Check if any tab in this section exists for the user
                $hasTabs = false;
                foreach ($user_tabs as $tab) {
                    if (in_array($tab['tab_name'], $section['tabs'])) {
                        $hasTabs = true;
                        break;
                    }
                }
                if ($hasTabs):
            ?>
                <div class="nav-section">
                    <div class="nav-section-title">
                        <i class="fas <?php echo $section['icon']; ?>"></i>
                        <?php echo $sectionName; ?>
                    </div>
                    <?php renderNavItems($section['tabs'], $current_page, $user_tabs); ?>
                </div>
            <?php 
                endif;
            endforeach;
            
            // Other tabs not in groups
            $allGroupedTabs = [];
            foreach ($tabGroups as $group) {
                $allGroupedTabs = array_merge($allGroupedTabs, $group['tabs']);
            }
            $other_tabs = array_filter($user_tabs, function($tab) use ($allGroupedTabs) {
                return !in_array($tab['tab_name'], $allGroupedTabs);
            });
            
            if (!empty($other_tabs)):
            ?>
            <div class="nav-section">
                <div class="nav-section-title">
                    <i class="fas fa-ellipsis-h"></i>
                    EXTRA
                </div>
                <?php foreach ($other_tabs as $tab):
                    $active = ($current_page == $tab['tab_url']) ? 'active' : '';
                ?>
                    <a href="<?php echo $tab['tab_url']; ?>" class="nav-item <?php echo $active; ?>">
                        <i class="<?php echo $tab['tab_icon']; ?>"></i>
                        <span><?php echo htmlspecialchars($tab['show_name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </nav>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1><?php echo $page_title ?? 'Dashboard'; ?></h1>
                <p><i class="fas fa-home"></i> Home / <?php echo $page_title ?? 'Dashboard'; ?></p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
            </div>
        </div>
        
        <!-- Mobile Menu JavaScript -->
        <script>
            (function() {
                const sidebar = document.getElementById('sidebar');
                const toggleBtn = document.getElementById('mobileMenuToggle');
                const overlay = document.getElementById('sidebarOverlay');
                
                function closeSidebar() {
                    if (sidebar) sidebar.classList.remove('open');
                    if (overlay) overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
                
                function openSidebar() {
                    if (sidebar) sidebar.classList.add('open');
                    if (overlay) overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
                
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (sidebar && sidebar.classList.contains('open')) {
                            closeSidebar();
                        } else {
                            openSidebar();
                        }
                    });
                }
                
                if (overlay) {
                    overlay.addEventListener('click', closeSidebar);
                }
                
                // Close sidebar on window resize if screen becomes desktop
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 992) {
                        closeSidebar();
                        if (sidebar) sidebar.classList.remove('open');
                        if (overlay) overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
                
                // Close sidebar when a nav link is clicked (mobile)
                const navLinks = document.querySelectorAll('.nav-item');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 992) {
                            closeSidebar();
                        }
                    });
                });
            })();
        </script>