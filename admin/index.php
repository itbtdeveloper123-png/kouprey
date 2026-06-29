<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get dashboard statistics
try {
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE status = 'active'")->fetchColumn();
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
} catch (PDOException $e) {
    $productCount = $reviewCount = $adminCount = $categoryCount = 0;
}

ob_start();
?>

<div class="row g-4 mb-5">
    <div class="col-12">
        <div class="card border-0 bg-transparent">
            <h2 class="h3 fw-800 text-dark mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>! 👋</h2>
            <p class="text-secondary">Here's a quick overview of what's happening on Kouprey today.</p>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="row g-4 mb-5">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-primary-soft">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <div class="stat-label text-secondary fw-bold small text-uppercase">Total Products</div>
            <div class="stat-value h2 fw-800 mb-1"><?php echo $productCount; ?></div>
            <div class="text-success small"><i class="bi bi-arrow-up me-1"></i>Active Catalog</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-success-soft">
                <i class="bi bi-chat-heart-fill"></i>
            </div>
            <div class="stat-label text-secondary fw-bold small text-uppercase">Reviews</div>
            <div class="stat-value h2 fw-800 mb-1"><?php echo $reviewCount; ?></div>
            <div class="text-primary small">Customer Feedback</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-info-soft">
                <i class="bi bi-tags-fill"></i>
            </div>
            <div class="stat-label text-secondary fw-bold small text-uppercase">Categories</div>
            <div class="stat-value h2 fw-800 mb-1"><?php echo $categoryCount; ?></div>
            <div class="text-info small">Product Groups</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-warning-soft">
                <i class="bi bi-person-badge-fill"></i>
            </div>
            <div class="stat-label text-secondary fw-bold small text-uppercase">Active Admins</div>
            <div class="stat-value h2 fw-800 mb-1"><?php echo $adminCount; ?></div>
            <div class="text-warning small">System Controllers</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Access -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 24px;">
            <h4 class="fw-800 mb-4"><i class="bi bi-rocket-takeoff-fill me-2 text-primary"></i>Quick Actions</h4>
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="products.php" class="text-decoration-none">
                        <div class="p-4 border rounded-4 hover-shadow transition-all bg-light h-100">
                            <i class="bi bi-plus-circle-fill text-primary fs-3 d-block mb-3"></i>
                            <h5 class="text-dark fw-bold">Add New Product</h5>
                            <p class="text-secondary small mb-0">Expand your inventory with new coffee variations</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="settings.php" class="text-decoration-none">
                        <div class="p-4 border rounded-4 hover-shadow transition-all bg-light h-100">
                            <i class="bi bi-gear-wide-connected text-success fs-3 d-block mb-3"></i>
                            <h5 class="text-dark fw-bold">Site Configuration</h5>
                            <p class="text-secondary small mb-0">Update contact info, social links, and system settings</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="reviews.php" class="text-decoration-none">
                        <div class="p-4 border rounded-4 hover-shadow transition-all bg-light h-100">
                            <i class="bi bi-check-all text-info fs-3 d-block mb-3"></i>
                            <h5 class="text-dark fw-bold">Manage Reviews</h5>
                            <p class="text-secondary small mb-0">Approve or hide customer testimonials</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="admin_users.php" class="text-decoration-none">
                        <div class="p-4 border rounded-4 hover-shadow transition-all bg-light h-100">
                            <i class="bi bi-shield-lock-fill text-warning fs-3 d-block mb-3"></i>
                            <h5 class="text-dark fw-bold">Security & Team</h5>
                            <p class="text-secondary small mb-0">Manage roles and permissions for other admins</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div class="col-lg-4">
        <div class="card border-0 bg-dark text-white p-4 h-100 shadow-premium" style="border-radius: 24px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
            <div class="text-center py-4">
                <div class="d-inline-block p-4 rounded-circle bg-white bg-opacity-10 mb-4">
                    <i class="bi bi-cpu fs-1 text-primary-light"></i>
                </div>
                <h4 class="fw-800">System Status</h4>
                <div class="badge bg-success rounded-pill px-3 py-2 mt-2">Server Online</div>
                
                <div class="mt-5 text-start">
                    <div class="d-flex justify-content-between mb-3 border-bottom border-secondary pb-2">
                        <span class="text-secondary">Version</span>
                        <span>v2.0.4-Premium</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-secondary pb-2">
                        <span class="text-secondary">Database</span>
                        <span>MySQL 8.0</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Environment</span>
                        <span class="text-success fw-bold">Production</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-800 { font-weight: 800; }
    .transition-all { transition: all 0.3s ease; }
    .hover-shadow:hover { border-color: var(--primary) !important; transform: translateY(-3px); box-shadow: var(--shadow-premium); background: white !important; }
</style>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include 'layout.php';
?>
