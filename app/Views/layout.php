<?php
/**
 * Shared layout template
 * Usage (simple):
 *
 * // in a controller or public entry
 * ob_start();
 * include __DIR__ . '/product-content.php'; // page-specific markup (only the inner content)
 * $content = ob_get_clean();
 * $pageTitle = 'Products - KouPrey';
 * include __DIR__ . '/layout.php';
 *
 * Or set $content and $pageTitle then require this file.
 */

session_start();
require_once __DIR__ . '/../Config/settings.php';

// Handle language change via GET parameter (fallback)
if (isset($_GET['set_lang'])) {
    setCurrentLanguage($_GET['set_lang']);
    // Redirect to clean URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle language change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_language'])) {
    setCurrentLanguage($_POST['set_language']);
    // Return a success response for AJAX
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if (!isset($pageTitle)) {
    $pageTitle = 'KouPrey';
}

$cssFile = realpath(__DIR__ . '/../../public/css/output.css');
$useOutput = $cssFile && file_exists($cssFile) && filesize($cssFile) > 50;
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES); ?></title>
    <link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Freeman&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy:wght@400;700&display=swap" rel="stylesheet">
    <style>
    .font-freeman {
        font-family: 'Freeman', serif;
    }

    /* Use Kantumruy from Google Fonts CDN for Khmer text, with sensible fallbacks */
    :lang(km), html[lang="km"] body, .lang-km, .km-text {
        font-family: 'Kantumruy', 'Noto Sans Khmer', 'Khmer OS', 'Khmer OS System', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* Mobile App-like Styles */
    @media (max-width: 768px) {
        /* Full-width sections on mobile */
        section, main {
            margin: 0;
            max-width: none !important;
        }

        /* App-like card shadows */
        .bg-white {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Touch-friendly buttons */
        button {
            min-height: 44px;
            touch-action: manipulation;
        }

        /* App-like spacing */
        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .py-6 {
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }

        /* Mobile navigation safe area */
        .pb-20 {
            padding-bottom: 5rem;
        }
    }

    /* iOS-style status bar spacing */
    @supports (padding-top: env(safe-area-inset-top)) {
        @media (max-width: 768px) {
            body {
                padding-top: env(safe-area-inset-top);
            }
        }
    }

    /* Animated background for body */
    body {
        background: linear-gradient(-45deg, #fef7e0, #f59e0b, #92400e, #d97706);
        background-size: 400% 400%;
        animation: gradientShift 15s ease infinite;
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    </style>
    <?php
    if ($useOutput) {
        echo '<link rel="stylesheet" href="css/output.css">';
    } else {
        echo '<script src="https://cdn.tailwindcss.com"></script>';
    }
    ?>
</head>
<body class="text-gray-800 font-freeman min-h-screen pb-20 flex flex-col">
	<header class="bg-white shadow-sm border-b border-gray-200 px-4 py-3 md:px-6 md:py-4 sticky top-0 z-50">
        <div class="flex items-center justify-between max-w-6xl mx-auto">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="/" class="flex items-center">
                    <img src="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png" alt="<?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?>" class="h-8 w-auto md:h-10">
                    <span class="text-lg md:text-xl font-bold ml-2 text-gray-800">KouPrey</span>
                </a>
            </div>
            
            <!-- Desktop Navigation -->
            <nav class="hidden md:flex space-x-8">
                <a href="product.php" class="text-gray-600 hover:text-gray-900 transition-colors flex items-center">
                    <i class="fas fa-box mr-2"></i><?php echo getSetting('nav_product', 'Products'); ?>
                </a>
                <a href="features.php" class="text-gray-600 hover:text-gray-900 transition-colors flex items-center">
                    <i class="fas fa-star mr-2"></i><?php echo getSetting('nav_features', 'Features'); ?>
                </a>
                <a href="reviews.php" class="text-gray-600 hover:text-gray-900 transition-colors flex items-center">
                    <i class="fas fa-comments mr-2"></i><?php echo getSetting('nav_reviews', 'Reviews'); ?>
                </a>
                <a href="about.php" class="text-gray-600 hover:text-gray-900 transition-colors flex items-center">
                    <i class="fas fa-info-circle mr-2"></i><?php echo getSetting('nav_about', 'About'); ?>
                </a>
                <!-- Language Switcher -->
                <button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center space-x-1 text-sm bg-transparent border-none outline-none cursor-pointer hover:bg-gray-100 rounded px-2 py-1 transition-colors" title="Switch Language">
                    <img src="<?php echo getCurrentLanguage() === 'en' ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80' : 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Flag_of_Cambodia.svg/2560px-Flag_of_Cambodia.svg.png'; ?>" 
                         alt="<?php echo getCurrentLanguage() === 'en' ? 'English' : 'Khmer'; ?>" 
                         class="w-6 h-4 object-cover rounded">
                    <span class="font-medium"><?php echo getCurrentLanguage() === 'en' ? 'EN' : 'KM'; ?></span>
                </button>
            </nav>
            
            <!-- Mobile Actions -->
            <div class="flex items-center space-x-3">
                <!-- Language Switcher -->
                <button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center space-x-1 text-sm bg-transparent border-none outline-none cursor-pointer hover:bg-gray-100 rounded px-2 py-1 transition-colors" title="Switch Language">
                    <img src="<?php echo getCurrentLanguage() === 'en' ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80' : 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Flag_of_Cambodia.svg/2560px-Flag_of_Cambodia.svg.png'; ?>" 
                         alt="<?php echo getCurrentLanguage() === 'en' ? 'English' : 'Khmer'; ?>" 
                         class="w-6 h-4 object-cover rounded">
                    <span class="font-medium"><?php echo getCurrentLanguage() === 'en' ? 'EN' : 'KM'; ?></span>
                </button>
                <button class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full transition-colors" title="Search">
                    <i class="fas fa-search w-5 h-5"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="px-4 py-6 md:px-6 md:py-8">
        <?php
        if (isset($content)) {
            echo $content;
        } else {
            // If $content is not provided, try to include a view file named by $view
            if (isset($view) && is_file(__DIR__ . '/' . $view)) {
                include __DIR__ . '/' . $view;
            } else {
                echo '<p class="text-gray-600">No content available.</p>';
            }
        }
        ?>
    </main>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-40">
        <div class="flex items-center justify-around py-2">
            <a href="product.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 text-gray-600">
                <i class="fas fa-box w-6 h-6 mb-1"></i>
                <span class="text-xs font-medium"><?php echo getSetting('nav_product', 'Products'); ?></span>
            </a>
            <a href="features.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 text-gray-600">
                <i class="fas fa-star w-6 h-6 mb-1"></i>
                <span class="text-xs font-medium"><?php echo getSetting('nav_features', 'Features'); ?></span>
            </a>
            <a href="reviews.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 text-gray-600">
                <i class="fas fa-comments w-6 h-6 mb-1"></i>
                <span class="text-xs font-medium"><?php echo getSetting('nav_reviews', 'Reviews'); ?></span>
            </a>
            <a href="about.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 text-gray-600">
                <i class="fas fa-info-circle w-6 h-6 mb-1"></i>
                <span class="text-xs font-medium"><?php echo getSetting('nav_about', 'About'); ?></span>
            </a>
        </div>
    </nav>

    <!-- Add bottom padding for mobile nav -->
    <style>
        @media (max-width: 768px) {
            body {
                padding-bottom: 80px;
            }
        }
    </style>

    <footer class="mt-auto py-6 bg-gray-100 md:block hidden">
        <div class="max-w-6xl mx-auto px-6 text-center text-sm text-gray-600">© <?php echo date('Y'); ?> KouPrey. All rights reserved.</div>
        <div class="max-w-6xl mx-auto px-6 mt-4 flex justify-center space-x-6">
                <a href="#" class="text-gray-600 hover:text-blue-600 transition-colors"><i class="fab fa-facebook-f text-xl"></i></a>
                <a href="#" class="text-gray-600 hover:text-pink-600 transition-colors"><i class="fab fa-tiktok text-xl"></i></a>
                <a href="#" class="text-gray-600 hover:text-blue-500 transition-colors"><i class="fab fa-telegram-plane text-xl"></i></a>
        </div>
    </footer>

    <?php if (!empty($scripts) && is_array($scripts)) : ?>
        <?php foreach ($scripts as $src) : ?>
            <script src="<?php echo htmlspecialchars($src, ENT_QUOTES); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        function changeLanguage(lang) {
            // Try AJAX first
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'set_language=' + encodeURIComponent(lang)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('AJAX failed, using fallback:', error);
                // Fallback: redirect with GET parameter
                window.location.href = window.location.pathname + '?set_lang=' + encodeURIComponent(lang);
            });
        }

        function toggleLanguage() {
            const current = '<?php echo getCurrentLanguage(); ?>';
            const newLang = current === 'en' ? 'km' : 'en';
            changeLanguage(newLang);
        }
    </script>
</body>
</html>
