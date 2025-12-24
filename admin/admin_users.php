<?php
session_start();
require_once '../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Handle add admin user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);

    $errors = [];

    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $errors[] = "All fields are required";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    if (empty($errors)) {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $full_name])) {
                $message = "Admin user added successfully!";
            } else {
                $errors[] = "Failed to add admin user";
            }
        }
    }
}

// Handle status change
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $stmt = $pdo->prepare("SELECT status FROM admin_users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE admin_users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        $message = "User status updated successfully!";
    }
}

// Handle delete (only if not current user and not the only admin)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Check if this is the current user
    if ($id == $_SESSION['admin_id']) {
        $error = "You cannot delete your own account!";
    } else {
        // Check if this is the only admin
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users WHERE status = 'active'");
        $result = $stmt->fetch();
        if ($result['count'] <= 1) {
            $error = "Cannot delete the only active admin user!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = "Admin user deleted successfully!";
            } else {
                $error = "Failed to delete admin user";
            }
        }
    }
}

// Fetch all admin users
$stmt = $pdo->query("SELECT * FROM admin_users ORDER BY created_at DESC");
$admin_users = $stmt->fetchAll();

ob_start();
?>

    <style>
        .navbar-brand { font-weight: bold; }
        .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; }
        .form-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; }
        .btn-custom { border-radius: 25px; padding: 0.5rem 1.5rem; font-weight: 500; }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        .status-badge { font-size: 0.75rem; }
    </style>

    <div class="container-fluid px-4 py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-dark">
                            <i class="bi bi-people-fill me-2 text-primary"></i>Manage Admin Users
                        </h1>
                        <p class="text-muted mb-0">Add and manage administrator accounts</p>
                    </div>
                    <div class="bg-light px-3 py-2 rounded">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-people text-primary me-2"></i>
                            <div>
                                <div class="fw-bold text-dark"><?php echo count($admin_users); ?></div>
                                <small class="text-muted">Total Admins</small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-plus me-2"></i>Add New Admin
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label fw-bold">
                                            <i class="bi bi-person-fill me-1 text-primary"></i>Full Name
                                        </label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Enter full name">
                                    </div>
                                    <div class="mb-3">
                                        <label for="username" class="form-label fw-bold">
                                            <i class="bi bi-at me-1 text-primary"></i>Username
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" required placeholder="Choose username">
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-bold">
                                            <i class="bi bi-envelope-fill me-1 text-primary"></i>Email
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" required placeholder="Enter email">
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-bold">
                                            <i class="bi bi-lock-fill me-1 text-primary"></i>Password
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required placeholder="Create password" minlength="6">
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="add_admin" class="btn btn-primary">
                                            <i class="bi bi-plus-lg me-2"></i>Add Admin
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-dark">
                                        <i class="bi bi-list-check me-2"></i>Admin Users
                                    </h5>
                                    <span class="badge bg-primary"><?php echo count($admin_users); ?> users</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($admin_users)): ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-people display-4 mb-3"></i>
                                        <h5>No admin users yet</h5>
                                        <p>Add your first admin user using the form</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th><i class="bi bi-hash me-1"></i>ID</th>
                                                    <th><i class="bi bi-person me-1"></i>Name</th>
                                                    <th><i class="bi bi-at me-1"></i>Username</th>
                                                    <th><i class="bi bi-envelope me-1"></i>Email</th>
                                                    <th><i class="bi bi-calendar me-1"></i>Created</th>
                                                    <th><i class="bi bi-toggle-on me-1"></i>Status</th>
                                                    <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($admin_users as $user): ?>
                                                    <tr>
                                                        <td><?php echo $user['id']; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                            <?php if ($user['id'] == $_SESSION['admin_id']): ?>
                                                                <small class="text-muted">(You)</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($user['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group" role="group">
                                                                <a href="?toggle_status=<?php echo $user['id']; ?>"
                                                                   class="btn btn-sm <?php echo $user['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                                   title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                                    <i class="fa-solid fa-toggle-<?php echo $user['status'] === 'active' ? 'off' : 'on'; ?>"></i>
                                                                </a>
                                                                <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                                                    <a href="?delete=<?php echo $user['id']; ?>"
                                                                       class="btn btn-danger btn-sm"
                                                                       onclick="return confirm('Are you sure you want to delete this admin user?')"
                                                                       title="Delete">
                                                                        <i class="bi bi-trash"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Manage Admin Users';
$activeNav = 'management';
include 'layout.php';
