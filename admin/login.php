<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../app/Config/database.php';

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, status FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'active') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_name'] = $user['full_name'];

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
    <title>Admin Portal - Kouprey</title>
    <link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        /* Abstract Background Elements */
        .bg-shape {
            position: absolute;
            z-index: -1;
            filter: blur(80px);
            opacity: 0.4;
            border-radius: 50%;
        }
        .shape-1 { width: 400px; height: 400px; background: var(--primary); top: -100px; left: -100px; }
        .shape-2 { width: 300px; height: 300px; background: #06b6d4; bottom: -50px; right: -50px; }

        .auth-container {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
            z-index: 10;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand-logo img {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 15px rgba(0,0,0,0.2);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .auth-title {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .form-label {
            color: #e2e8f0;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-wrapper i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            transition: all 0.2s;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 0.875rem 1rem 0.875rem 3.25rem;
            color: #fff;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
            color: #fff;
        }

        .form-control:focus + i {
            color: var(--primary);
        }

        .btn-submit {
            background: var(--primary);
            border: none;
            border-radius: 16px;
            padding: 1rem;
            color: #fff;
            font-weight: 700;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.4);
        }

        .auth-footer {
            margin-top: 2rem;
            text-align: center;
        }

        .register-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .register-link:hover {
            color: var(--primary);
        }

        .alert-premium {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            border-radius: 14px;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-logo">
                <img src="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png" alt="Kouprey">
            </div>
            
            <div class="auth-header">
                <h1 class="auth-title">Admin Access</h1>
                <p class="auth-subtitle">Welcome back! Please sign in.</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-premium d-flex align-items-center" role="alert">
                    <i class="bi bi-shield-exclamation me-2 fs-5"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-wrapper">
                        <input type="text" class="form-control" name="username" placeholder="Enter username" required autocomplete="username">
                        <i class="bi bi-person"></i>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input type="password" class="form-control" name="password" placeholder="••••••••" required autocomplete="current-password">
                        <i class="bi bi-lock"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-submit" id="submitBtn">
                    <span>Sign In to Dashboard</span>
                </button>
            </form>

            <div class="auth-footer">
                <a href="register.php" class="register-link">
                    New administrator? <span class="text-white fw-bold">Request account</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('submitBtn');

        form.addEventListener('submit', () => {
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i> Authenticating...';
            btn.style.opacity = '0.8';
            btn.style.pointerEvents = 'none';
        });
    </script>
</body>
</html>