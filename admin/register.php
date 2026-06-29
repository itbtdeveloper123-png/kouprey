<?php
session_start();
require_once '../app/Config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);

    $errors = [];

    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }

    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $errors[] = "Username or email already exists";
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $full_name])) {
                $success = "Registration successful! You can now login.";
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Freeman&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Freeman', sans-serif;
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 1rem;
        }

        .register-card {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            max-width: 450px;
            width: 100%;
        }

        .register-header {
            background-color: #f9fafb;
            color: #111827;
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .register-icon {
            font-size: 3rem;
            color: #2563eb;
            margin-bottom: 1rem;
        }

        .register-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .register-subtitle {
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

        .btn-register {
            background-color: #2563eb;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.2s ease;
        }

        .btn-register:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .login-link {
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

        .login-link:hover {
            background-color: #f3f4f6;
            color: #1d4ed8;
        }

        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #059669;
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
                <div class="card register-card">
                    <div class="register-header">
                        <div class="register-icon">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <h2 class="register-title">Admin Registration</h2>
                        <p class="register-subtitle">Create your admin account</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                                <br><a href="login.php" class="alert-link">Click here to login</a>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="full_name" name="full_name"
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" placeholder="Full Name" required>
                                        <label for="full_name">Full Name</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-at"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="username" name="username"
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="Username" required>
                                        <label for="username">Username</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope-fill"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="Email" required>
                                        <label for="email">Email</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required minlength="6">
                                        <label for="password">Password</label>
                                    </div>
                                </div>
                                <div class="password-strength text-muted">
                                    <small>Password must be at least 6 characters</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                        <label for="confirm_password">Confirm Password</label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="bi bi-person-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <a href="login.php" class="login-link">
                                <i class="bi bi-arrow-left me-1"></i>Already have an account? Login here
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const feedback = document.querySelector('.password-strength');

            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
                if (!feedback.querySelector('.text-danger')) {
                    feedback.innerHTML = '<small class="text-danger">Passwords do not match</small>';
                }
            } else {
                this.classList.remove('is-invalid');
                feedback.innerHTML = '<small>Password must be at least 6 characters</small>';
            }
        });

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
            const btn = document.querySelector('.btn-register');
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-2 spin"></i>Creating Account...';
            btn.disabled = true;
        });
    </script>
</body>
</html>