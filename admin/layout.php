<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Basic check: redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Defaults
if (!isset($pageTitle)) $pageTitle = 'Admin Panel';
if (!isset($activeNav)) $activeNav = '';
if (!isset($pageContent)) $pageContent = '';
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Kouprey</title>
    <link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Freeman&display=swap" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark shadow">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-check me-2"></i>Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($activeNav==='dashboard') ? 'active' : ''; ?>" href="index.php"><i class="bi bi-house-door me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-gear me-1"></i>Management</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="about.php"><i class="bi bi-info-circle me-2"></i>About</a></li>
                            <li><a class="dropdown-item" href="features.php"><i class="bi bi-star me-2"></i>Features</a></li>
                            <li><a class="dropdown-item" href="products.php"><i class="bi bi-box me-2"></i>Products</a></li>
                            <li><a class="dropdown-item" href="reviews.php"><i class="bi bi-chat me-2"></i>Reviews</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin_users.php"><i class="bi bi-people me-2"></i>Admin Users</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="welcome-badge me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                    </div>
                    <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <?php echo $pageContent; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hide navbar and other elements when in iframe
        if (window.self !== window.top) {
            document.addEventListener('DOMContentLoaded', function() {
                const navbar = document.querySelector('.navbar');
                if (navbar) navbar.style.display = 'none';
                
                // Adjust body padding
                document.body.style.paddingTop = '0';
            });
        }

        document.querySelectorAll('.admin-card a').forEach(link => {
            link.addEventListener('click', function(e) {
                const btn = this;
                btn.innerHTML = '<i class="bi bi-arrow-repeat me-1 spin"></i>Loading...';
                btn.classList.add('disabled');
            });
        });

        // Shrink navbar on scroll
        let navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', function() {
            if (navbar && window.self === window.top) { // Only shrink if not in iframe
                if (window.scrollY > 50) {
                    navbar.classList.add('shrink');
                } else {
                    navbar.classList.remove('shrink');
                }
            }
        });
    </script>
</body>
</html>
