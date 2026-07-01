<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';
require_once __DIR__ . '/../Config/visitor_tracker.php';

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

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch reviews from database with product information
$stmt = $pdo->query("
    SELECT r.*, p.name as product_name, p.id as product_id
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    ORDER BY p.name ASC, r.created_at DESC
");
$reviews = $stmt->fetchAll();

// Group reviews by product
$reviewsByProduct = [];
foreach ($reviews as $review) {
    $productId = $review['product_id'];
    if (!isset($reviewsByProduct[$productId])) {
        $reviewsByProduct[$productId] = [
            'product_name' => $review['product_name'] ?: 'Unknown Product',
            'reviews' => []
        ];
    }
    $reviewsByProduct[$productId]['reviews'][] = $review;
}

// Fetch products for search functionality (all languages)
$currentLanguage = getCurrentLanguage();
$productStmt = $pdo->prepare("SELECT * FROM products WHERE enabled = 1 ORDER BY featured DESC, best_seller DESC, id DESC");
$productStmt->execute();
$allProducts = $productStmt->fetchAll();

// Group products by base_product_id
$productsByBaseId = [];
foreach ($allProducts as $product) {
    $baseId = $product['base_product_id'];
    if (!isset($productsByBaseId[$baseId])) {
        $productsByBaseId[$baseId] = [];
    }
    $productsByBaseId[$baseId][$product['language']] = $product;
}

// For display, use current language products
$products = [];
foreach ($productsByBaseId as $baseId => $langVersions) {
    if (isset($langVersions[$currentLanguage])) {
        $products[] = $langVersions[$currentLanguage];
    } elseif (isset($langVersions['en'])) {
        $products[] = $langVersions['en'];
    } else {
        $products[] = reset($langVersions);
    }
}

// Create search index with all language versions
$searchProducts = [];
foreach ($productsByBaseId as $baseId => $langVersions) {
    $searchProduct = [
        'base_product_id' => $baseId,
        'languages' => $langVersions,
        'all_names' => '',
        'all_descriptions' => ''
    ];

    $allNames = [];
    $allDescriptions = [];
    foreach ($langVersions as $lang => $product) {
        $allNames[] = $product['name'];
        $allDescriptions[] = $product['description'];
    }

    $searchProduct['all_names'] = implode(' ', $allNames);
    $searchProduct['all_descriptions'] = implode(' ', $allDescriptions);
    $searchProducts[] = $searchProduct;
}

// Function to generate star rating
function generateStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }
    return $stars;
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(getCurrentLanguage()); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>KouPrey Coffee</title>
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
	<link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
	}

	/* utility class to force Hanuman bold weight when needed */
	.kh-700, :lang(km) strong, :lang(km) b {
		font-weight: 700;
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
	/* Search Modal Styles */
	#searchModal {
		transition: opacity 0.3s ease;
		backdrop-filter: blur(4px);
	}

	#searchModal.hidden {
		opacity: 0;
		pointer-events: none;
	}

	#searchModal:not(.hidden) {
		animation: modalFadeIn 0.3s ease-out;
	}

	#searchModal .modal-content {
		transform: scale(0.95);
		transition: transform 0.3s ease;
		box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
	}

	#searchModal:not(.hidden) .modal-content {
		transform: scale(1);
		animation: modalSlideIn 0.3s ease-out;
	}

	@keyframes modalFadeIn {
		from {
			opacity: 0;
		}
		to {
			opacity: 1;
		}
	}

	@keyframes modalSlideIn {
		from {
			opacity: 0;
			transform: scale(0.95) translateY(-20px);
		}
		to {
			opacity: 1;
			transform: scale(1) translateY(0);
		}
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
		button, .product-button {
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

	/* iOS 18 Style Modal Styles */
	#searchModal {
		backdrop-filter: blur(20px);
		-webkit-backdrop-filter: blur(20px);
		background: rgba(0, 0, 0, 0.4);
	}

	#searchModal .modal-content {
		background: rgba(255, 255, 255, 0.95);
		backdrop-filter: blur(40px);
		-webkit-backdrop-filter: blur(40px);
		border: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 28px;
		box-shadow:
			0 8px 32px rgba(0, 0, 0, 0.12),
			0 2px 8px rgba(0, 0, 0, 0.08),
			inset 0 1px 0 rgba(255, 255, 255, 0.4);
		transform: scale(0.95) translateY(20px);
		transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
	}

	#searchModal:not(.hidden) .modal-content {
		transform: scale(1) translateY(0);
		animation: iosModalSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
	}

	/* iOS 18 Modal Header */
	#searchModal .modal-content > div:first-child {
		border-bottom: 1px solid rgba(0, 0, 0, 0.1);
		background: linear-gradient(180deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 100%);
		backdrop-filter: blur(20px);
		border-radius: 28px 28px 0 0;
		padding: 24px;
	}

	/* iOS 18 Close Button */
	#searchModal button[id*="close"] {
		width: 32px;
		height: 32px;
		border-radius: 50%;
		background: rgba(142, 142, 147, 0.12);
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
		color: #8e8e93;
	}

	#searchModal button[id*="close"]:hover {
		background: rgba(142, 142, 147, 0.24);
		transform: scale(1.05);
	}

	#searchModal button[id*="close"]:active {
		transform: scale(0.95);
		background: rgba(142, 142, 147, 0.36);
	}

	/* iOS 18 Modal Content */
	#searchModal .modal-content > div:not(:first-child) {
		padding: 24px;
	}

	/* iOS 18 Typography */
	#searchModal h3 {
		font-weight: 600;
		font-size: 20px;
		color: #1c1c1e;
		margin: 0;
		font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
	}

	/* iOS-style status bar spacing */
	@supports (padding-top: env(safe-area-inset-top)) {
		@media (max-width: 768px) {
			body {
				padding-top: env(safe-area-inset-top);
			}
		}
	}

	/* iOS 18 Animation */
	@keyframes iosModalSlideIn {
		from {
			opacity: 0;
			transform: scale(0.9) translateY(40px);
		}
		to {
			opacity: 1;
			transform: scale(1) translateY(0);
		}
	}

	/* iOS 18 Mobile Optimizations */
	@media (max-width: 768px) {
		#searchModal .modal-content {
			margin: 16px;
			max-width: calc(100vw - 32px);
			max-height: calc(100vh - 32px);
			border-radius: 24px;
		}

		#searchModal .modal-content > div:first-child {
			padding: 20px;
			border-radius: 24px 24px 0 0;
		}

		#searchModal .modal-content > div:not(:first-child) {
			padding: 20px;
		}
	}

	/* Safe area padding for bottom nav */
	.pb-safe {
		padding-bottom: 20px;
		padding-bottom: env(safe-area-inset-bottom, 20px);
	}
	</style>
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		function changeLanguage(lang) {
			// Send POST request to set language
			fetch(window.location.href, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: 'set_language=' + encodeURIComponent(lang)
			}).then(() => {
				// Reload the page
				window.location.reload();
			});
		}
	</script>
</head>
<body class="bg-white text-gray-800 font-freeman min-h-screen pb-20 flex flex-col">
	<header class="bg-white/70 backdrop-blur-xl shadow-sm border-b border-white/20 px-4 py-4 md:px-6 md:py-5 sticky top-0 z-50 transition-all duration-300">
		<div class="flex items-center justify-between max-w-6xl mx-auto h-full">
			<!-- Logo -->
			<div class="flex items-center">
				<a href="product.php#products" class="flex items-center transform active:scale-95 transition-transform">
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

	<main class="max-w-6xl mx-auto px-4 md:px-6 py-12 md:py-20">
        <div class="text-center mb-16" data-aos="fade-up">
            <h1 class="text-4xl md:text-6xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent mb-4">
                <?php echo htmlspecialchars(getSetting('reviews_title', 'Customer Reviews')); ?>
            </h1>
            <div class="w-24 h-1.5 bg-gradient-to-r from-orange-400 to-orange-600 mx-auto rounded-full mb-6"></div>
            <p class="text-gray-500 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
                <?php echo htmlspecialchars(getSetting('reviews_description', 'Discover why coffee lovers across the country choose Kouprey for their daily caffeine ritual.')); ?>
            </p>
        </div>

		<div class="space-y-16">
			<?php if (!empty($reviewsByProduct)): ?>
				<?php foreach ($reviewsByProduct as $productId => $productData): ?>
					<div class="product-reviews-section" data-aos="fade-up">
						<div class="product-group-header p-6 rounded-2xl mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm border border-orange-100/30">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($productData['product_name']); ?></h3>
                                <p class="text-gray-400 text-sm mt-1"><?php echo count($productData['reviews']); ?> total reviews for this blend</p>
                            </div>
                            <a href="product_detail.php?base_id=<?php echo $productId; ?>" class="text-orange-600 font-bold text-sm hover:text-orange-700 flex items-center gap-2 group">
                                View Product <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>

						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<?php foreach ($productData['reviews'] as $review): ?>
								<div class="review-card p-8 flex flex-col h-full">
									<div class="flex items-center justify-between mb-6">
										<div class="flex items-center gap-4">
											<div class="w-12 h-12 bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl flex items-center justify-center text-orange-600 font-bold shadow-inner">
												<?php echo strtoupper(substr($review['name'], 0, 1)); ?>
											</div>
											<div>
												<div class="flex items-center gap-2">
                                                    <h4 class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($review['name']); ?></h4>
                                                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                                </div>
												<div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest"><?php echo date('M d, Y', strtotime($review['created_at'] ?? 'now')); ?></div>
											</div>
										</div>
                                        <div class="flex text-yellow-400 text-xs">
                                            <?php 
                                            for($i=1; $i<=5; $i++) {
                                                echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-gray-200"></i>';
                                            }
                                            ?>
                                        </div>
									</div>
									<p class="text-gray-600 leading-relaxed italic relative">
                                        <i class="fas fa-quote-left absolute -left-4 -top-2 text-orange-100 text-3xl -z-10 opacity-30"></i>
                                        <?php echo htmlspecialchars($review['review']); ?>
                                    </p>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else: ?>
                <div class="text-center py-32 bg-gray-50 rounded-3xl border-2 border-dashed border-gray-200">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                        <i class="fas fa-mug-hot text-gray-300 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">No reviews found</h3>
                    <p class="text-gray-500 mt-2">Check back soon for new feedback from our customers.</p>
                </div>
			<?php endif; ?>
		</div>
	</main>

	<footer class="mt-auto bg-gray-900 text-white py-12">
		<div class="max-w-6xl mx-auto px-4 md:px-6">
			<div class="grid grid-cols-1 md:grid-cols-4 gap-8">
				<!-- Company Info -->
				<div class="col-span-1 md:col-span-2">
					<div class="flex items-center mb-4">
						<img src="https://i.ibb.co/gLZY6fQr/Untitled-1-Recovered.png" alt="KouPrey Logo" class="h-8 w-auto mr-3">
						<span class="text-xl font-bold text-yellow-400"><?php echo htmlspecialchars(getSetting('company_name', 'KouPrey Coffee')); ?></span>
					</div>
					<p class="text-gray-300 mb-4 leading-relaxed">
						<?php echo htmlspecialchars(getSetting('site_description', 'Premium coffee beans and sustainable brewing solutions')); ?>
					</p>
					<div class="space-y-2">
						<div class="flex items-start">
							<i class="fas fa-map-marker-alt text-yellow-400 mt-1 mr-3"></i>
							<span class="text-gray-300"><?php echo nl2br(htmlspecialchars(getSetting('company_address', 'Phnom Penh, Cambodia'))); ?></span>
						</div>
						<div class="flex items-center">
							<i class="fas fa-phone text-yellow-400 mr-3"></i>
							<span class="text-gray-300"><?php echo htmlspecialchars(getSetting('company_phone', '+855 12 345 678')); ?></span>
						</div>
						<div class="flex items-center">
							<i class="fas fa-envelope text-yellow-400 mr-3"></i>
							<span class="text-gray-300"><?php echo htmlspecialchars(getSetting('company_email', 'info@kouprey.com')); ?></span>
						</div>
					</div>
				</div>

				<!-- Quick Links -->
				<div>
					<h3 class="text-lg font-semibold mb-4 text-white"><?php echo htmlspecialchars(getSetting('footer_quick_links', 'Quick Links')); ?></h3>
					<ul class="space-y-2">
						<li><a href="/kouprey/public/" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_home', 'Home')); ?></a></li>
						<li><a href="/kouprey/public/product.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_products', 'Products')); ?></a></li>
						<li><a href="/kouprey/public/about.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_about_us', 'About Us')); ?></a></li>
						<li><a href="/kouprey/public/reviews.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_reviews', 'Reviews')); ?></a></li>
						<li><a href="/kouprey/admin/login.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_admin', 'Admin')); ?></a></li>
					</ul>
				</div>

				<!-- Social & Newsletter -->
				<div>
					<h3 class="text-lg font-semibold mb-4 text-white"><?php echo getSetting('social_banner_text', 'Connect With Us'); ?></h3>
					<div class="flex space-x-4 mb-6">
						<?php if (getSetting('enable_social_links', '1') === '1'): ?>
							<?php if (getSetting('social_facebook')): ?>
								<a href="<?php echo htmlspecialchars(getSetting('social_facebook')); ?>" target="_blank" class="text-gray-300 hover:text-blue-400 transition-colors">
									<i class="fab fa-facebook-f text-xl"></i>
								</a>
							<?php endif; ?>
							<?php if (getSetting('social_instagram')): ?>
								<a href="<?php echo htmlspecialchars(getSetting('social_instagram')); ?>" target="_blank" class="text-gray-300 hover:text-pink-400 transition-colors">
									<i class="fab fa-instagram text-xl"></i>
								</a>
							<?php endif; ?>
							<?php if (getSetting('social_tiktok')): ?><a href="<?php echo htmlspecialchars(getSetting('social_tiktok')); ?>" target="_blank" class="text-gray-300 hover:text-pink-400 transition-colors">
								<i class="fab fa-tiktok text-xl"></i>
							</a><?php endif; ?>
							<?php if (getSetting('social_telegram')): ?><a href="<?php echo htmlspecialchars(getSetting('social_telegram')); ?>" target="_blank" class="text-gray-300 hover:text-blue-500 transition-colors">
								<i class="fab fa-telegram-plane text-xl"></i>
							</a><?php endif; ?>
						<?php endif; ?>
					</div>

					<?php if (getSetting('enable_newsletter', '1') === '1'): ?>
						<div>
							<p class="text-gray-300 text-sm mb-2"><?php echo htmlspecialchars(getSetting('footer_stay_updated', 'Stay Updated')); ?></p>
							<div class="flex">
								<input type="email" placeholder="<?php echo htmlspecialchars(getSetting('footer_enter_email', 'Enter your email')); ?>" class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:border-yellow-400">
								<button class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-black font-medium rounded-r-lg transition-colors">
									<i class="fas fa-paper-plane"></i>
								</button>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Bottom Bar -->
			<div class="border-t border-gray-800 mt-8 pt-6 flex flex-col md:flex-row justify-between items-center">
				<p class="text-gray-400 text-sm"><?php echo htmlspecialchars(getSetting('footer_text', '© ' . date('Y') . ' KouPrey. All rights reserved.')); ?></p>
				<div class="flex space-x-6 mt-4 md:mt-0">
				<a href="privacy_policy.php" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_privacy_policy', 'Privacy Policy')); ?></a>
					<a href="terms_of_service.php" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_terms_of_service', 'Terms of Service')); ?></a>
					<a href="#" onclick="openContactModal()" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_contact_us', 'Contact Us')); ?></a>
				</div>
			</div>
		</div>
	</footer>

	<!-- Search Modal -->
	<div id="searchModal" class="fixed inset-0 z-50 hidden">
		<div class="flex items-center justify-center min-h-screen p-4">
			<div class="modal-content bg-white max-w-2xl w-full max-h-[90vh] overflow-hidden relative">
				<!-- Search Header -->
				<div class="flex items-center justify-between">
					<h3 class="text-2xl font-bold text-gray-800 flex items-center">
						<i class="fas fa-search text-blue-500 mr-2"></i>Search Products
					</h3>
					<button id="closeSearchModal" class="text-gray-400 hover:text-gray-600 transition-colors">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>

				<!-- Search Input -->
				<div class="border-b border-gray-200">
					<div class="relative">
						<input type="text" id="searchInput" placeholder="Search for products..." class="w-full px-4 py-3 pl-12 border-0 focus:outline-none focus:ring-0 text-lg">
						<svg class="w-5 h-5 absolute left-3 top-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
						</svg>
					</div>
				</div>

				<!-- Search Results -->
				<div class="overflow-y-auto max-h-[calc(90vh-200px)]">
					<div id="searchResults" class="space-y-4">
						<!-- Results will be populated here -->
					</div>
					<div id="noResults" class="text-center text-gray-500 hidden py-8">
						<p class="text-lg">No products found matching your search.</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Embed product data for JavaScript -->
	<script>
		const productsData = <?php echo json_encode($products); ?>;
		const searchProductsData = <?php echo json_encode($searchProducts); ?>;
	</script>

	<script>
		// Search Modal functionality
		const searchModal = document.getElementById('searchModal');
		const searchButton = document.getElementById('searchButton');
		const closeSearchModalBtn = document.getElementById('closeSearchModal');
		const searchInput = document.getElementById('searchInput');
		const searchResults = document.getElementById('searchResults');
		const noResults = document.getElementById('noResults');

		// Function to show search modal
		function showSearchModal() {
			searchModal.classList.remove('hidden');
			document.body.style.overflow = 'hidden';
			searchInput.focus();
		}

		// Function to hide search modal
		function hideSearchModal() {
			searchModal.classList.add('hidden');
			document.body.style.overflow = 'auto';
			searchInput.value = '';
			searchResults.innerHTML = '';
			noResults.classList.add('hidden');
		}

		// Function to detect if text contains Khmer characters
		function containsKhmer(text) {
			const khmerRegex = /[\u1780-\u17FF\u19E0-\u19FF\u1A00-\u1A1F]/;
			return khmerRegex.test(text);
		}

		// Function to get appropriate language version for display
		function getDisplayProduct(searchProduct, query) {
			const isKhmerQuery = containsKhmer(query);
			const preferredLang = isKhmerQuery ? 'km' : 'en';

			if (searchProduct.languages && searchProduct.languages[preferredLang]) {
				return searchProduct.languages[preferredLang];
			}

			const currentLang = '<?php echo $currentLanguage; ?>';
			if (searchProduct.languages && searchProduct.languages[currentLang]) {
				return searchProduct.languages[currentLang];
			}

			if (searchProduct.languages && searchProduct.languages['en']) {
				return searchProduct.languages['en'];
			}

			if (searchProduct.languages) {
				return Object.values(searchProduct.languages)[0];
			}

			return searchProduct;
		}

		// Function to perform search
		function performSearch(query) {
			const filteredProducts = searchProductsData.filter(product => {
				const nameMatch = product.all_names.toLowerCase().includes(query.toLowerCase());
				const descMatch = product.all_descriptions.toLowerCase().includes(query.toLowerCase());
				return nameMatch || descMatch;
			});

			const displayProducts = filteredProducts.map(product => getDisplayProduct(product, query));
			displaySearchResults(displayProducts);
		}

		// Function to display search results
		function displaySearchResults(products) {
			searchResults.innerHTML = '';

			if (products.length === 0) {
				noResults.classList.remove('hidden');
				return;
			}

			noResults.classList.add('hidden');

			products.forEach(product => {
				const resultItem = document.createElement('div');
				resultItem.className = 'flex items-center p-4 border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors';
				resultItem.onclick = () => {
					hideSearchModal();
					// Redirect to product page with product details
					window.location.href = 'product.php#' + product.id;
				};

				resultItem.innerHTML = `
					<img src="${product.image || '/assets/images/product-medium.png'}" alt="${product.name}" class="w-12 h-12 object-contain mr-4 rounded">
					<div class="flex-1">
						<h4 class="font-semibold text-gray-800">${product.name}</h4>
						<p class="text-sm text-gray-600">$${parseFloat(product.price).toFixed(2)}</p>
					</div>
					<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
					</svg>
				`;
				searchResults.appendChild(resultItem);
			});
		}

		// Event listeners
		searchButton.addEventListener('click', showSearchModal);
		closeSearchModalBtn.addEventListener('click', hideSearchModal);

		searchModal.addEventListener('click', function(e) {
			if (e.target === searchModal) {
				hideSearchModal();
			}
		});

		searchInput.addEventListener('input', function(e) {
			const query = e.target.value.trim();
			if (query.length > 0) {
				performSearch(query);
			} else {
				searchResults.innerHTML = '';
				noResults.classList.add('hidden');
			}
		});

		// Keyboard support
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && !searchModal.classList.contains('hidden')) {
				hideSearchModal();
			}
		});

		// Header scroll effect
		window.addEventListener('scroll', function() {
			const header = document.querySelector('header');
			if (window.scrollY > 10) {
				header.classList.add('scrolled');
			} else {
				header.classList.remove('scrolled');
			}
		});

	</script>

	<!-- Add bottom padding for mobile nav -->
	<style>
		@media (max-width: 768px) {
			body {
				padding-bottom: 80px;
			}
		}
	</style>

	<!-- Contact Us Modal -->
	<div id="contactModal" class="fixed inset-0 z-50 hidden items-center justify-center">
		<div class="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm" onclick="closeContactModal()"></div>
		<div class="relative w-full max-w-2xl max-h-[90vh] bg-white shadow-2xl rounded-lg overflow-hidden modal-content transform scale-95 transition-transform duration-300 ease-out">
			<div class="bg-gradient-to-r from-orange-500 to-red-600 p-6 text-white">
				<div class="flex justify-between items-center">
					<h3 class="text-2xl font-bold">Contact Us</h3>
					<button onclick="closeContactModal()" class="p-2 hover:bg-white hover:bg-opacity-20 rounded-full transition-all duration-300">
						<i class="fas fa-times text-white text-xl"></i>
					</button>
				</div>
			</div>
			<div class="p-6">
				<div class="space-y-6">
					<?php $contactContent = getSetting('contact_us', ''); ?>
					<?php if (!empty($contactContent)): ?>
						<div class="prose max-w-none">
							<?php echo nl2br(htmlspecialchars($contactContent)); ?>
						</div>
					<?php else: ?>
						<div class="flex items-center">
							<div class="bg-blue-100 p-3 rounded-full mr-4">
								<i class="fas fa-map-marker-alt text-blue-600"></i>
							</div>
							<div>
								<h4 class="font-semibold text-gray-800">Address</h4>
								<p class="text-gray-600"><?php echo htmlspecialchars(getSetting('company_address', '123 Coffee Street, City, Country')); ?></p>
							</div>
						</div>
						
						<div class="flex items-center">
							<div class="bg-green-100 p-3 rounded-full mr-4">
								<i class="fas fa-phone text-green-600"></i>
							</div>
							<div>
								<h4 class="font-semibold text-gray-800">Phone</h4>
								<p class="text-gray-600"><?php echo htmlspecialchars(getSetting('company_phone', '+1 (555) 123-4567')); ?></p>
							</div>
						</div>
						
						<div class="flex items-center">
							<div class="bg-purple-100 p-3 rounded-full mr-4">
								<i class="fas fa-envelope text-purple-600"></i>
							</div>
							<div>
								<h4 class="font-semibold text-gray-800">Email</h4>
								<p class="text-gray-600"><?php echo htmlspecialchars(getSetting('company_email', 'info@kouprey.com')); ?></p>
							</div>
						</div>
						
						<div class="flex items-center">
							<div class="bg-yellow-100 p-3 rounded-full mr-4">
								<i class="fas fa-clock text-yellow-600"></i>
							</div>
							<div>
								<h4 class="font-semibold text-gray-800">Business Hours</h4>
								<p class="text-gray-600"><?php echo htmlspecialchars(getSetting('company_hours', 'Mon-Fri: 9AM-6PM, Sat-Sun: 10AM-4PM')); ?></p>
							</div>
						</div>
					<?php endif; ?>
				</div>
				
				<div class="mt-8 pt-6 border-t border-gray-200">
					<h4 class="font-semibold text-gray-800 mb-4">Send us a message</h4>
					<form class="space-y-4">
						<div>
							<input type="text" placeholder="Your Name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
						</div>
						<div>
							<input type="email" placeholder="Your Email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent">
						</div>
						<div>
							<textarea rows="4" placeholder="Your Message" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"></textarea>
						</div>
						<button type="submit" class="w-full bg-orange-500 text-white py-2 px-4 rounded-lg hover:bg-orange-600 transition-colors">
							Send Message
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<script>
		// Contact Us Modal Functions
		function openContactModal() {
			const modal = document.getElementById('contactModal');
			if (modal) {
				modal.classList.remove('hidden');
				modal.classList.add('flex');
				// Trigger animation after a small delay
				setTimeout(() => {
					const modalContent = modal.querySelector('.modal-content');
					if (modalContent) {
						modalContent.classList.remove('scale-95');
						modalContent.classList.add('scale-100');
					}
				}, 10);
				document.body.style.overflow = 'hidden';
			}
		}

		function closeContactModal() {
			const modal = document.getElementById('contactModal');
			if (modal) {
				const modalContent = modal.querySelector('.modal-content');
				if (modalContent) {
					modalContent.classList.remove('scale-100');
					modalContent.classList.add('scale-95');
				}
				// Hide after animation
				setTimeout(() => {
					modal.classList.add('hidden');
					modal.classList.remove('flex');
				}, 300);
				document.body.style.overflow = 'auto';
			}
		}
	</script>

	<!-- Mobile Bottom Navigation (iOS 26 Floating Island) -->
	<nav class="md:hidden fixed bottom-6 left-6 right-6 bg-white/15 backdrop-blur-[30px] backdrop-saturate-[180%] border border-white/40 shadow-[0_20px_50px_rgba(0,0,0,0.1)] rounded-[2.5rem] z-40 pb-safe px-2 overflow-hidden">
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
</body>
</html>
