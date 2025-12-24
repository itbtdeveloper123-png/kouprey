<?php
session_start();
require_once '../app/Config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Check credentials against database
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, status FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'active') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['full_name'];

                // Update last login
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);

                header('Location: index.php');
                exit;
            } else {
                $error = "Account is inactive. Please contact administrator.";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1rem;
        }

        .login-card {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background-color: #f9fafb;
            color: #111827;
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .login-icon {
            font-size: 3rem;
            color: #2563eb;
            margin-bottom: 1rem;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .login-subtitle {
            color: #6b7280;
            margin-bottom: 0;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .input-group-text {
            background-color: #f9fafb;
            border: 1px solid #d1d5db;
            border-right: none;
            color: #374151;
        }

        .form-floating > .form-control {
            border-radius: 0 8px 8px 0;
            border-left: none;
        }

        .btn-login {
            background-color: #2563eb;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .register-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            display: inline-block;
        }

        .register-link:hover {
            background-color: #f3f4f6;
            color: #1d4ed8;
        }

        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card login-card">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h2 class="login-title">Admin Login</h2>
                        <p class="login-subtitle">Please sign in to access the dashboard</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                                        <label for="username">Username</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                        <label for="password">Password</label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                                </button>
                            </div>
                        </form>
                        <div class="text-center">
                            <a href="register.php" class="register-link">
                                <i class="bi bi-person-plus me-1"></i>Don't have an account? Register here
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.classList.add('focused');
            });

            input.addEventListener('blur', function() {
                this.parentElement.parentElement.classList.remove('focused');
            });
        });

        // Add loading state to button
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.btn-login');
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-2 spin"></i>Signing In...';
            btn.disabled = true;
        });
    </script>
</body>
</html>