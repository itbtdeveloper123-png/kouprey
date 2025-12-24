<?php
session_start();
// Include database config
require_once '../app/Config/database.php';

// Check if user is logged in (basic check, you can enhance this)
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get dashboard statistics
try {
    // Count products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $productCount = $stmt->fetch()['total'];

    // Count reviews
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
    $reviewCount = $stmt->fetch()['total'];

    // Count admin users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_users WHERE status = 'active'");
    $adminCount = $stmt->fetch()['total'];

    // Count categories
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $categoryCount = $stmt->fetch()['total'];

} catch (PDOException $e) {
    // Handle error gracefully
    $productCount = $reviewCount = $adminCount = $categoryCount = 0;
}

ob_start();
?>

    <div class="dashboard-header">
        <div class="floating-shapes"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 mb-3 fw-bold">
                        <i class="bi bi-speedometer2 me-3"></i>Dashboard
                    </h1>
                    <p class="lead mb-0 opacity-90">Welcome back! Here's an overview of your website management system</p>
                </div>
                <div class="col-lg-4 text-center text-lg-end mt-4 mt-lg-0">
                    <div class="stats-card">
                        <div class="stats-number">
                            <i class="bi bi-graph-up me-2"></i><?php echo $productCount + $reviewCount + $adminCount + $categoryCount; ?>
                        </div>
                        <div class="stats-label">Total Records</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Section -->
    <div class="container mb-5">
        <div class="quick-stats">
            <h3 class="h4 mb-4 text-center">
                <i class="bi bi-bar-chart-line me-2"></i>Quick Statistics
            </h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number text-primary"><?php echo $productCount; ?></div>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-success"><?php echo $reviewCount; ?></div>
                    <div class="stat-label">Reviews</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-info"><?php echo $categoryCount; ?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number text-warning"><?php echo $adminCount; ?></div>
                    <div class="stat-label">Active Admins</div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="text-center mb-5">
            <h2 class="section-title">Content Management</h2>
            <p class="section-subtitle">Easily manage all aspects of your website from this centralized dashboard</p>
        </div>

        <div class="row g-4">
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card admin-card h-100 clickable-card" data-target="#aboutModal">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-primary mx-auto">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                        <h5 class="card-title">About Page</h5>
                        <p class="card-text text-muted grow">Manage about page content and information</p>
                        <button class="btn btn-primary btn-custom mt-auto" data-bs-toggle="modal" data-bs-target="#aboutModal">
                            <i class="bi bi-pencil-square me-1"></i>Manage
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card admin-card h-100 clickable-card" data-target="#featuresModal">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-success mx-auto">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <h5 class="card-title">Features</h5>
                        <p class="card-text text-muted grow">Add and manage website features</p>
                        <button class="btn btn-success btn-custom mt-auto" data-bs-toggle="modal" data-bs-target="#featuresModal">
                            <i class="bi bi-list-check me-1"></i>Manage
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card admin-card h-100 clickable-card" data-target="#productsModal">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-warning mx-auto">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <h5 class="card-title">Products</h5>
                        <p class="card-text text-muted grow">Manage product catalog and inventory</p>
                        <button class="btn btn-warning btn-custom mt-auto" data-bs-toggle="modal" data-bs-target="#productsModal">
                            <i class="bi bi-cart-plus me-1"></i>Manage
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card admin-card h-100 clickable-card" data-target="#reviewsModal">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-info mx-auto">
                            <i class="bi bi-chat-quote-fill"></i>
                        </div>
                        <h5 class="card-title">Reviews</h5>
                        <p class="card-text text-muted grow">Handle customer reviews and testimonials</p>
                        <button class="btn btn-info btn-custom mt-auto" data-bs-toggle="modal" data-bs-target="#reviewsModal">
                            <i class="bi bi-chat-dots me-1"></i>Manage
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card admin-card h-100 clickable-card" data-target="#adminUsersModal">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-secondary mx-auto">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h5 class="card-title">Admin Users</h5>
                        <p class="card-text text-muted grow">Manage admin user accounts</p>
                        <button class="btn btn-secondary btn-custom mt-auto" data-bs-toggle="modal" data-bs-target="#adminUsersModal">
                            <i class="bi bi-people me-1"></i>Manage
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card admin-card h-100 clickable-card" data-target="#settingsModal">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-danger mx-auto">
                            <i class="bi bi-gear-fill"></i>
                        </div>
                        <h5 class="card-title">Settings</h5>
                        <p class="card-text text-muted grow">Configure website settings and preferences</p>
                        <button class="btn btn-danger btn-custom mt-auto" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="bi bi-gear me-1"></i>Configure
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="container mb-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="recent-activity">
                    <h3 class="h4 mb-4">
                        <i class="bi bi-activity me-2"></i>Recent Activity
                    </h3>
                    <div class="activity-item">
                        <div class="activity-icon bg-primary text-white">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Product catalog updated</div>
                            <div class="activity-time">2 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon bg-success text-white">
                            <i class="bi bi-chat-quote"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">New customer review received</div>
                            <div class="activity-time">4 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon bg-info text-white">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Admin user account created</div>
                            <div class="activity-time">1 day ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon bg-warning text-white">
                            <i class="bi bi-gear"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Website settings updated</div>
                            <div class="activity-time">2 days ago</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="admin-card h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <div class="card-icon text-primary mx-auto">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <h5 class="card-title">Quick Tips</h5>
                        <p class="card-text text-muted grow">Keep your content fresh and engaging. Regular updates help maintain user interest and improve SEO rankings.</p>
                        <div class="mt-auto">
                            <small class="text-muted">💡 Pro tip: Use high-quality images for better visual appeal</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Full Screen Modals -->
<!-- About Modal -->
<div class="modal fade fullscreen-modal" id="aboutModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>About Page Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="about.php" class="fullscreen-iframe" onload="resizeIframe(this)"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Features Modal -->
<div class="modal fade fullscreen-modal" id="featuresModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-star me-2"></i>Features Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="features.php" class="fullscreen-iframe" onload="resizeIframe(this)"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Products Modal -->
<div class="modal fade fullscreen-modal" id="productsModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Products Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="products.php" class="fullscreen-iframe" onload="resizeIframe(this)"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Reviews Modal -->
<div class="modal fade fullscreen-modal" id="reviewsModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-quote me-2"></i>Reviews Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="reviews.php" class="fullscreen-iframe" onload="resizeIframe(this)"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Admin Users Modal -->
<div class="modal fade fullscreen-modal" id="adminUsersModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-people me-2"></i>Admin Users Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="admin_users.php" class="fullscreen-iframe" onload="resizeIframe(this)"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade fullscreen-modal" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Settings Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe src="settings.php" class="fullscreen-iframe" onload="resizeIframe(this)"></iframe>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include 'layout.php';
?>

<script>
// Make content management cards clickable
document.addEventListener('DOMContentLoaded', function() {
    const clickableCards = document.querySelectorAll('.clickable-card');
    
    clickableCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't navigate if clicking on a button inside the card
            if (e.target.closest('button')) {
                return;
            }
            
            const target = this.getAttribute('data-target');
            if (target) {
                const modal = new bootstrap.Modal(document.querySelector(target), {
                    backdrop: 'static',
                    keyboard: false,
                    focus: true
                });
                modal.show();
            }
        });
    });

    // Ensure fullscreen modals take full screen
    const fullscreenModals = document.querySelectorAll('.fullscreen-modal');
    fullscreenModals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
            document.body.style.overflow = 'hidden';
            document.body.style.paddingRight = '0';
            // Ensure modal takes full screen
            this.style.position = 'fixed';
            this.style.top = '0';
            this.style.left = '0';
            this.style.width = '100vw';
            this.style.height = '100vh';
            this.style.zIndex = '1055';
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            this.style.position = '';
            this.style.top = '';
            this.style.left = '';
            this.style.width = '';
            this.style.height = '';
            this.style.zIndex = '';
        });
    });
});

// Function to resize iframe based on content
function resizeIframe(iframe) {
    try {
        // Add loaded class to hide loading spinner
        iframe.closest('.modal-body').classList.add('loaded');
        
        // Wait for iframe to load, then resize
        setTimeout(function() {
            if (iframe.contentWindow && iframe.contentWindow.document) {
                const iframeDoc = iframe.contentWindow.document;
                const body = iframeDoc.body;
                const html = iframeDoc.documentElement;
                
                // Calculate the height needed
                const height = Math.max(
                    body.scrollHeight,
                    body.offsetHeight,
                    html.clientHeight,
                    html.scrollHeight,
                    html.offsetHeight
                );
                
                // Set minimum height and add some padding
                iframe.style.height = Math.max(height + 50, 600) + 'px';
            }
        }, 500); // Increased delay to ensure content is fully loaded
    } catch (e) {
        // Fallback height if cross-origin issues
        iframe.closest('.modal-body').classList.add('loaded');
        iframe.style.height = '600px';
    }
}
</script>
