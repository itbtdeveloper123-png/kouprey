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
<html lang="<?php echo htmlspecialchars(getCurrentLanguage()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES); ?></title>
    <?php 
    $logoUrl = getSetting('company_logo'); 
    if (empty($logoUrl)) {
        $logoUrl = getSetting('company_logo', '', 'en');
    }
    ?>
    <link rel="icon" type="image/png" href="<?php echo !empty($logoUrl) ? htmlspecialchars($logoUrl) : 'https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png'; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Freeman&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&display=swap" rel="stylesheet">
    <style>
    /* Global fixes for mobile horizontal overflow */
    html {
        overflow-x: hidden;
        width: 100%;
        position: relative;
    }
    
    body {
        width: 100%;
        position: relative;
    }

    @font-face {
        font-family: 'Superspace Bold';
        src: url('/kouprey/public/fonts/Superspace Bold ver 1.00.ttf') format('truetype');
        font-weight: bold;
        font-style: normal;
    }
    
    .font-freeman {
        font-family: 'Superspace Bold', 'Freeman', serif;
    }

    /* Khmer font: Hanuman for Khmer language and elements with .kh
       Apply only to common text elements to avoid overriding icon fonts (e.g. Font Awesome) */
    :lang(km) :where(h1,h2,h3,h4,h5,h6,p,span,div,li,button,a,label,input,textarea,strong,b,em),
    [lang="km"] :where(h1,h2,h3,h4,h5,h6,p,span,div,li,button,a,label,input,textarea,strong,b,em),
    .kh {
        font-family: 'Hanuman', serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        /* Khmer characters look larger, so we reduce size slightly for app-like look */
        font-size: 0.94em; 
    }

    :lang(km) h1, :lang(km) h2, :lang(km) h3 {
        line-height: 1.4;
    }

    /* utility class to force Hanuman bold weight when needed */
    .kh-700, :lang(km) strong, :lang(km) b {
        font-weight: 700;
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
            :lang(km) {
                font-size: 0.92em;
            }
        }
    }

    /* Future iOS Style - Background */
    body {
        background: #ffffff;
        background-attachment: fixed;
    }

    /* Ultra-Glassmorphism Header */
    header {
        background: rgba(255, 255, 255, 0.4) !important;
        backdrop-filter: blur(30px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(30px) saturate(180%) !important;
        border-bottom: 0.5px solid rgba(255, 255, 255, 0.3) !important;
    }

    header.scrolled {
        background: rgba(255, 255, 255, 0.7) !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04) !important;
        border-bottom-color: rgba(255, 255, 255, 0.5) !important;
    }

    /* iOS 26 Active Menu Style */
    nav a.active-menu {
        background: rgba(0, 0, 0, 0.05);
        backdrop-filter: blur(10px);
        box-shadow: inset 0 0 0 0.5px rgba(0, 0, 0, 0.05);
    }

        /* Scroll effects for Header */
        header.scrolled {
            background-color: rgba(255, 255, 255, 0.85); /* Slightly more opaque on scroll but still glassy */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); /* Stronger shadow on scroll */
            border-bottom-color: rgba(229, 231, 235, 0.8);
            backdrop-blur: 20px;
        }

        @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Scroll effects for Header */
    header.scrolled {
        background-color: rgba(255, 255, 255, 0.98);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        border-bottom-color: rgba(229, 231, 235, 0.5);
    }
    </style>
    <?php
    if ($useOutput) {
        echo '<link rel="stylesheet" href="css/output.css">';
    } else {
        echo '<script src="https://cdn.tailwindcss.com"></script>';
    }
    ?>
    <style>
        /* Ensure anchored sections are not hidden under the fixed header */
        #products { scroll-margin-top: 6rem; }
        @media (max-width: 768px) { #products { scroll-margin-top: 10rem; } }
    </style>
</head>
<body class="text-gray-800 font-freeman min-h-screen pb-20 flex flex-col">
	<header class="px-4 py-4 md:px-6 md:py-3 sticky top-0 z-50 transition-all duration-500">
		<div class="flex items-center justify-between max-w-7xl mx-auto h-full">
			<!-- Logo -->
			<div class="flex items-center">
				<a href="product.php" class="flex items-center transform active:scale-95 transition-transform">
					<?php 
					$logoUrl = getSetting('company_logo'); 
					if (empty($logoUrl)) {
						$logoUrl = getSetting('company_logo', '', 'en');
					}
					?>
					<?php if (!empty($logoUrl)): ?>
						<img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?>" class="h-14 w-auto object-contain" style="height: 56px;">
					<?php endif; ?>
				</a>
			</div>
			
			<!-- Desktop Navigation -->
			<nav class="hidden md:flex items-center space-x-4">
                <a href="product.php#products" class="<?php echo ($current_page == 'product.php' || $current_page == 'product_detail.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_product', 'Product')); ?></a>
				<a href="features.php" class="<?php echo ($current_page == 'features.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></a>
				<a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></a>
				<a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></a>
			</nav>
			
			<!-- Mobile Actions -->
			<div class="flex items-center gap-3">
				<!-- Language Switcher -->
				<button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-full px-4 py-2 transition-all active:scale-95 border border-gray-100 shadow-sm" title="Switch Language">
                    <img src="<?php echo getCurrentLanguage() === 'en' ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80' : 'https://cdn-icons-png.flaticon.com/512/16022/16022033.png'; ?>" 
						 alt="<?php echo getCurrentLanguage() === 'en' ? 'English' : 'Khmer'; ?>" 
						 class="w-6 h-6 object-cover rounded-full shadow-sm">
					<span class="font-bold text-sm text-gray-700"><?php echo getCurrentLanguage() === 'en' ? 'EN' : 'KM'; ?></span>
				</button>
				<button id="searchButton" class="w-11 h-11 flex items-center justify-center text-gray-600 hover:text-white hover:bg-black rounded-full transition-all active:scale-90 bg-gray-50 border border-gray-100 shadow-sm" title="Search">
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
					</svg>
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

	<!-- Mobile Bottom Navigation (iOS 26 Floating Island) -->
	<nav class="md:hidden fixed bottom-6 left-6 right-6 bg-white/40 backdrop-blur-[30px] border border-white/40 shadow-[0_20px_50px_rgba(0,0,0,0.1)] rounded-[2.5rem] z-40 pb-safe px-2 overflow-hidden">
		<div class="flex items-center justify-around py-1">
            <a href="product.php#products" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
				<?php if ($current_page == 'product.php' || $current_page == 'product_detail.php'): ?>
					<div class="absolute inset-x-2 top-2 bottom-2 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]"></div>
				<?php endif; ?>
				<i class="fas fa-mug-hot text-xl mb-1 <?php echo ($current_page == 'product.php' || $current_page == 'product_detail.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
				<span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'product.php' || $current_page == 'product_detail.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_product', 'Products')); ?></span>
			</a>
			
			<a href="features.php" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
				<?php if ($current_page == 'features.php'): ?>
					<div class="absolute inset-x-2 top-2 bottom-2 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]"></div>
				<?php endif; ?>
				<i class="fas fa-bolt text-xl mb-1 <?php echo ($current_page == 'features.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
				<span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'features.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></span>
			</a>

			<a href="reviews.php" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
				<?php if ($current_page == 'reviews.php'): ?>
					<div class="absolute inset-x-2 top-2 bottom-2 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]"></div>
				<?php endif; ?>
				<i class="fas fa-star text-xl mb-1 <?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
				<span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></span>
			</a>

			<a href="about.php" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
				<?php if ($current_page == 'about.php'): ?>
					<div class="absolute inset-x-2 top-2 bottom-2 bg-[#92adc5]/20 rounded-[1.5rem] -z-10 shadow-[inset_0_0_0_1px_rgba(146,173,197,0.2)]"></div>
				<?php endif; ?>
				<i class="fas fa-user text-xl mb-1 <?php echo ($current_page == 'about.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
				<span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'about.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></span>
			</a>
		</div>
	</nav>

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

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (header) {
                if (window.scrollY > 10) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            }
        });

        // If page loaded with a hash (e.g. product.php#products), scroll to it accounting for fixed header
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const hash = window.location.hash;
                if (hash) {
                    const el = document.querySelector(hash);
                    if (el) {
                        const header = document.querySelector('header');
                        const offset = header ? header.offsetHeight + 10 : 80;
                        const top = el.getBoundingClientRect().top + window.pageYOffset - offset;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                    }
                }

                // If clicking an on-page products link while already on product.php, smooth-scroll instead of jumping
                document.querySelectorAll('a[href*="#products"]').forEach(function(a){
                    a.addEventListener('click', function(e){
                        const target = document.querySelector('#products');
                        if (!target) return; // allow normal navigation if not on this page
                        // If link would navigate to the same page, prevent default and smooth scroll
                        const href = a.getAttribute('href');
                        if (href && (href === 'product.php#products' || href.endsWith('#products') )) {
                            if (window.location.pathname.endsWith('product.php') || window.location.pathname === '/' || window.location.pathname.indexOf('product.php') !== -1) {
                                e.preventDefault();
                                const header = document.querySelector('header');
                                const offset = header ? header.offsetHeight + 10 : 80;
                                const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                                window.scrollTo({ top: top, behavior: 'smooth' });
                            }
                        }
                    });
                });
            } catch (err) {
                console.error('Anchor scroll helper error:', err);
            }
        });
    </script>
</body>
</html>
