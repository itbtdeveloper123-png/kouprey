<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (!isset($pageTitle)) $pageTitle = 'Admin Panel';
if (!isset($activeNav)) $activeNav = '';
if (!isset($pageContent)) $pageContent = '';

require_once __DIR__ . '/../app/Config/settings.php';
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Kouprey Admin</title>
    <link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- UI Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Modern Admin Styles -->
    <link href="assets/css/modern-admin.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <link href="assets/css/rte-editor.css" rel="stylesheet">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <div id="admin-wrapper" class="<?php echo ($activeNav === 'settings') ? 'hide-sidebar-layout' : ''; ?>">
        <!-- Persistent Sidebar -->
        <aside id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <?php 
                    $logoUrl = getSetting('company_logo'); 
                    if (empty($logoUrl)) {
                        $logoUrl = getSetting('company_logo', '', 'en');
                    }
                    ?>
                    <?php if (!empty($logoUrl)): ?>
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="img-fluid" style="max-height: 40px;">
                    <?php else: ?>
                        <i class="bi bi-shield-check me-2"></i>KOUPREY
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-label">Main Menu</div>
                <a href="index.php" class="nav-link-item <?php echo ($activeNav==='dashboard') ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2-fill"></i> Dashboard
                </a>
                
                <div class="nav-label mt-4">Content</div>
                <a href="products.php" class="nav-link-item <?php echo ($activeNav==='management' && strpos($_SERVER['PHP_SELF'], 'products.php') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam-fill"></i> Products
                </a>
                <a href="features.php" class="nav-link-item <?php echo (strpos($_SERVER['PHP_SELF'], 'features.php') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-star-fill"></i> Features
                </a>
                <a href="reviews.php" class="nav-link-item <?php echo (strpos($_SERVER['PHP_SELF'], 'reviews.php') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-chat-heart-fill"></i> Reviews
                </a>
                
                <div class="nav-label mt-4">System</div>
                <a href="about.php" class="nav-link-item <?php echo (strpos($_SERVER['PHP_SELF'], 'about.php') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-info-circle-fill"></i> About Content
                </a>
                <a href="admin_users.php" class="nav-link-item <?php echo (strpos($_SERVER['PHP_SELF'], 'admin_users.php') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i> Team Admins
                </a>
                <a href="settings.php" class="nav-link-item <?php echo ($activeNav === 'settings') ? 'active' : ''; ?>">
                    <i class="bi bi-gear-fill"></i> Site Settings
                </a>
                
                <div class="mt-5 px-3">
                    <a href="logout.php" class="btn btn-danger w-100 rounded-pill py-2 shadow-sm">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main id="main-content">
            <!-- Top Navigation Bar -->
            <header id="top-bar">
                <div class="d-flex align-items-center">
                    <button class="btn d-lg-none me-3" id="sidebar-toggle">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div class="breadcrumb-premium d-none d-sm-block">
                        <span class="text-secondary">Admin</span> 
                        <span class="mx-2 text-gray-300">/</span> 
                        <span class="text-dark fw-bold"><?php echo htmlspecialchars($pageTitle); ?></span>
                    </div>

                    <?php if ($activeNav === 'settings'): ?>
                        <a href="index.php" class="btn btn-light rounded-pill px-4 ms-4 shadow-sm fw-bold animate-fade-in" style="border: 1px solid var(--gray-200);">
                            <i class="bi bi-arrow-left me-2"></i> Exit Settings
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <div class="user-profile-dropdown shadow-sm" data-bs-toggle="dropdown">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_name'] ?? 'Admin'); ?>&background=4f46e5&color=fff" 
                                 class="rounded-circle" width="30" height="30">
                            <span class="fw-bold d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                            <i class="bi bi-chevron-down small text-secondary"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 mt-2" style="border-radius: 15px;">
                            <li><a class="dropdown-item rounded-3 py-2" href="settings.php"><i class="bi bi-person me-2"></i> My Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item rounded-3 py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content-container animate-fade-in">
                <?php echo $pageContent; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fallback Tab Switcher - ensures tabs work even if Bootstrap JS fails
        document.addEventListener('DOMContentLoaded', function() {
            var tabButtons = document.querySelectorAll('#settingsTabs [data-bs-toggle="tab"]');
            if (tabButtons.length > 0) {
                tabButtons.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var targetId = this.getAttribute('data-bs-target').replace('#', '');
                        
                        // Deactivate all tabs
                        tabButtons.forEach(function(b) { b.classList.remove('active'); });
                        document.querySelectorAll('#settingsTabContent .tab-pane').forEach(function(p) {
                            p.classList.remove('show', 'active');
                        });
                        
                        // Activate clicked tab
                        this.classList.add('active');
                        var targetPane = document.getElementById(targetId);
                        if (targetPane) {
                            targetPane.classList.add('show', 'active');
                        }
                    });
                });
            }
        });
    </script>
    <script>
        // Sidebar Toggle for Mobile
        $('#sidebar-toggle').on('click', function() {
            $('#sidebar').toggleClass('show');
        });

        // Hide sidebar when clicking outside on mobile
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#sidebar, #sidebar-toggle').length) {
                $('#sidebar').removeClass('show');
            }
        });
    </script>
</body>
</html>
