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

// Fetch features from database - get both languages
$stmt = $pdo->prepare("SELECT * FROM features ORDER BY base_feature_id, language");
$stmt->execute();
$allFeatures = $stmt->fetchAll();

// Group features by base_feature_id
$featuresGrouped = [];
foreach ($allFeatures as $feature) {
    $baseId = $feature['base_feature_id'] ?? $feature['id'];
    $featuresGrouped[$baseId][$feature['language']] = $feature;
}

// Use current language features for display
$features = array_map(function($group) {
    return $group[getCurrentLanguage()] ?? ($group['en'] ?? reset($group));
}, $featuresGrouped);

// Fetch products for search functionality (all languages)
$currentLanguage = getCurrentLanguage();
$productStmt = $pdo->prepare("SELECT * FROM products ORDER BY sort_order ASC, featured DESC, best_seller DESC, id DESC");
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

// Fetch reviews for products (will be used in product detail modal)
$reviewsStmt = $pdo->query("
    SELECT r.*, p.name as product_name 
    FROM reviews r 
    LEFT JOIN products p ON r.product_id = p.id 
    ORDER BY r.created_at DESC
");
$allReviews = $reviewsStmt->fetchAll();

// Group reviews by product_id for easy access
$reviewsByProduct = [];
foreach ($allReviews as $review) {
    $reviewsByProduct[$review['product_id']][] = $review;
}

// Fetch feature-product assignments
$assignmentStmt = $pdo->query("SELECT feature_id, product_id FROM feature_products");
$rawAssignments = $assignmentStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

// Convert assignments to work with current language features
$assignments = [];
foreach ($rawAssignments as $featureId => $productIds) {
    // Find which base_feature_id this feature belongs to
    $stmt = $pdo->prepare("SELECT base_feature_id FROM features WHERE id = ?");
    $stmt->execute([$featureId]);
    $feature = $stmt->fetch();
    if ($feature) {
        $baseId = $feature['base_feature_id'];
        // Find the current language version of this feature
        $stmt = $pdo->prepare("SELECT id FROM features WHERE base_feature_id = ? AND language = ?");
        $stmt->execute([$baseId, getCurrentLanguage()]);
        $currentFeature = $stmt->fetch();
        if ($currentFeature) {
            $assignments[$currentFeature['id']] = $productIds;
        }
    }
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
	<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
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
		overflow-x: hidden; /* Modern way to hide horizontal overflow without breaking sticky */
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

	/* iOS 18 Style Modal Styles */
	#searchModal,
	#productDetailModal,
	#featureModal {
		backdrop-filter: blur(20px);
		-webkit-backdrop-filter: blur(20px);
		background: rgba(0, 0, 0, 0.4);
	}

	#searchModal .modal-content,
	#productDetailModal .modal-content,
	#featureModal .modal-content {
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

	#searchModal:not(.hidden) .modal-content,
	#productDetailModal:not(.hidden) .modal-content,
	#featureModal:not(.hidden) .modal-content {
		transform: scale(1) translateY(0);
		animation: iosModalSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
	}

	/* iOS 18 Modal Header */
	#searchModal .modal-content > div:first-child,
	#productDetailModal .modal-content > div:first-child,
	#featureModal .modal-content > div:first-child {
		border-bottom: 1px solid rgba(0, 0, 0, 0.1);
		background: linear-gradient(180deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 100%);
		backdrop-filter: blur(20px);
		border-radius: 28px 28px 0 0;
		padding: 24px;
	}

	/* iOS 18 Close Button */
	#searchModal button[id*="close"],
	#productDetailModal button[id*="close"],
	#featureModal button[id*="close"] {
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

	#searchModal button[id*="close"]:hover,
	#productDetailModal button[id*="close"]:hover,
	#featureModal button[id*="close"]:hover {
		background: rgba(142, 142, 147, 0.24);
		transform: scale(1.05);
	}

	#searchModal button[id*="close"]:active,
	#productDetailModal button[id*="close"]:active,
	#featureModal button[id*="close"]:active {
		transform: scale(0.95);
		background: rgba(142, 142, 147, 0.36);
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

	/* iOS 18 Modal Content */
	#searchModal .modal-content > div:not(:first-child),
	#productDetailModal .modal-content > div:not(:first-child),
	#featureModal .modal-content > div:not(:first-child) {
		padding: 24px;
	}

	/* iOS 18 Typography */
	#searchModal h3,
	#productDetailModal h3,
	#featureModal h3 {
		font-weight: 600;
		font-size: 20px;
		color: #1c1c1e;
		margin: 0;
		font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
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
		#searchModal .modal-content,
		#productDetailModal .modal-content,
		#featureModal .modal-content {
			margin: 16px;
			max-width: calc(100vw - 32px);
			max-height: calc(100vh - 32px);
			border-radius: 24px;
		}

		#searchModal .modal-content > div:first-child,
		#productDetailModal .modal-content > div:first-child,
		#featureModal .modal-content > div:first-child {
			padding: 20px;
			border-radius: 24px 24px 0 0;
		}

		#searchModal .modal-content > div:not(:first-child),
		#productDetailModal .modal-content > div:not(:first-child),
		#featureModal .modal-content > div:not(:first-child) {
			padding: 20px;
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
	.pb-safe {
		padding-bottom: 20px;
		padding-bottom: env(safe-area-inset-bottom, 20px);
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

	/* iOS-style status bar spacing */
	@supports (padding-top: env(safe-area-inset-top)) {
		@media (max-width: 768px) {
			body {
				padding-top: env(safe-area-inset-top);
			}
		}
	}

	/* Scroll effects for Header */
	header.scrolled {
		background-color: rgba(255, 255, 255, 0.98);
		box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
		border-bottom-color: rgba(229, 231, 235, 0.5);
	}

/* Feature node animation */
.feature-node {
    transition: left 650ms cubic-bezier(.22,.9,.2,1), top 650ms cubic-bezier(.22,.9,.2,1), transform 420ms cubic-bezier(.2,.9,.2,1), opacity 420ms ease;
}
#featureLines line {
    transition: stroke-dashoffset 600ms ease, stroke-width 200ms ease, stroke 200ms ease;
}

			/* Mobile feature card tweaks */
			.feature-mobile-card {
				border: none;
				text-align: left;
			}
			.feature-mobile-card:focus {
				outline: none;
				box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
			}
			.inf-number { font-size: 16px; }
			
			/* Safe area padding for bottom nav */
			.pb-safe {
				padding-bottom: 20px;
				padding-bottom: env(safe-area-inset-bottom, 20px);
			}
	</style>
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
	<header class="w-full fixed top-0 left-0 z-50 transition-all duration-500 px-4 py-4 md:px-6 md:py-3">
		<div class="flex items-center justify-between max-w-6xl mx-auto h-full">
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

	<main class="max-w-6xl mx-auto px-4 md:px-6 py-12 md:py-20 mt-20 md:mt-24">
		<div class="text-center mb-20" data-aos="fade-up">
			<span class="text-yellow-600 font-bold tracking-wider uppercase text-sm mb-2 block"><?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?> Process</span>
			<h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-6 font-freeman leading-tight">Our Quality Guarantee</h1>
			<p class="max-w-2xl mx-auto text-lg text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars(getSetting('features_description', 'Discover the step-by-step journey that makes our coffee exceptional — from sustainable sourcing to expert roasting.'))); ?></p>
		</div>

		<!-- Process Timeline Container -->
		<div class="relative max-w-5xl mx-auto">
			<!-- Vertical Line (Desktop: Center, Mobile: Left) -->
			<div class="absolute left-8 md:left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-yellow-200 via-orange-200 to-transparent transform md:-translate-x-1/2 rounded-full z-0"></div>

			<?php 
			$__icons = ['fa-leaf', 'fa-fire', 'fa-award', 'fa-coffee', 'fa-mug-hot', 'fa-seedling', 'fa-check-circle', 'fa-globe-americas', 'fa-heart'];
			$__gradients = [
				'from-green-400 to-green-600', 
				'from-orange-400 to-red-500', 
				'from-blue-400 to-blue-600', 
				'from-yellow-400 to-yellow-600', 
				'from-purple-400 to-purple-600',
				'from-teal-400 to-teal-600'
			];
			$__shadows = [
				'shadow-green-200',
				'shadow-orange-200',
				'shadow-blue-200',
				'shadow-yellow-200',
				'shadow-purple-200',
				'shadow-teal-200'
			];
			$__i = 0; 
			?>

			<!-- Steps Loop -->
			<div class="relative z-10 space-y-16 md:space-y-0">
				<?php foreach ($features as $baseId => $feature): ?>
					<?php 
						$iconClass = $__icons[$__i % count($__icons)];
						$gradientClass = $__gradients[$__i % count($__gradients)];
						$shadowClass = $__shadows[$__i % count($__shadows)];
						$isEven = ($__i % 2 === 0);
						$stepNum = str_pad($__i + 1, 2, '0', STR_PAD_LEFT);
					?>
					
					<!-- Step Item -->
					<div class="relative flex flex-col md:flex-row items-center <?php echo $isEven ? 'md:flex-row-reverse' : ''; ?> group w-full md:mb-24 last:mb-0" data-aos="fade-up">
						
						<!-- Desktop Spacer (for alternating layout) -->
						<div class="hidden md:block w-1/2"></div>
						
						<!-- Center Node/Icon -->
						<div class="absolute left-8 md:left-1/2 transform -translate-x-1/2 flex items-center justify-center">
							<div class="w-16 h-16 rounded-full bg-white border-4 border-gray-50 shadow-xl z-20 flex items-center justify-center group-hover:scale-110 transition-transform duration-300 relative">
								<div class="absolute inset-0 rounded-full bg-gradient-to-br <?php echo $gradientClass; ?> opacity-20 animate-pulse"></div>
								<span class="font-freeman text-xl font-bold text-gray-800 relative z-10"><?php echo $stepNum; ?></span>
							</div>
						</div>

						<!-- Content Card -->
						<div class="ml-24 md:ml-0 md:w-1/2 <?php echo $isEven ? 'md:pr-16 md:text-right' : 'md:pl-16 md:text-left'; ?> md:pl-0">
							<div class="bg-white rounded-3xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 relative overflow-hidden group-hover:-translate-y-1 cursor-pointer" onclick="showFeatureModal({id: '<?php echo $baseId; ?>', title: '<?php echo addslashes($feature['title']); ?>', description: '<?php echo addslashes($feature['description']); ?>'})">
								<!-- Decorative Gradient Blob -->
								<div class="absolute top-0 <?php echo $isEven ? 'right-0' : 'right-0 md:left-0'; ?> w-32 h-32 bg-gradient-to-br <?php echo $gradientClass; ?> opacity-5 rounded-full blur-2xl -mt-10 -mr-10"></div>
								
								<div class="relative z-10">
									<div class="flex items-center gap-3 mb-4 <?php echo $isEven ? 'md:justify-end' : 'md:justify-start'; ?>">
										<div class="w-10 h-10 rounded-xl bg-gradient-to-br <?php echo $gradientClass; ?> flex items-center justify-center text-white shadow-md">
											<i class="fas <?php echo $iconClass; ?> text-sm"></i>
										</div>
										<h3 class="text-xl font-bold text-gray-800 group-hover:text-yellow-600 transition-colors"><?php echo htmlspecialchars($feature['title']); ?></h3>
									</div>
									
									<p class="text-gray-600 leading-relaxed text-base mb-6">
										<?php 
										$d = $feature['description'] ?? ''; 
										if (strlen($d) > 150) $d = substr($d, 0, 147) . '...'; 
										echo htmlspecialchars($d); 
										?>
									</p>


								</div>
							</div>
						</div>
					</div>
					<?php $__i++; ?>
				<?php endforeach; ?>
			</div>
		</div>


		<script>
			// Initialize AOS if available, otherwise simple fallback
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof AOS !== 'undefined') {
					AOS.init({
						duration: 800,
						once: true,
						offset: 100
					});
				}
			});
		</script>
	</main>

	<footer class="mt-auto bg-gray-900 text-white py-12">
		<div class="max-w-6xl mx-auto px-4 md:px-6">
			<div class="grid grid-cols-1 md:grid-cols-4 gap-8">
				<!-- Company Info -->
				<div class="col-span-1 md:col-span-2">
					<div class="flex items-center mb-4">
						<img src="https://i.ibb.co/zT8QwG1h/Untitled-1-Recovered-3-Recovered-Recovered.png" alt="<?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?>" class="h-8 w-auto mr-3 object-contain">
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
					<h3 class="text-lg font-semibold mb-4 text-white"><?php echo htmlspecialchars(getSetting('footer_connect_with_us', 'Connect With Us')); ?></h3>
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
							<a href="#" class="text-gray-300 hover:text-pink-400 transition-colors">
								<i class="fab fa-tiktok text-xl"></i>
							</a>
							<a href="#" class="text-gray-300 hover:text-blue-500 transition-colors">
								<i class="fab fa-telegram-plane text-xl"></i>
							</a>
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

	<!-- Product Detail Modal -->
	<div id="productDetailModal" class="fixed inset-0 z-50 hidden" style="z-index: 60;">
		<div class="flex items-center justify-center min-h-screen p-4">
			<div class="modal-content bg-white max-w-4xl w-full max-h-[95vh] overflow-hidden relative">
				<!-- Modal Header -->
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<div id="productDetailBadgeHeader" class="px-4 py-2 text-sm font-bold rounded-full bg-blue-500 text-white">
							Product
						</div>
						<h3 class="text-2xl font-bold text-gray-800 font-freeman" id="productDetailTitle">Product Details</h3>
					</div>
					<button id="closeProductDetailModal" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-full duration-200 ease-in-out">
						<svg class="w-6 h-6 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>

				<!-- Modal Content -->
				<div class="overflow-y-auto max-h-[calc(95vh-140px)]">
					<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
						<!-- Product Image Section -->
						<div class="space-y-4">
							<div class="relative">
								<div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-2xl p-8 flex justify-center items-center">
									<img id="productDetailImage" src="/kouprey/public/assets/images/product-medium.png" alt="Product Image" class="w-full max-w-md h-80 object-contain rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
								</div>
								<!-- Floating badge -->
								<div class="absolute -top-3 -right-3 bg-yellow-400 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg animate-pulse flex items-center">
									<i class="fas fa-check-circle text-white mr-1"></i> Premium
								</div>
							</div>

							<!-- Quick Info Cards -->
							<div class="grid grid-cols-2 gap-3">
								<div class="bg-blue-50 rounded-xl p-4 text-center">
									<div class="text-2xl font-bold text-blue-600 mb-1" id="productDetailPrice">$0.00</div>
									<div class="text-sm text-blue-600 font-medium flex items-center justify-center gap-1">
										<i class="fas fa-dollar-sign"></i> Price
									</div>
								</div>
								<div class="bg-green-50 rounded-xl p-4 text-center">
									<div class="text-2xl font-bold text-green-600 mb-1" id="productDetailWeight">250g</div>
									<div class="text-sm text-green-600 font-medium flex items-center justify-center gap-1">
										<i class="fas fa-weight-hanging"></i> Weight
									</div>
								</div>
							</div>
						</div>

						<!-- Product Details Section -->
						<div class="space-y-6">
							<!-- Product Title and Description -->
							<div>
								<h4 class="text-3xl font-bold text-gray-800 mb-3 font-freeman" id="productDetailName">Product Name</h4>
								<p class="text-gray-600 leading-relaxed text-lg" id="productDetailDescription">Product description goes here.</p>
							</div>

							<!-- Product Badges -->
							<div class="flex flex-wrap gap-2" id="productDetailBadges">
								<!-- Badges will be populated here -->
							</div>

							<!-- Detailed Information Accordion -->
							<div class="space-y-3">
								<!-- Detailed Description -->
								<div id="productDetailDetailedDescription" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-info-circle text-blue-500"></i> Detailed Description
									</h5>
									<p class="text-gray-600 leading-relaxed" id="productDetailDetailedDesc"></p>
								</div>

								<!-- Ingredients -->
								<div id="productDetailIngredients" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-seedling text-green-500"></i> Ingredients
									</h5>
									<p class="text-gray-600 leading-relaxed" id="productDetailIngredientsText"></p>
								</div>


								<!-- Brewing Instructions -->
								<div id="productDetailBrewing" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-coffee text-brown-500"></i> Brewing Instructions
									</h5>
									<p class="text-gray-600 leading-relaxed" id="productDetailBrewingText"></p>
								</div>

								<!-- Tasting Notes -->
								<div id="productDetailTasting" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-tongue text-red-500"></i> Tasting Notes
									</h5>
									<p class="text-gray-600 leading-relaxed" id="productDetailTastingText"></p>
								</div>

								<!-- Roast Level -->
								<div id="productDetailRoastInfo" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-burn text-orange-500"></i> Roast Level
									</h5>
									<p class="text-gray-600 leading-relaxed" id="productDetailRoastLevelText"></p>
								</div>
							</div>

							<!-- Action Buttons -->
							<div class="flex gap-4 pt-6 border-t border-gray-200">
								<button id="productDetailShareBtn" class="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 font-medium flex items-center justify-center gap-2">
									<i class="fas fa-share-alt"></i> Share
								</button>
							</div>

							<!-- Additional Info -->
							<div class="bg-blue-50 rounded-xl p-4">
								<h5 class="font-bold text-blue-800 mb-2 flex items-center gap-2">
									<i class="fas fa-question-circle text-blue-600"></i> Why Choose This Product?
								</h5>
								<ul class="text-sm text-blue-700 space-y-1" id="productDetailDetails">
									<li><i class="fas fa-check text-green-500 mr-2"></i> Premium quality ingredients</li>
									<li><i class="fas fa-check text-green-500 mr-2"></i> Carefully sourced and roasted</li>
									<li><i class="fas fa-check text-green-500 mr-2"></i> Perfect for any occasion</li>
									<li><i class="fas fa-check text-green-500 mr-2"></i> Rich in flavor and aroma</li>
								</ul>
							</div>

							<!-- Customer Reviews Section -->
							<div class="bg-yellow-50 rounded-xl p-4" id="productDetailReviews" style="display: none;">
								<h5 class="font-bold text-yellow-800 mb-4 flex items-center gap-2">
									<i class="fas fa-star text-yellow-500"></i> Customer Reviews
								</h5>
								<div id="productDetailReviewsContent" class="space-y-4">
									<!-- Reviews will be populated here by JavaScript -->
								</div>
								<div id="productDetailNoReviews" class="text-center py-4 text-gray-500">
									<i class="fas fa-comment-slash text-2xl mb-2"></i>
									<p>No reviews yet for this product.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Feature Details Modal -->
	<div id="featureModal" class="fixed inset-0 z-50 hidden">
		<div class="flex items-center justify-center min-h-screen p-4">
			<div class="modal-content bg-white max-w-4xl w-full max-h-[90vh] overflow-hidden relative">
				<!-- Feature Header -->
				<div class="flex items-center justify-between">
					<h3 class="text-2xl font-bold text-gray-800 flex items-center" id="featureModalTitle">
						<i class="fas fa-info-circle text-purple-500 mr-2"></i>Feature Details
					</h3>
					<button id="closeFeatureModal" class="text-gray-400 hover:text-gray-600 transition-colors">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>

				<!-- Feature Content -->
				<div class="overflow-y-auto max-h-[calc(90vh-140px)]">
					<!-- Feature Description -->
					<div class="mb-8">
						<h4 class="text-xl font-semibold text-gray-800 mb-4">About This Feature</h4>
						<p class="text-gray-600 leading-relaxed" id="featureModalDescription"></p>
					</div>

					<!-- Related Products -->
					<div>
						<h4 class="text-xl font-semibold text-gray-800 mb-4">Our Products Featuring This</h4>
						<div id="featureProducts" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
							<!-- Products will be populated here -->
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Embed product data for JavaScript -->
	<script>
		const productsData = <?php echo json_encode($products); ?>;
		const searchProductsData = <?php echo json_encode($searchProducts); ?>;
		const featureAssignments = <?php echo json_encode($assignments); ?>;
		let currentFeatureId = null;
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
					<img src="${product.image || '/kouprey/public/assets/images/product-medium.png'}" alt="${product.name}" class="w-12 h-12 object-contain mr-4 rounded">
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
			if (e.key === 'Escape' && !featureModal.classList.contains('hidden')) {
				hideFeatureModal();
			}
			if (e.key === 'Escape' && !productDetailModal.classList.contains('hidden')) {
				hideProductDetailModal();
			}
		});

		// Feature Modal functionality
		const featureModal = document.getElementById('featureModal');
		const closeFeatureModalBtn = document.getElementById('closeFeatureModal');
		const featureModalTitle = document.getElementById('featureModalTitle');
		const featureModalDescription = document.getElementById('featureModalDescription');
		const featureProducts = document.getElementById('featureProducts');

		// Function to show feature modal
		function showFeatureModal(featureData) {
			currentFeatureId = featureData.id;
			featureModalTitle.textContent = featureData.title;
			featureModalDescription.textContent = featureData.description;
			renderFeatureProducts();
			featureModal.classList.remove('hidden');
			document.body.style.overflow = 'hidden';
		}

		// Function to hide feature modal
		function hideFeatureModal() {
			featureModal.classList.add('hidden');
			document.body.style.overflow = 'auto';
		}

		// Function to render products in feature modal
		function renderFeatureProducts() {
			featureProducts.innerHTML = '';

			// Get assigned product IDs for current feature
			const assignedProductIds = featureAssignments[currentFeatureId] || [];
			
			if (assignedProductIds.length === 0) {
				featureProducts.innerHTML = `
					<div class="col-span-full text-center py-8 text-gray-500">
						<svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
						</svg>
						<h4 class="text-lg font-medium mb-2">No products assigned</h4>
						<p class="text-sm">This feature doesn't have any assigned products yet.</p>
					</div>
				`;
				return;
			}

			// Filter and display only assigned products
			const assignedProducts = productsData.filter(product => assignedProductIds.includes(product.id));

			assignedProducts.forEach(product => {
				const productCard = document.createElement('div');
				productCard.className = 'bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow cursor-pointer';
				productCard.onclick = () => {
					showProductDetailModal(product);
				};

				productCard.innerHTML = `
					<div class="flex flex-col items-center text-center">
						<img src="${product.image || '/kouprey/public/assets/images/product-medium.png'}" alt="${product.name}" class="w-20 h-20 object-contain mb-3 rounded">
						<h4 class="font-semibold text-gray-800 text-sm mb-2">${product.name}</h4>
						<p class="text-yellow-600 font-bold text-sm">$${parseFloat(product.price).toFixed(2)}</p>
						<button class="mt-2 bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-3 py-1 rounded transition-colors">
							View Details
						</button>
					</div>
				`;

				featureProducts.appendChild(productCard);
			});
		}

		// Product Detail Modal functionality
		const productDetailModal = document.getElementById('productDetailModal');
		const closeProductDetailModalBtn = document.getElementById('closeProductDetailModal');
		const productDetailTitle = document.getElementById('productDetailTitle');
		const productDetailImage = document.getElementById('productDetailImage');
		const productDetailName = document.getElementById('productDetailName');
		const productDetailPrice = document.getElementById('productDetailPrice');
		const productDetailDescription = document.getElementById('productDetailDescription');
		const productDetailFeatures = document.getElementById('productDetailFeatures');
		const viewFullProductBtn = document.getElementById('viewFullProductBtn');

		// Function to show product detail modal
		function showProductDetailModal(product) {
			// Ensure product detail modal appears on top
			productDetailModal.style.zIndex = '60';
			featureModal.style.zIndex = '50';

			productDetailTitle.textContent = product.name + ' Details';
			productDetailImage.src = product.image || '/assets/images/product-medium.png';
			productDetailImage.alt = product.name;
			productDetailName.textContent = product.name;
			productDetailPrice.textContent = '$' + parseFloat(product.price).toFixed(2);
			productDetailWeight.textContent = product.weight || '250g';
			productDetailDescription.textContent = product.description || 'No description available for this product.';

			// Update badge header
			const badgeHeader = document.getElementById('productDetailBadgeHeader');
			if (product.featured == 1) {
				badgeHeader.textContent = 'Featured';
				badgeHeader.className = 'px-4 py-2 text-sm font-bold rounded-full bg-yellow-500 text-white';
			} else if (product.best_seller == 1) {
				badgeHeader.textContent = 'Best Seller';
				badgeHeader.className = 'px-4 py-2 text-sm font-bold rounded-full bg-red-500 text-white';
			} else {
				badgeHeader.textContent = 'Premium';
				badgeHeader.className = 'px-4 py-2 text-sm font-bold rounded-full bg-blue-500 text-white';
			}

			// Populate badges
			const badgesContainer = document.getElementById('productDetailBadges');
			badgesContainer.innerHTML = '';
			if (product.featured == 1) {
				badgesContainer.innerHTML += '<span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1"><i class="fas fa-star"></i> Featured</span>';
			}
			if (product.best_seller == 1) {
				badgesContainer.innerHTML += '<span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1"><i class="fas fa-fire"></i> Best Seller</span>';
			}
			if (product.origin) {
				badgesContainer.innerHTML += '<span class="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1"><i class="fas fa-globe-americas"></i> ' + product.origin + '</span>';
			}

			// Populate detailed description
			const detailedDescSection = document.getElementById('productDetailDetailedDescription');
			const detailedDesc = document.getElementById('productDetailDetailedDesc');
			if (product.detailed_description) {
				detailedDesc.textContent = product.detailed_description;
				detailedDescSection.style.display = 'block';
			} else {
				detailedDescSection.style.display = 'none';
			}

			// Populate ingredients
			const ingredientsSection = document.getElementById('productDetailIngredients');
			const ingredients = document.getElementById('productDetailIngredientsText');
			if (product.ingredients) {
				ingredients.textContent = product.ingredients;
				ingredientsSection.style.display = 'block';
			} else {
				ingredientsSection.style.display = 'none';
			}

			// Populate origin
			const originSection = document.getElementById('productDetailOrigin');
			const origin = document.getElementById('productDetailOriginText');
			if (product.origin) {
				origin.textContent = product.origin;
				originSection.style.display = 'block';
			} else {
				originSection.style.display = 'none';
			}

			// Populate brewing instructions
			const brewingSection = document.getElementById('productDetailBrewing');
			const brewingInstructions = document.getElementById('productDetailBrewingText');
			if (product.brewing_instructions) {
				brewingInstructions.textContent = product.brewing_instructions;
				brewingSection.style.display = 'block';
			} else {
				brewingSection.style.display = 'none';
			}

			// Populate tasting notes
			const tastingSection = document.getElementById('productDetailTasting');
			const tastingNotes = document.getElementById('productDetailTastingText');
			if (product.tasting_notes) {
				tastingNotes.textContent = product.tasting_notes;
				tastingSection.style.display = 'block';
			} else {
				tastingSection.style.display = 'none';
			}

			// Populate roast level
			const roastSection = document.getElementById('productDetailRoastInfo');
			const roastLevel = document.getElementById('productDetailRoastLevelText');
			if (product.roast_level) {
				roastLevel.textContent = product.roast_level;
				roastSection.style.display = 'block';
			} else {
				roastSection.style.display = 'none';
			}

			// Generate features based on available data
			const features = [];
			if (product.name.toLowerCase().includes('coffee')) {
				features.push('Premium quality coffee beans');
				features.push('Carefully roasted for optimal flavor');
				features.push('Rich and aromatic taste');
				if (product.roast_level) {
					features.push(product.roast_level + ' roast level');
				}
				if (product.origin) {
					features.push('Sourced from ' + product.origin);
				}
				features.push('Perfect for any brewing method');
			} else {
				features.push('High quality ingredients');
				features.push('Carefully prepared');
				features.push('Excellent taste profile');
				features.push('Premium selection');
			}

			if (product.best_seller == 1) {
				features.push('Customer favorite - Best Seller');
			}
			if (product.featured == 1) {
				features.push('Featured product');
			}

			const detailsList = document.getElementById('productDetailDetails');
			detailsList.innerHTML = features.map(feature => `
				<li><i class="fas fa-check text-green-500 mr-2"></i> ${feature}</li>
			`).join('');

			// Set up button actions
			const shareBtn = document.getElementById('productDetailShareBtn');

			shareBtn.onclick = () => {
				if (navigator.share) {
					navigator.share({
						title: product.name,
						text: product.description,
						url: window.location.href
					});
				} else {
					// Fallback: copy to clipboard
					const url = window.location.href;
					navigator.clipboard.writeText(url).then(() => {
						showToast('Link copied to clipboard!');
					});
				}
			};

			// Populate customer reviews
			const reviewsSection = document.getElementById('productDetailReviews');
			const reviewsContent = document.getElementById('productDetailReviewsContent');
			const noReviews = document.getElementById('productDetailNoReviews');
			
			// Get reviews for this product from the PHP data
			const productReviews = <?php echo json_encode($reviewsByProduct); ?>[product.id] || [];
			
			if (productReviews.length > 0) {
				reviewsContent.innerHTML = productReviews.slice(0, 3).map(review => {
					const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
					const reviewDate = new Date(review.created_at).toLocaleDateString();
					return `
						<div class="bg-white rounded-lg p-4 border border-gray-200">
							<div class="flex items-center justify-between mb-2">
								<div class="flex items-center gap-2">
									<div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
										${review.name.charAt(0).toUpperCase()}
									</div>
									<div>
										<div class="font-semibold text-gray-800">${review.name}</div>
										<div class="text-yellow-500 text-sm">${stars}</div>
									</div>
								</div>
								<div class="text-sm text-gray-500">${reviewDate}</div>
							</div>
							<p class="text-gray-700 leading-relaxed">${review.review}</p>
						</div>
					`;
				}).join('');
				reviewsSection.style.display = 'block';
				noReviews.style.display = 'none';
			} else {
				reviewsSection.style.display = 'block';
				noReviews.style.display = 'block';
				reviewsContent.innerHTML = '';
			}

			productDetailModal.classList.remove('hidden');
			document.body.style.overflow = 'hidden';
		}

		// Function to hide product detail modal
		function hideProductDetailModal() {
			productDetailModal.classList.add('hidden');
			document.body.style.overflow = 'auto';
		}

		// Event listeners for feature modal
		closeFeatureModalBtn.addEventListener('click', hideFeatureModal);

		featureModal.addEventListener('click', function(e) {
			if (e.target === featureModal) {
				hideFeatureModal();
			}
		});

		// Add click listeners to feature cards
		document.addEventListener('click', function(e) {
			const featureCard = e.target.closest('.feature-card');
			if (featureCard) {
				const featureData = {
					id: featureCard.dataset.featureId,
					title: featureCard.dataset.featureTitle,
					description: featureCard.dataset.featureDescription
				};
				showFeatureModal(featureData);
			}
		});

		// Add click listeners to product cards within features
		document.addEventListener('click', function(e) {
			const productCard = e.target.closest('.product-card');
			if (productCard && !e.target.closest('.feature-card')) { // Make sure it's not clicking on a feature card
				e.stopPropagation(); // Prevent triggering feature card click
				const productData = {
					id: productCard.dataset.productId,
					name: productCard.dataset.productName,
					description: productCard.dataset.productDescription,
					price: productCard.dataset.productPrice,
					image: productCard.dataset.productImage
				};
				showProductDetailModal(productData);
			}
		});

		// Function to switch feature language within a card
		function switchFeatureLanguage(baseId, language) {
			const featureData = JSON.parse(document.getElementById(`feature-data-${baseId}`).textContent);
			const titleElement = document.getElementById(`feature-title-${baseId}`);
			const descriptionElement = document.getElementById(`feature-description-${baseId}`);
			
			if (featureData[language]) {
				titleElement.textContent = featureData[language].title;
				descriptionElement.textContent = featureData[language].description;
				
				// Update button styles
				const enBtn = titleElement.parentElement.querySelector('button:first-of-type');
				const kmBtn = titleElement.parentElement.querySelector('button:last-of-type');
				
				if (language === 'en') {
					enBtn.classList.remove('bg-gray-200', 'text-gray-600');
					enBtn.classList.add('bg-blue-500', 'text-white');
					kmBtn.classList.remove('bg-blue-500', 'text-white');
					kmBtn.classList.add('bg-gray-200', 'text-gray-600');
				} else {
					kmBtn.classList.remove('bg-gray-200', 'text-gray-600');
					kmBtn.classList.add('bg-blue-500', 'text-white');
					enBtn.classList.remove('bg-blue-500', 'text-white');
					enBtn.classList.add('bg-gray-200', 'text-gray-600');
				}
			}
		}

		// Event listeners for product detail modal
		closeProductDetailModalBtn.addEventListener('click', hideProductDetailModal);

		productDetailModal.addEventListener('click', function(e) {
			if (e.target === productDetailModal) {
				hideProductDetailModal();
			}
		});

		// Keyboard support for product detail modal
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && !productDetailModal.classList.contains('hidden')) {
				hideProductDetailModal();
			}
		});

		// Function to show toast message
		function showToast(message) {
			const toast = document.createElement('div');
			toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
			toast.textContent = message;
			document.body.appendChild(toast);
			setTimeout(() => {
				toast.remove();
			}, 3000);
		}

		// Header scroll effect
		const header = document.querySelector('header');
		window.addEventListener('scroll', function() {
			if (window.scrollY > 10) {
				header.classList.add('shadow-md');
				header.classList.remove('shadow-sm');
			} else {
				header.classList.remove('shadow-md');
				header.classList.add('shadow-sm');
			}
		});

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

	<!-- Add bottom padding for mobile nav -->
	<style>
		@media (max-width: 768px) {
			body {
				padding-bottom: 80px;
			}
		}
	</style>

	<script>
		// Header scroll effect to match product.php
		window.addEventListener('scroll', function() {
			const header = document.querySelector('header');
			if (window.scrollY > 10) {
				header.classList.add('scrolled');
			} else {
				header.classList.remove('scrolled');
			}
		});

		// Ensure active tab logic matches exactly
		document.addEventListener('DOMContentLoaded', function() {
			const currentPage = "<?php echo $current_page; ?>";
			console.log("Current Page:", currentPage);
		});
	</script>

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

		// Sticky Header Effect
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
	</script>
	<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</body>
</html>
