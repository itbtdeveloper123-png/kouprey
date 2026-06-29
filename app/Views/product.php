<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';
require_once __DIR__ . '/../Config/visitor_tracker.php';

// Handle language change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_language'])) {
    setCurrentLanguage($_POST['set_language']);
    exit; // Since it's AJAX, just exit after setting
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get hero images
$heroImages = glob(realpath(__DIR__ . '/../../public/uploads/') . '/hero-bg-*.png');

// Fetch products from database with average ratings for all languages
$currentLanguage = getCurrentLanguage();
$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.base_category_id,
        COALESCE(review_stats.avg_rating, 0) as avg_rating,
        COALESCE(review_stats.review_count, 0) as review_count
	FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
	LEFT JOIN (
        SELECT
            base_product_id,
            AVG(r.rating) as avg_rating,
            COUNT(r.id) as review_count
        FROM reviews r
        JOIN products pr ON r.product_id = pr.id
        GROUP BY pr.base_product_id
    ) review_stats ON p.base_product_id = review_stats.base_product_id
	WHERE p.enabled = 1
    ORDER BY p.sort_order ASC, p.featured DESC, p.id DESC
");
$stmt->execute();
$allProducts = $stmt->fetchAll();

// Group products by base_product_id and keep only current language version for display
$productsByBaseId = [];
foreach ($allProducts as $product) {
    $baseId = $product['base_product_id'];
    if (!isset($productsByBaseId[$baseId])) {
        $productsByBaseId[$baseId] = [];
    }
    $productsByBaseId[$baseId][$product['language']] = $product;
}

// For display, use current language products, fallback to English if not available
$products = [];
foreach ($productsByBaseId as $baseId => $langVersions) {
    if (isset($langVersions[$currentLanguage])) {
        $products[] = $langVersions[$currentLanguage];
    } elseif (isset($langVersions['en'])) {
        $products[] = $langVersions['en'];
    } else {
        // Use first available language version
        $products[] = reset($langVersions);
    }
}
$allAvailableProducts = $products;

// Create a search index with all language versions
$searchProducts = [];
foreach ($productsByBaseId as $baseId => $langVersions) {
    $searchProduct = [
        'base_product_id' => $baseId,
        'languages' => $langVersions, // Include all language versions
        'all_names' => '',
        'all_descriptions' => ''
    ];

    // Collect all language versions for search
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

// Fetch categories for current language
$stmt = $pdo->prepare("SELECT * FROM categories WHERE language = ? ORDER BY name ASC");
$stmt->execute([$currentLanguage]);
$categories = $stmt->fetchAll();

// Function to generate star rating
function generateStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '<i class="fas fa-star text-yellow-400"></i>' : '<i class="far fa-star text-gray-300"></i>';
    }
    return $stars;
}

// Filter Products by Category (Server-Side)
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';
if ($selectedCategory !== 'all') {
    $products = array_filter($products, function($product) use ($selectedCategory) {
        if (strpos($selectedCategory, 'category-') === 0) {
            // Database Category ID
            $catId = substr($selectedCategory, 9);
            return $product['base_category_id'] == $catId;
        } elseif ($selectedCategory === 'featured') {
            return $product['featured'] == 1;
        } elseif ($selectedCategory === 'regular') {
            return $product['featured'] == 0;
        }
        return true;
    });
    // Re-index array after filter
    $products = array_values($products);
}

// Global Pagination Logic (Moved to top for AJAX support)
// Determine perPage based on device: 9 for computer (3x3 grid), 8 for mobile (2x4 grid)
$isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $_SERVER['HTTP_USER_AGENT']);
$perPage = $isMobile ? 8 : 9;
$totalProducts = count($products);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;
$startIndex = ($currentPage - 1) * $perPage;
$pagedProducts = array_slice($products, $startIndex, $perPage);

// AJAX Handler for Pagination
if (isset($_GET['ajax_pagination'])) {
    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    // Start capturing HTML for products
    ob_start();
    ?>
    <div id="products-container" class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-3 gap-2 md:gap-6">
        <?php
        if (!empty($pagedProducts)):
            foreach ($pagedProducts as $index => $product):
                $categoryClass = $product['featured'] ? 'product-featured' : 'product-regular';
                $aosDelay = ($index % 6) * 100;
        ?>
            <article class="product-item rounded-2xl p-2 md:p-6 transition-all duration-300 group product-item <?php echo $categoryClass; ?> cursor-pointer flex flex-col h-full" 
                     onclick="window.location.href='product_detail.php?base_id=<?php echo $product['base_product_id']; ?>'" 
                     data-category="<?php echo $product['featured'] ? 'featured' : 'regular'; ?>" 
                     data-category-id="<?php echo $product['base_category_id'] ?: ''; ?>" 
                     data-price="<?php echo $product['price']; ?>"
                     data-aos="fade-up"
                     data-aos-delay="<?php echo $aosDelay; ?>">
                
                <!-- Badges -->
                <div class="absolute top-3 left-3 flex flex-col gap-2 z-20">
                    <?php if ($product['featured']): ?>
                        <span class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-bold shadow-lg flex items-center gap-1">
                            <i class="fas fa-star"></i> FEATURED
                        </span>
                    <?php elseif ($product['best_seller']): ?>
                        <span class="bg-gradient-to-r from-green-600 to-green-500 text-white px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-bold shadow-lg flex items-center gap-1">
                            <i class="fas fa-award"></i> BEST SELLER
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Image Container -->
                <div class="product-image-container relative mb-4 pt-[100%] rounded-xl">
                    <div class="absolute inset-0 flex items-center justify-center p-2 md:p-6">
                        <div class="absolute w-32 h-32 bg-orange-200 rounded-full filter blur-3xl opacity-0 group-hover:opacity-30 transition-opacity duration-500"></div>
                        <img src="<?php echo ($product['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="main-img max-w-full max-h-full object-contain relative z-10" 
                             style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.2));">
                    </div>
                </div>

                <!-- Content -->
                <div class="product-info flex-1 flex flex-col items-center text-center">
                    <div class="flex items-center justify-center gap-1 mb-2">
                        <div class="flex text-[10px] sm:text-xs text-yellow-400">
                            <?php echo generateStars(round($product['avg_rating'])); ?>
                        </div>
                        <span class="text-[10px] text-gray-400 font-medium">(<?php echo $product['review_count']; ?>)</span>
                    </div>
                    <h4 class="product-title text-sm md:text-lg font-bold text-gray-800 mb-3 line-clamp-2 leading-snug">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h4>
                    <div class="mt-auto">
                        <button class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-2.5 rounded-xl text-xs md:text-sm font-bold hover:from-orange-600 hover:to-orange-700 transition-all duration-300 shadow-sm group-hover:shadow-md transform group-hover:scale-105">
                            <i class="fas fa-eye mr-2"></i><?php echo htmlspecialchars(getSetting('view_details', $currentLanguage == 'km' ? 'មើលលម្អិត' : 'View Details')); ?>
                        </button>
                    </div>
                </div>
            </article>
        <?php endforeach; else: ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-coffee text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">No products available.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $productsHtml = ob_get_clean();

    // Start capturing HTML for pagination UI
    ob_start();
    if ($totalPages > 1): ?>
        <div class="mt-12 flex justify-center">
            <nav class="inline-flex items-center bg-gray-100/80 backdrop-blur-md p-1.5 rounded-[1.5rem] border border-gray-200/50 shadow-sm">
                <!-- Previous Page -->
                <button onclick="changePage(<?php echo $currentPage - 1; ?>)" <?php echo $currentPage <= 1 ? 'disabled' : ''; ?> class="w-10 h-10 flex items-center justify-center rounded-full <?php echo $currentPage <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-white hover:text-orange-600'; ?> transition-all duration-200">
                    <i class="fas fa-chevron-left text-sm"></i>
                </button>

                <!-- Page Numbers -->
                <div class="flex items-center px-2">
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    
                    // Always show at least 5 pages if available
                    if ($endPage - $startPage < 4) {
                        if ($startPage == 1) {
                            $endPage = min($totalPages, 5);
                        } else {
                            $startPage = max(1, $totalPages - 4);
                        }
                    }

                    if ($startPage > 1): ?>
                        <button onclick="changePage(1)" class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold text-gray-500 hover:bg-white hover:text-orange-600 transition-all duration-200">1</button>
                        <?php if ($startPage > 2): ?><span class="px-2 text-gray-400 text-xs italic">...</span><?php endif; ?>
                    <?php endif; ?>


                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <button onclick="changePage(<?php echo $i; ?>)" 
                           class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold transition-all duration-300 <?php echo $i == $currentPage ? 'bg-orange-500 text-white shadow-lg shadow-orange-200 scale-110' : 'text-gray-500 hover:bg-white hover:text-orange-600'; ?>">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><span class="px-2 text-gray-400 text-xs italic">...</span><?php endif; ?>
                        <button onclick="changePage(<?php echo $totalPages; ?>)" class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold text-gray-500 hover:bg-white hover:text-orange-600 transition-all duration-200"><?php echo $totalPages; ?></button>
                    <?php endif; ?>
                </div>

                <!-- Next Page -->
                <button onclick="changePage(<?php echo $currentPage + 1; ?>)" <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?> class="w-10 h-10 flex items-center justify-center rounded-full <?php echo $currentPage >= $totalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-white hover:text-orange-600'; ?> transition-all duration-200">
                    <i class="fas fa-chevron-right text-sm"></i>
                </button>
            </nav>
        </div>
    <?php endif;
    $paginationHtml = ob_get_clean();

    echo json_encode([
        'productsHtml' => $productsHtml,
        'paginationHtml' => $paginationHtml,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage
    ]);
    exit;
}

// Separate featured products and regular products
$featuredProducts = array_filter($products, function($product) {
    return $product['featured'] == 1;
});
$regularProducts = array_filter($products, function($product) {
    return $product['featured'] == 0;
});

// Compute Top Products: sort by avg_rating desc, then review_count desc, fallback to featured
$topProducts = $products;
usort($topProducts, function($a, $b) {
	if ($a['avg_rating'] == $b['avg_rating']) {
		if ($a['review_count'] == $b['review_count']) return ($b['featured'] <=> $a['featured']);
		return $b['review_count'] <=> $a['review_count'];
	}
	return $b['avg_rating'] <=> $a['avg_rating'];
});
$topProducts = array_slice($topProducts, 0, 6);
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>">
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
	
	html {
		scroll-behavior: smooth;
	}
	
	section[id] {
		scroll-margin-top: 80px; /* Offset for sticky header */
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


	/* Swiper Navigation Buttons */
	.swiper-button-next,
	.swiper-button-prev {
		border-radius: 50%;
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #f59e0b;
		font-size: 20px;
		font-weight: bold;
		transition: all 0.3s ease;
	}


	.swiper-button-next::after,
	.swiper-button-prev::after {
		font-size: 20px;
		font-weight: bold;
	}

	@media (max-width: 768px) {
		.swiper-button-next,
		.swiper-button-prev {
			width: 36px;
			height: 36px;
			font-size: 16px;
		}

		.swiper-button-next::after,
		.swiper-button-prev::after {
			font-size: 16px;
		}

	/* Hide swiper navigation buttons on mobile */
		.swiper-button-next,
		.swiper-button-prev {
			display: none !important;
		}
	}

	/* Featured Swiper Transparent Background */
	.featured-swiper .swiper-button-next,
	.featured-swiper .swiper-button-prev,
	.mobile-featured-swiper .swiper-button-next,
	.mobile-featured-swiper .swiper-button-prev {
		background: transparent !important;
		border: none !important;
		box-shadow: none !important;
	}

/* Product Info Label Styles */
#product-info-label,
#mobile-product-info-label,
#main-product-info-label,
#main-mobile-product-info-label {
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
	backdrop-filter: blur(8px);
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 20px;
}

#product-info-label::before,
#mobile-product-info-label::before,
#main-product-info-label::before,
#main-mobile-product-info-label::before {
	content: '';
	position: absolute;
	bottom: -6px;
	left: 50%;
	transform: translateX(-50%);
	width: 0;
	height: 0;
	border-left: 6px solid transparent;
	border-right: 6px solid transparent;
	border-top: 6px solid rgba(0, 0, 0, 0.6);
}

@media (max-width: 768px) {
	#product-info-label,
	#main-product-info-label {
		display: none;
	}
	#mobile-product-info-label,
	#main-mobile-product-info-label {
		display: block;
	}
}

@media (min-width: 769px) {
	#mobile-product-info-label,
	#main-mobile-product-info-label {
		display: none;
	}
	#product-info-label,
	#main-product-info-label {
		display: block;
	}
}

	/* Featured modal and floating button removed */

	@media (max-width: 360px) {
		/* More spacing for very small screens */
		#searchButton {
			margin-right: 12px !important;
		}
	}

	/* Mobile Swiper styles */
	.mobile-featured-swiper {
		padding-bottom: 25px;
	}

	.mobile-featured-swiper .swiper-slide {
		width: 100% !important;
		height: auto;
		transition: transform 0.3s ease;
	}

	.mobile-featured-swiper .swiper-slide-active {
		transform: scale(1.05);
		z-index: 2;
	}

	.mobile-featured-swiper .swiper-slide-next,
	.mobile-featured-swiper .swiper-slide-prev {
		transform: scale(0.95);
		opacity: 0.8;
		z-index: 1;
	}

	.mobile-featured-swiper .swiper-slide article {
		width: 100%;
		height: 320px;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		transition: transform 0.3s ease; /* removed box-shadow transition */
		overflow: hidden;
		cursor: pointer;
		border-radius: 0.75rem;
		margin: 0;
		background: transparent !important;
		box-shadow: none !important;
	}

	.mobile-featured-swiper .swiper-slide-active article {
		box-shadow: none !important;
		transform: translateY(-3px);
	}

	.mobile-featured-swiper .swiper-slide-next article,
	.mobile-featured-swiper .swiper-slide-prev article {
		box-shadow: none !important;
		opacity: 0.85;
	}
	.product-card-content {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		padding: 1rem 0;
	}

	.product-title {
		font-size: 1.125rem;
		font-weight: 600;
		color: #1f2937;
		margin-bottom: 0.5rem;
		line-height: 1.4;
		height: 2.8rem;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		text-overflow: ellipsis;
		text-align: center;
	}
	.product-description {
		color: #6b7280;
		font-size: 0.875rem;
		line-height: 1.5;
		margin-bottom: 1rem;
		flex: 1;
		height: 3rem;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		text-overflow: ellipsis;
		text-align: center;
	}

	/* Our Products Premium Card Styles */
	.product-item {
		background: transparent;
		border: none;
		box-shadow: none;
		transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
		position: relative;
		overflow: visible;
	}

	.product-item:hover {
		transform: translateY(-8px);
	}

	.product-item .product-image-container {
		position: relative;
		overflow: hidden;
		border-radius: 12px;
		background: transparent;
		transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
	}

	.product-item:hover .product-image-container {
		background: transparent;
	}

	.product-item img.main-img {
		transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
	}

	.product-item:hover img.main-img {
		/* JS handles the zoom now */
	}



	.product-item .product-info {
		padding-top: 15px;
	}

	.product-item .product-title {
		transition: color 0.3s ease;
	}

	.product-item:hover .product-title {
		color: #d97706;
	}

	/* Modal Animation Enhancement */
	#productModal .modal-content {
		transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1), opacity 0.4s ease;
		transform: scale(0.9) translateY(20px);
		opacity: 0;
	}

	#productModal:not(.hidden) .modal-content {
		transform: scale(1) translateY(0);
		opacity: 1;
	}

	/* Pulse effect forfeatured badges */
	.badge-pulse {
		animation: pulse-red 2s infinite;
	}

	@keyframes pulse-red {
		0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
		70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
		100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
	}

	/* Enhanced Modal Styles */
	#productModal {
		transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
		backdrop-filter: blur(15px);
		-webkit-backdrop-filter: blur(15px);
	}

	#productModal.hidden {
		opacity: 0;
		visibility: hidden;
		pointer-events: none;
	}

	#productModal:not(.hidden) {
		opacity: 1;
		visibility: visible;
	}

	#productModal .modal-content {
		border: 1px solid rgba(255, 255, 255, 0.2);
		box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
		border-radius: 2rem;
	}
	#productModal:not(.hidden) {
		to {
			opacity: 1;
		}
	}

	#productModal .modal-content {
		transform: scale(0.9) translateY(20px);
		transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
		box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
	}

	#productModal:not(.hidden) .modal-content {
		transform: scale(1) translateY(0);
		animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
	}

	@keyframes modalSlideIn {
		from {
			opacity: 0;
			transform: scale(0.9) translateY(20px);
		}
		to {
			opacity: 1;
			transform: scale(1) translateY(0);
		}
	}

	/* Font Awesome Brown Color */
	.text-brown-500 {
		color: #8B4513;
	}
	@media (max-width: 1024px) {
		#productModal .modal-content {
			margin: 1rem;
			max-width: calc(100vw - 2rem);
		}

		#productModal .grid {
			grid-template-columns: 1fr;
			gap: 2rem;
		}

		#productModal img {
			max-width: 250px;
			height: 250px;
		}
	}

	@media (max-width: 768px) {
		#productModal .modal-content {
			margin: 0.5rem;
			max-width: calc(100vw - 1rem);
			max-height: calc(100vh - 1rem);
		}

		#productModal img {
			max-width: 200px;
			height: 200px;
		}

		#productModal .p-8 {
			padding: 1.5rem;
		}

		#productModal .text-3xl {
			font-size: 1.75rem;
		}

		#productModal .text-2xl {
			font-size: 1.5rem;
		}

		#modalTitle {
			display: none;
		}

		#modalProductName {
			font-size: 1.5rem !important;
		}

		#closeModal {
			border-radius: 50% !important;
			width: 40px !important;
			height: 40px !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
		}

		#closeSearchModal {
			border-radius: 50% !important;
			width: 40px !important;
			height: 40px !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
		}
	}

	/* Modal hover effects */
	#productModal img:hover {
		transform: scale(1.05);
		transition: transform 0.3s ease;
	}

	#productModal .bg-gray-50:hover {
		background-color: rgba(249, 250, 251, 0.8);
		transition: background-color 0.3s ease;
	}

	#productModal button:hover {
		transform: translateY(-2px);
		transition: all 0.3s ease;
	}

	#productModal #closeModal:hover {
		background-color: rgba(0, 0, 0, 0.1);
		transform: rotate(90deg);
		transition: all 0.3s ease;
	}

	#productModal #closeModal:hover svg {
		transform: scale(1.1);
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

	.float-animation {
		animation: float 3s ease-in-out infinite;
	}

	@keyframes float {
		0%, 100% { transform: translateY(0); }
		50% { transform: translateY(-10px); }
	}

	.slide-in {
		animation: slideIn 1s ease-out;
	}

	@keyframes slideIn {
		from { transform: translateX(-50px); opacity: 0; }
		to { transform: translateX(0); opacity: 1; }
	}

	/* Mobile App-like Styles */
	@media (max-width: 768px) {
		/* Full-width sections on mobile */
		section {
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

/* Banner Swiper Styles */
.banner-swiper {
	padding-bottom: 40px;
	width: 100%;
}

.banner-swiper .swiper-slide {
	width: 100%;
	height: auto;
	transition: transform 0.3s ease;
}

.banner-swiper .swiper-slide-active {
	transform: scale(1.01);
}

/* Banner content responsive */
@media (max-width: 768px) {
	.banner-swiper .swiper-slide-active {
		transform: none;
	}

	.banner-swiper {
		background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
		border-radius: 0.75rem;
	}

	/* Ensure banner images fit properly on mobile */
	.banner-swiper .swiper-slide div {
		display: flex;
		align-items: center;
		justify-content: center;
		background-size: contain !important;
		background-repeat: no-repeat !important;
		background-position: center !important;
	}

	.banner-swiper .swiper-slide img {
		max-width: 100%;
		max-height: 100%;
		border-radius: 0.75rem;
	}

/* Mobile banner images should show full image without cropping */
@media (max-width: 768px) {
	.banner-swiper .swiper-slide img {
		object-fit: contain !important;
	}

	.banner-swiper .swiper-slide div {
		background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
		background-size: contain !important;
		background-repeat: no-repeat !important;
		background-position: center !important;
	}
}
}

/* IOS 26 Aesthetic Swiper Pagination */
.swiper-pagination {
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	gap: 8px !important;
	bottom: 30px !important;
	transition: all 0.3s ease;
}

.swiper-pagination-bullet {
	width: 8px !important;
	height: 8px !important;
	background: rgba(0, 0, 0, 0.12) !important;
	opacity: 1 !important;
	border-radius: 20px !important;
	transition: all 0.5s cubic-bezier(0.2, 1, 0.3, 1) !important;
	margin: 0 !important;
	border: none !important;
	position: relative;
}

.swiper-pagination-bullet-active {
	width: 28px !important;
	background: #ea580c !important;
	box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3) !important;
}

/* Special styling for dark backgrounds (like spotlight) */
.spotlight-swiper .swiper-pagination-bullet {
	background: rgba(0, 0, 0, 0.1) !important;
}

.spotlight-swiper .swiper-pagination-bullet-active {
	background: #ea580c !important;
}

/* Extra glass effect for pagination containers */
.banner-swiper .swiper-pagination,
.spotlight-swiper .swiper-pagination,
.category-swiper-syrup .swiper-pagination,
.category-swiper-powder .swiper-pagination {
	background: rgba(255, 255, 255, 0.2);
	backdrop-filter: blur(12px);
	-webkit-backdrop-filter: blur(12px);
	padding: 10px 18px !important;
	border-radius: 40px;
	border: 1px solid rgba(255, 255, 255, 0.3);
	width: auto !important;
	left: 50% !important;
	transform: translateX(-50%) !important;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
}

.banner-swiper .swiper-button-next,
.banner-swiper .swiper-button-prev {
	width: 36px;
	height: 36px;
	background: rgba(255, 255, 255, 0.9);
	border-radius: 50%;
	color: #d97706;
	transition: all 0.3s ease;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
	border: 1px solid rgba(0, 0, 0, 0.1);
}

.banner-swiper .swiper-button-next:hover,
.banner-swiper .swiper-button-prev:hover {
	background: #ea580c;
	color: white;
	transform: scale(1.05);
	box-shadow: 0 4px 12px rgba(234, 88, 12, 0.4);
}

.banner-swiper .swiper-button-next::after,
.banner-swiper .swiper-button-prev::after {
	font-size: 14px;
	font-weight: bold;
}

/* Mobile-specific banner styles */
@media (max-width: 768px) {
	.banner-swiper .swiper-button-next,
	.banner-swiper .swiper-button-prev {
		width: 32px;
		height: 32px;
	}

	.banner-swiper .swiper-button-next::after,
	.banner-swiper .swiper-button-prev::after {
		font-size: 12px;
	}

/* Mobile banner background fix: make slides full-bleed and remove gradient band */
@media (max-width: 768px) {
	.banner-swiper {
		background: transparent !important;
		border-radius: 0 !important;
		padding-bottom: 12px !important;
	}

	/* Keep using the inline background-image on the inner divs, don't override it */
	.banner-swiper .swiper-slide div {
		padding: 0 !important;
		display: block !important;
		background-size: cover !important;
		background-position: center !important;
		background-repeat: no-repeat !important;
		height: 180px !important;
		border-radius: 0.75rem !important;
	}

	/* If any <img> exist, ensure they cover as fallback */
	.banner-swiper .swiper-slide img {
		width: 100% !important;
		height: 180px !important;
		object-fit: cover !important;
		border-radius: 0.75rem !important;
		display: block !important;
	}

	.banner-swiper .swiper-pagination {
		bottom: 8px !important;
	}
}

	.banner-swiper .swiper-pagination-bullet {
		width: 8px;
		height: 8px;
	}
}



	/* iOS 18 Style Modal Styles */
	#searchModal,
	#productModal {
		backdrop-filter: blur(20px);
		-webkit-backdrop-filter: blur(20px);
		background: rgba(0, 0, 0, 0.4);
	}

	#searchModal .modal-content,
	#productModal .modal-content {
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
	#productModal:not(.hidden) .modal-content {
		transform: scale(1) translateY(0);
		animation: iosModalSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
	}

	/* iOS 18 Modal Header */
	#searchModal .modal-content > div:first-child,
	#productModal .modal-content > div:first-child {
		border-bottom: 1px solid rgba(0, 0, 0, 0.1);
		background: linear-gradient(180deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.4) 100%);
		backdrop-filter: blur(20px);
		border-radius: 28px 28px 0 0;
		padding: 24px;
	}

	/* iOS 18 Close Button */
	#searchModal button[id*="close"],
	#productModal button[id*="close"] {
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
	#productModal button[id*="close"]:hover {
		background: rgba(142, 142, 147, 0.24);
		transform: scale(1.05);
	}

	#searchModal button[id*="close"]:active,
	#productModal button[id*="close"]:active {
		transform: scale(0.95);
		background: rgba(142, 142, 147, 0.36);
	}

	/* iOS 18 Modal Content */
	#searchModal .modal-content > div:not(:first-child),
	#productModal .modal-content > div:not(:first-child) {
		padding: 24px;
	}

	/* iOS 18 Typography */
	#searchModal h3,
	#productModal h3 {
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
		#productModal .modal-content {
			margin: 16px;
			max-width: calc(100vw - 32px);
			max-height: calc(100vh - 32px);
			border-radius: 24px;
		}

		#searchModal .modal-content > div:first-child,
		#productModal .modal-content > div:first-child {
			padding: 20px;
			border-radius: 24px 24px 0 0;
		}

		#searchModal .modal-content > div:not(:first-child),
		#productModal .modal-content > div:not(:first-child) {
			padding: 20px;
		}
	}

	/* Category filter styles */
	.category-btn.active {
		background: #fef3c7;
		color: #000000;
		font-weight: 600;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}

	/* Mobile categories modal styles */
	.category-btn-mobile.active {
		background: #fef3c7;
		color: #000000;
		font-weight: 600;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}

	/* Floating button animation */
	@keyframes bounce {
		0%, 20%, 50%, 80%, 100% {
			transform: translateY(0) translateX(-50%);
		}
		40% {
			transform: translateY(-10px) translateX(-50%);
		}
		60% {
			transform: translateY(-5px) translateX(-50%);
		}
	}

	#floatingCategoriesBtn {
		animation: bounce 2s infinite;
	}



	/* Related Products Zoom Styles */
	.related-product-item {
		position: relative;
		overflow: hidden;
	}

	.related-product-zoom-overlay {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.8);
		display: none;
		justify-content: center;
		align-items: center;
		z-index: 9999;
		cursor: pointer;
	}

	.related-product-zoom-overlay.active {
		display: flex;
	}

	.related-product-zoom-overlay img {
		max-width: 90%;
		max-height: 90%;
		object-fit: contain;
		border-radius: 8px;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
	}

	.related-product-magnify {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		background: rgba(0, 0, 0, 0.7);
		color: white;
		border-radius: 50%;
		width: 24px;
		height: 24px;
		display: flex;
		align-items: center;
		justify-content: center;
		opacity: 0;
		transition: opacity 0.3s ease;
		pointer-events: none;
		font-size: 12px;
	}

	@media (min-width: 768px) {
		.related-product-magnify {
			width: 30px;
			height: 30px;
			font-size: 14px;
		}
	}

	.related-product-item:hover .related-product-magnify {
		opacity: 1;
	}
	/* Safe area padding for bottom nav */
	.pb-safe {
		padding-bottom: 20px;
		padding-bottom: env(safe-area-inset-bottom, 20px);
	}

	/* iOS-style status bar spacing */
	@supports (padding-top: env(safe-area-inset-top)) {
		@media (max-width: 768px) {
			body {
				padding-top: env(safe-area-inset-top);
			}
		}
	}
	</style>
	<!-- Swiper CSS -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
	<!-- AOS CSS -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
	<!-- Hide all Swiper navigation buttons site-wide -->
	<style>
		.swiper-button-next,
		.swiper-button-prev { display: none !important; }
	</style>
	<?php
	$cssFile = realpath(__DIR__ . '/../../public/css/output.css');
	$useOutput = false; // $cssFile && file_exists($cssFile) && filesize($cssFile) > 50;
	if ($useOutput) {
		echo '<link rel="stylesheet" href="css/output.css">';
	} else {
		echo '<script src="https://cdn.tailwindcss.com"></script>';
	}
	?>
	<style>
	/* Ensure nutrition value columns stay inline and do not wrap */
	.injected-custom-field .nutrition-rows .col-span-7 {
		display: flex;
		gap: 1rem;
		justify-content: flex-end;
		align-items: center;
		flex-wrap: nowrap;
		font-size: 0.875rem; /* 14px */
	}
	.injected-custom-field .nutrition-rows .col-span-5 {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		font-size: 0.875rem; /* 14px */
		font-weight: 500;
	}
	.injected-custom-field .nutrition-rows .col-span-7 > div {
		font-size: 0.875rem; /* 14px */
		font-weight: 400;
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
				<a href="product.php#products" class="<?php echo ($current_page == 'product.php' || $current_page == 'product_detail.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_product', $currentLanguage == 'km' ? 'ផលិតផល' : 'Product')); ?></a>
				<a href="features.php" class="<?php echo ($current_page == 'features.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_features', $currentLanguage == 'km' ? 'លក្ខណៈពិសេស' : 'Features')); ?></a>
				<a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_reviews', $currentLanguage == 'km' ? 'ការពិនិត្យ' : 'Reviews')); ?></a>
				<a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_about', $currentLanguage == 'km' ? 'អំពីយើង' : 'About')); ?></a>
			</nav>
			
			<!-- Mobile Actions -->
			<div class="flex items-center gap-3">
				<!-- Language Switcher -->
				<button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-full px-4 py-2 transition-all active:scale-95 border border-gray-100 shadow-sm" title="<?php echo getCurrentLanguage() === 'km' ? 'ប្តូរភាសា' : 'Switch Language'; ?>">
					<img src="<?php echo getCurrentLanguage() === 'en' ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80' : 'https://cdn-icons-png.flaticon.com/512/16022/16022033.png'; ?>" 
						 alt="<?php echo getCurrentLanguage() === 'en' ? 'English' : 'Khmer'; ?>" 
						 class="w-6 h-6 object-cover rounded-full shadow-sm">
					<span class="font-bold text-sm text-gray-700"><?php echo getCurrentLanguage() === 'en' ? 'EN' : 'KM'; ?></span>
				</button>
				<button id="searchButton" class="w-11 h-11 flex items-center justify-center text-gray-600 hover:text-white hover:bg-black rounded-full transition-all active:scale-90 bg-gray-50 border border-gray-100 shadow-sm" title="<?php echo getCurrentLanguage() === 'km' ? 'ស្វែងរក' : 'Search'; ?>">
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
					</svg>
				</button>
			</div>
		</div>
	</header>



	<!-- featured modal removed -->

	<!-- Contact Us Modal -->
	<div id="contactModal" class="fixed inset-0 z-50 hidden items-center justify-center">
		<div class="absolute inset-0 bg-black bg-opacity-60" onclick="closeContactModal()"></div>
		<div class="relative w-full max-w-2xl max-h-[90vh] bg-white shadow-2xl rounded-lg overflow-hidden modal-content transform scale-95 transition-transform duration-300 ease-out">
			<div class="bg-orange-600 p-6 text-white">
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


	<!-- Banner Slider removed (moved above replacing hero) -->

	<?php 
	// Select products for the spotlight slider
	$spotlightProducts = $allAvailableProducts;
	
	if (!empty($spotlightProducts)): 
	?>
	<section class="relative w-full py-12 md:py-24 overflow-hidden bg-white">


		<div class="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
			<!-- Swiper Container -->
			<div class="swiper spotlight-swiper">
				<div class="swiper-wrapper">
					<?php foreach ($spotlightProducts as $index => $spotlightProduct): ?>
					<div class="swiper-slide pb-12">
						<div class="flex flex-col md:flex-row items-center gap-8 md:gap-16 lg:gap-24">
							
							<!-- Product Image (Left on Desktop) -->
							<div class="w-full md:w-1/2 relative group">
								<div class="relative z-10 flex justify-center items-center">
									<!-- Circular background behind image -->
									<div class="absolute w-[280px] h-[280px] md:w-[450px] md:h-[450px] bg-gradient-to-tr from-gray-100 to-gray-50 rounded-full z-0 transform transition-transform duration-700 group-hover:scale-105"></div>
									
									<!-- Main Image -->
									<img src="<?php echo ($spotlightProduct['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
										 alt="<?php echo htmlspecialchars($spotlightProduct['name']); ?>" 
										 class="relative z-10 w-64 md:w-96 max-w-full drop-shadow-2xl transform transition-all duration-500 group-hover:-rotate-3 group-hover:scale-110 cursor-pointer"
										 onclick="window.location.href='product_detail.php?base_id=<?php echo $spotlightProduct['base_product_id']; ?>'">
									
									<!-- Floating Stats Card -->
									<div class="absolute -bottom-4 md:bottom-10 -right-2 md:right-10 bg-white/90 backdrop-blur-md p-3 md:p-4 rounded-2xl shadow-xl z-20 border border-white/50 animate-bounce" style="animation-duration: 3s;">
										<div class="flex items-center gap-3">
											<div class="bg-orange-100 p-2 rounded-full text-orange-600">
												<i class="fas fa-fire text-lg md:text-xl"></i>
											</div>
											<div>
												<p class="text-xs text-gray-500 font-semibold uppercase">Popularity</p>
												<p class="text-sm md:text-base font-bold text-gray-800">
													<?php 
													if ($spotlightProduct['featured']) {
														echo "Featured Item";
													} elseif ($spotlightProduct['avg_rating'] >= 4.5) {
														echo "Top Rated";
													} else {
														echo "Customer Favorite";
													}
													?>
												</p>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Product Content (Right on Desktop) -->
							<div class="w-full md:w-1/2 text-center md:text-left">
								<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs md:text-sm font-bold mb-4 md:mb-6">
									<span class="bg-orange-500 w-2 h-2 rounded-full animate-ping"></span>
									<?php echo $currentLanguage == 'km' ? 'បណ្តុំផលិតផលពេញនិយម' : 'Signature Collection'; ?>
								</div>
								
								<h2 class="text-3xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4 md:mb-6 font-freeman leading-tight">
									<?php echo htmlspecialchars($spotlightProduct['name']); ?>
								</h2>
								
								<div class="flex items-center justify-center md:justify-start gap-4 mb-6">
									<div class="flex text-yellow-400 text-lg md:text-xl">
										<?php echo generateStars(5); ?>
									</div>
									<span class="text-gray-400 text-sm md:text-base font-medium">|</span>
									<span class="text-gray-600 text-sm md:text-base"><?php echo $spotlightProduct['review_count']; ?> <?php echo htmlspecialchars(getSetting('global_reviews', $currentLanguage == 'km' ? 'ការពិនិត្យសកល' : 'Global Reviews')); ?></span>
								</div>

								<p class="text-gray-600 text-base md:text-xl mb-8 leading-relaxed max-w-xl mx-auto md:mx-0">
									<?php echo htmlspecialchars(substr($spotlightProduct['description'], 0, 150)) . '...'; ?>
								</p>

								<div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start items-center">
									<a href="#products" 
											class="bg-gradient-to-r from-orange-500 to-red-500 text-white px-8 py-3 md:py-4 rounded-full font-bold shadow-lg hover:shadow-orange-500/30 transform hover:-translate-y-1 transition-all duration-300 flex items-center gap-2 w-full sm:w-auto justify-center text-center no-underline cursor-pointer">
										<span><?php echo $currentLanguage == 'km' ? 'មើលផលិតផល' : 'View Product'; ?></span>
										<i class="fas fa-arrow-right"></i>
									</a>
									
									<a href="#features" class="group flex items-center gap-2 text-gray-500 hover:text-orange-600 font-medium transition-colors px-6 py-3">
										<div class="w-10 h-10 rounded-full border-2 border-gray-200 group-hover:border-orange-500 flex items-center justify-center transition-colors">
											<i class="fas fa-play text-xs ml-1"></i>
										</div>
										<span><?php echo $currentLanguage == 'km' ? 'ទស្សនាវីដេអូ' : 'Watch Video'; ?></span>
									</a>
								</div>
								
								<!-- Mini Features Grid -->
								<div class="grid grid-cols-3 gap-4 mt-8 md:mt-12 pt-6 md:pt-8 w-full">
									<div class="text-center md:text-left">
										<h4 class="font-bold text-gray-900 text-lg">100%</h4>
										<p class="text-xs text-gray-500">Organic</p>
									</div>
									<div class="text-center md:text-left">
										<h4 class="font-bold text-gray-900 text-lg">Premium</h4>
										<p class="text-xs text-gray-500">Quality</p>
									</div>
									<div class="text-center md:text-left">
										<h4 class="font-bold text-gray-900 text-lg">Fast</h4>
										<p class="text-xs text-gray-500">Delivery</p>
									</div>
								</div>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<!-- Carousel Pagination -->
				<div class="swiper-pagination !bottom-0"></div>
			</div>
		</div>
	</section>
	
	<!-- Initialize Spotlight Swiper -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			new Swiper('.spotlight-swiper', {
				loop: true,
				autoplay: {
					delay: 5000,
					disableOnInteraction: false,
				},
				speed: 800,
				effect: 'fade',
				fadeEffect: {
					crossFade: true
				},
				pagination: {
					el: '.spotlight-swiper .swiper-pagination',
					clickable: true,
				},
			});
		});
	</script>
	<?php endif; ?>

	<!-- Category Zig-Zag Spotlight (Syrup & Powder) -->
	<?php
	// Robustly resolve categories across languages
	// Strategy: Find a "marker" product that belongs to the English category, get its base_product_id,
	// then find that product in the current language list to identify the current category_id.
	
	function getBaseCategoryByTarget($pdo, $targetName) {
		$stmt = $pdo->prepare("
			SELECT base_category_id 
			FROM categories 
			WHERE language = 'en' AND (name = ? OR name LIKE ?) 
			ORDER BY (CASE WHEN name = ? THEN 0 ELSE 1 END) ASC, id ASC 
			LIMIT 1
		");
		$stmt->execute([$targetName, '%' . $targetName . '%', $targetName]);
		$cat = $stmt->fetch();
		return $cat ? $cat['base_category_id'] : null;
	}

	$syrupBaseId = getBaseCategoryByTarget($pdo, 'Syrup');
	$powderBaseId = getBaseCategoryByTarget($pdo, 'Powder');
	$beanBaseId = getBaseCategoryByTarget($pdo, 'Bean') ?: getBaseCategoryByTarget($pdo, 'Coffee');

	// Localized category details for UI (titles etc)
	$syrupCat = null;
	$powderCat = null;
	$beanCat = null;
	
	foreach ($categories as $cat) {
		if ($cat['base_category_id'] == $syrupBaseId) $syrupCat = $cat;
		if ($cat['base_category_id'] == $powderBaseId) $powderCat = $cat;
		if ($cat['base_category_id'] == $beanBaseId) $beanCat = $cat;
	}

	// Get ALL products for each category
	$syrupProducts = [];
	$powderProducts = [];

	// Fetch selected products from settings
	$selectedSyrupIds = json_decode(getSetting('syrup_collection_products', '[]'), true) ?: [];
	$selectedPowderIds = json_decode(getSetting('powder_selection_products', '[]'), true) ?: [];

	if ($syrupBaseId) {
		$foundSyrupProducts = [];
		foreach ($allAvailableProducts as $p) {
			$pBaseCatId = $p['base_category_id'] ?? null;
			if ($pBaseCatId != null && (string)$pBaseCatId === (string)$syrupBaseId) {
				if (!empty($selectedSyrupIds)) {
					if (in_array((string)$p['base_product_id'], array_map('strval', $selectedSyrupIds))) {
						$foundSyrupProducts[] = $p;
					}
				} else {
					$syrupProducts[] = $p;
				}
			}
		}
		
		if (!empty($selectedSyrupIds)) {
			// Sort according to selection order
			$idOrder = array_map('strval', $selectedSyrupIds);
			usort($foundSyrupProducts, function($a, $b) use ($idOrder) {
				$posA = array_search((string)$a['base_product_id'], $idOrder);
				$posB = array_search((string)$b['base_product_id'], $idOrder);
				return ($posA === false ? 999 : $posA) - ($posB === false ? 999 : $posB);
			});
			$syrupProducts = $foundSyrupProducts;
		} else {
			// If not manually selected, limit to newest 15 to keep it fresh
			$syrupProducts = array_slice($syrupProducts, 0, 15);
		}
	}

	if ($powderBaseId) {
		$foundPowderProducts = [];
		foreach ($allAvailableProducts as $p) {
			$pBaseCatId = $p['base_category_id'] ?? null;
			if ($pBaseCatId != null && (string)$pBaseCatId === (string)$powderBaseId) {
				if (!empty($selectedPowderIds)) {
					if (in_array((string)$p['base_product_id'], array_map('strval', $selectedPowderIds))) {
						$foundPowderProducts[] = $p;
					}
				} else {
					$powderProducts[] = $p;
				}
			}
		}

		if (!empty($selectedPowderIds)) {
			// Sort according to selection order
			$idOrder = array_map('strval', $selectedPowderIds);
			usort($foundPowderProducts, function($a, $b) use ($idOrder) {
				$posA = array_search((string)$a['base_product_id'], $idOrder);
				$posB = array_search((string)$b['base_product_id'], $idOrder);
				return ($posA === false ? 999 : $posA) - ($posB === false ? 999 : $posB);
			});
			$powderProducts = $foundPowderProducts;
		} else {
			// If not manually selected, limit to newest 15 to keep it fresh
			$powderProducts = array_slice($powderProducts, 0, 15);
		}
	}

	$beanProducts = [];
	if ($beanBaseId) {
		foreach ($allAvailableProducts as $p) {
			$pBaseCatId = $p['base_category_id'] ?? null;
			if ($pBaseCatId != null && (string)$pBaseCatId === (string)$beanBaseId) {
				$beanProducts[] = $p;
			}
		}
	}

	// Ensure we have enough slides for smooth looping (Duplicate to at least 8 items relative to view)
	function ensureLoopBuffer(&$array, $minCount = 12) {
		$count = count($array);
		if ($count > 0 && $count < $minCount) {
			$original = $array;
			while (count($array) < $minCount) {
				$array = array_merge($array, $original);
			}
			// Trim to reasonable max if exponential growth went wild (not strictly necessary with minCount=8 but safe)
			// $array = array_slice($array, 0, max($minCount, 15)); 
		}
	}

	ensureLoopBuffer($syrupProducts);
	ensureLoopBuffer($powderProducts);
	ensureLoopBuffer($beanProducts);
	
	// Only display if we have categories with products
	if ((!empty($syrupProducts)) || (!empty($powderProducts)) || (!empty($beanProducts))):
	?>
	<section class="py-16 md:py-24 bg-white relative overflow-hidden">


		<div class="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
			
			<!-- Section Header -->
			<div class="text-center mb-16" data-aos="fade-up">
				<span class="text-orange-600 font-bold tracking-wider uppercase text-sm mb-2 block"><?php echo getCurrentLanguage() === 'km' ? 'បណ្តុំផលិតផលសម្រិតសម្រាំង' : 'Curated Collections'; ?></span>
				<h3 class="text-3xl md:text-5xl font-bold text-gray-900 font-freeman"><?php echo getCurrentLanguage() === 'km' ? 'ស្វែងយល់អំពីផលិតផលពិសេសៗរបស់យើង' : 'Discover Our Specialties'; ?></h3>
			</div>

			<div class="flex flex-col gap-16 md:gap-32">
				
				<!-- Syrup Section (Left Carousel, Right Text) -->
				<?php if (!empty($syrupProducts)): ?>
				<div class="flex flex-col md:flex-row items-center gap-8 md:gap-16 group" data-aos="fade-right">
					<div class="w-full md:w-1/2 relative">
							<div class="aspect-[4/3] md:aspect-[4/3] aspect-square rounded-3xl relative">
								<!-- Swiper Container -->
								<div class="swiper category-swiper-syrup h-full w-full">
									<div class="swiper-wrapper">
										<?php foreach ($syrupProducts as $p): ?>
										<div class="swiper-slide cursor-pointer pb-12" onclick="window.location.href='product_detail.php?base_id=<?php echo $p['base_product_id']; ?>'">
											<img src="<?php echo ($p['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
												 alt="<?php echo htmlspecialchars($p['name']); ?>" 
												 class="w-full h-full object-contain object-center transform transition-transform duration-1000 p-2 md:p-4"
												 style="filter: drop-shadow(0 5px 5px rgba(0,0,0,0.6)) drop-shadow(0 5px 5px rgba(0,0,0,0.3))">
										<div class="absolute bottom-16 left-0 right-0 text-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
											<span class="bg-black/70 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg whitespace-nowrap"><?php echo htmlspecialchars($p['name']); ?></span>
										</div>
									</div>
									<?php endforeach; ?>
								</div>
								<div class="swiper-pagination !bottom-4"></div>
							</div>
							
							<!-- Floating Badge -->
							<div class="absolute top-6 left-6 bg-white/95 backdrop-blur px-6 py-3 rounded-full shadow-lg z-20 pointer-events-none">
								<span class="text-orange-600 font-bold flex items-center gap-2">
									<i class="fas fa-tint"></i> Top Quality
								</span>
							</div>
						</div>

					</div>

					<div class="w-full md:w-1/2 text-center md:text-left">
						<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-freeman">
							<?php 
								$syrupTitle = getSetting('syrup_collection_title');
								if (empty($syrupTitle)) {
									$syrupTitle = htmlspecialchars($syrupCat['name']) . ' Collection';
								} else {
									$syrupTitle = htmlspecialchars($syrupTitle);
								}
								echo $syrupTitle;
							?>
						</h2>
						<p class="text-gray-600 text-lg leading-relaxed mb-8">
							<?php echo htmlspecialchars(getSetting('syrup_collection_description', 'Enhance your beverages with our rich, flavorful syrups. Crafted for perfection, our collection brings a new dimension of taste to your coffee, cocktails, and desserts. Experience the difference of true quality.')); ?>
						</p>
						
						<ul class="space-y-4 mb-8 text-left max-w-md mx-auto md:mx-0">
							<?php 
								$featuresJson = getSetting('syrup_collection_features');
								if (!empty($featuresJson)) {
									$featuresList = json_decode($featuresJson, true) ?: [];
								} else {
									// Fallback/Legacy
									$featuresList = [];
									for ($i = 1; $i <= 3; $i++) {
										$f = getSetting('syrup_collection_feature_' . $i);
										if ($f) $featuresList[] = $f;
									}
									if (empty($featuresList)) {
										$featuresList = [
											'Natural ingredients & authentic flavors',
											'Perfect consistency for mixing',
											'Wide variety of classic & exotic options'
										];
									}
								}
								foreach ($featuresList as $text):
							?>
							<li class="flex items-start gap-3 text-gray-700">
								<div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-50 border border-green-200 flex items-center justify-center text-green-600 text-[10px] mt-0.5 shadow-sm transition-transform group-hover:scale-110">
									<i class="fas fa-check"></i>
								</div>
								<span><?php echo htmlspecialchars($text); ?></span>
							</li>
							<?php endforeach; ?>
						</ul>

						<button onclick="filterMobileCategory(<?php echo $syrupCat['base_category_id'] ?? ''; ?>)" class="px-8 py-3 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg flex items-center gap-2 mx-auto md:mx-0">
							<?php echo htmlspecialchars(getSetting('explore_syrups', 'Explore Syrups')); ?> <i class="fas fa-arrow-right"></i>
						</button>
					</div>
				</div>

				<!-- Syrup Products Carousel -->

				<?php endif; ?>

				<!-- Powder Section (Right Carousel, Left Text) -->
				<?php if (!empty($powderProducts)): ?>
				<div class="flex flex-col md:flex-row-reverse items-center gap-8 md:gap-16 group" data-aos="fade-left">
					<div class="w-full md:w-1/2 relative">
						<div class="aspect-[4/3] md:aspect-[4/3] aspect-square rounded-3xl relative">
							<!-- Swiper Container -->
							<div class="swiper category-swiper-powder h-full w-full">
								<div class="swiper-wrapper">
									<?php foreach ($powderProducts as $p): ?>
									<div class="swiper-slide cursor-pointer pb-12" onclick="window.location.href='product_detail.php?base_id=<?php echo $p['base_product_id']; ?>'">
										<img src="<?php echo ($p['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
											 alt="<?php echo htmlspecialchars($p['name']); ?>" 
											 class="w-full h-full object-contain object-center transform transition-transform duration-1000 p-2 md:p-4"
											 style="filter: drop-shadow(0 5px 5px rgba(0,0,0,0.6)) drop-shadow(0 5px 5px rgba(0,0,0,0.3))">
										<div class="absolute bottom-16 left-0 right-0 text-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
											<span class="bg-black/70 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg whitespace-nowrap"><?php echo htmlspecialchars($p['name']); ?></span>
										</div>
									</div>
									<?php endforeach; ?>
								</div>
								<div class="swiper-pagination !bottom-4"></div>
							</div>

							<div class="absolute bottom-6 right-6 bg-white/95 backdrop-blur px-6 py-3 rounded-full shadow-lg z-20 pointer-events-none">
								<span class="text-brown-600 font-bold flex items-center gap-2">
									<i class="fas fa-award"></i> Premium Grade
								</span>
							</div>
						</div>

					</div>

					<div class="w-full md:w-1/2 text-center md:text-left md:pl-8">
						<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-freeman">
							<?php 
								$powderTitle = getSetting('powder_selection_title');
								if (empty($powderTitle)) {
									$powderTitle = htmlspecialchars($powderCat['name']) . ' Selection';
								} else {
									$powderTitle = htmlspecialchars($powderTitle);
								}
								echo $powderTitle;
							?>
						</h2>
						<p class="text-gray-600 text-lg leading-relaxed mb-8">
							<?php echo htmlspecialchars(getSetting('powder_selection_description', 'Create smooth, velvety frappes and creamy signature drinks with our premium powders. Designed for professionals, loved by everyone. Unlock the secret to rich texture and unforgettable taste.')); ?>
						</p>

						<ul class="space-y-4 mb-8 text-left max-w-md mx-auto md:mx-0">
							<?php 
								$pFeaturesJson = getSetting('powder_selection_features');
								if (!empty($pFeaturesJson)) {
									$pFeaturesList = json_decode($pFeaturesJson, true) ?: [];
								} else {
									// Fallback/Legacy
									$pFeaturesList = [];
									for ($i = 1; $i <= 3; $i++) {
										$f = getSetting('powder_selection_feature_' . $i);
										if ($f) $pFeaturesList[] = $f;
									}
									if (empty($pFeaturesList)) {
										$pFeaturesList = ['Vanilla', 'Chocolate', 'Frappe Base'];
									}
								}
								foreach ($pFeaturesList as $text):
							?>
							<li class="flex items-start gap-3 text-gray-700">
								<div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-50 border border-green-200 flex items-center justify-center text-green-600 text-[10px] mt-0.5 shadow-sm transition-transform group-hover:scale-110">
									<i class="fas fa-check"></i>
								</div>
								<span><?php echo htmlspecialchars($text); ?></span>
							</li>
							<?php endforeach; ?>
						</ul>

						<button onclick="filterMobileCategory(<?php echo $powderCat['base_category_id'] ?? ''; ?>)" class="px-8 py-3 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg flex items-center gap-2 mx-auto md:mx-0">
							<?php echo htmlspecialchars(getSetting('explore_powders', 'Explore Powders')); ?> <i class="fas fa-arrow-right"></i>
						</button>
					</div>
				</div>

				<!-- Powder Products Carousel -->

				<?php endif; ?>

				<!-- Bean Section (Left Carousel, Right Text) -->
				<?php if (!empty($beanProducts)): ?>
				<div class="flex flex-col md:flex-row items-center gap-8 md:gap-16 group" data-aos="fade-right">
					<div class="w-full md:w-1/2 relative">
						<div class="aspect-[4/3] md:aspect-[4/3] aspect-square rounded-3xl relative">
							<!-- Swiper Container -->
							<div class="swiper category-swiper-bean h-full w-full">
								<div class="swiper-wrapper">
									<?php foreach ($beanProducts as $p): ?>
									<div class="swiper-slide cursor-pointer pb-12" onclick="window.location.href='product_detail.php?base_id=<?php echo $p['base_product_id']; ?>'">
										<img src="<?php echo ($p['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
											 alt="<?php echo htmlspecialchars($p['name']); ?>" 
											 class="w-full h-full object-contain object-center transform transition-transform duration-1000 p-2 md:p-4"
											 style="filter: drop-shadow(0 5px 5px rgba(0,0,0,0.6)) drop-shadow(0 5px 5px rgba(0,0,0,0.3))">
										<div class="absolute bottom-16 left-0 right-0 text-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
											<span class="bg-black/70 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg whitespace-nowrap"><?php echo htmlspecialchars($p['name']); ?></span>
										</div>
									</div>
									<?php endforeach; ?>
								</div>
								<div class="swiper-pagination !bottom-4"></div>
							</div>

							<div class="absolute top-6 left-6 bg-white/95 backdrop-blur px-6 py-3 rounded-full shadow-lg z-20 pointer-events-none">
								<span class="text-orange-600 font-bold flex items-center gap-2">
									<i class="fas fa-leaf"></i> 100% Arabica
								</span>
							</div>
						</div>
					</div>

					<div class="w-full md:w-1/2 text-center md:text-left">
						<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-freeman">
							<?php 
								$beanTitle = getSetting('bean_collection_title');
								if (empty($beanTitle)) {
									$beanTitle = htmlspecialchars($beanCat['name']) . ' Collection';
								} else {
									$beanTitle = htmlspecialchars($beanTitle);
								}
								echo $beanTitle;
							?>
						</h2>
						<p class="text-gray-600 text-lg leading-relaxed mb-8">
							<?php echo htmlspecialchars(getSetting('bean_collection_description', 'Discover the soul of our coffee with our premium beans. Sourced from the finest altitudes and roasted to perfection, each bean tells a story of craftsmanship and passion. Elevate your morning ritual.')); ?>
						</p>

						<div class="flex flex-wrap gap-3 justify-center md:justify-start mb-8">
							<span class="px-4 py-2 bg-orange-50 rounded-lg text-sm font-bold text-orange-700 border border-orange-100">Freshly Roasted</span>
							<span class="px-4 py-2 bg-orange-50 rounded-lg text-sm font-bold text-orange-700 border border-orange-100">Artisan Blends</span>
							<span class="px-4 py-2 bg-orange-50 rounded-lg text-sm font-bold text-orange-700 border border-orange-100">Single Origin</span>
						</div>

						<button onclick="filterMobileCategory(<?php echo $beanCat['base_category_id'] ?? ''; ?>)" class="px-8 py-3 bg-gray-900 text-white rounded-full font-bold hover:bg-orange-600 transition-colors shadow-lg flex items-center gap-2 mx-auto md:mx-0">
							<?php echo htmlspecialchars(getSetting('explore_beans', 'Explore Beans')); ?> <i class="fas fa-arrow-right"></i>
						</button>
					</div>
				</div>

				<!-- Bean Products Carousel -->
				<div class="relative mt-8" data-aos="fade-up">
					<div class="swiper category-product-cards-swiper-bean pb-12">
						<div class="swiper-wrapper">
							<?php foreach ($beanProducts as $p): ?>
							<div class="swiper-slide h-auto max-w-[280px]">
								<article class="bg-white rounded-3xl p-4 transition-all duration-300 group cursor-pointer flex flex-col h-full hover:shadow-xl border border-gray-50 relative overflow-hidden" 
										onclick="window.location.href='product_detail.php?base_id=<?php echo $p['base_product_id']; ?>'">
									<div class="product-image-container relative mb-3 pt-[100%] rounded-2xl bg-gray-50 overflow-hidden">
										<div class="absolute inset-0 flex items-center justify-center p-4">
											<img src="<?php echo ($p['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
												 alt="<?php echo htmlspecialchars($p['name']); ?>" 
												 class="w-full h-full object-contain transform transition-transform duration-500 group-hover:scale-110 drop-shadow-md">
										</div>
									</div>
									<div class="flex-1 flex flex-col pt-2 pb-1">
										<h4 class="font-bold text-gray-900 text-sm mb-1 group-hover:text-orange-600 transition-colors line-clamp-2"><?php echo htmlspecialchars($p['name']); ?></h4>
										<!-- Price removed as requested -->
										<!-- <div class="mt-auto pt-2 border-t border-gray-100 flex items-center justify-between">
											<span class="text-orange-600 font-bold text-sm">$<?php echo number_format($p['price'], 2); ?></span>
											<span class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 group-hover:bg-orange-500 group-hover:text-white transition-all">
												<i class="fas fa-plus text-[10px]"></i>
											</span>
										</div> -->
									</div>
								</article>
							</div>
							<?php endforeach; ?>
						</div>
						<div class="swiper-pagination !-bottom-2"></div>
					</div>
				</div>
				<?php endif; ?>

			</div>
		</div>
	</section>
	<?php endif; ?>





	<!-- Product List -->
	<section id="products" class="px-3 py-10 md:px-6 md:py-20 lg:py-24 bg-white">
		<div class="max-w-7xl mx-auto">
			<div class="text-center mb-10 md:mb-16">
				<h3 class="text-3xl md:text-5xl lg:text-6xl font-black text-gray-900 mb-4 flex items-center justify-center tracking-tight">
					<i class="fas fa-coffee text-yellow-500 mr-4"></i><?php echo htmlspecialchars(getSetting('our_products', 'Our Products')); ?>
				</h3>
				<p class="text-gray-500 text-lg md:text-xl max-w-2xl mx-auto"><?php echo htmlspecialchars(getSetting('our_products_description', 'Discover our complete collection of premium coffee products')); ?></p>
			</div>

			<!-- Sidebar Layout -->
			<div class="flex flex-col lg:flex-row gap-8">
				<!-- Categories Sidebar -->
				<div class="hidden lg:block lg:w-1/4">
					<div class="bg-white rounded-2xl p-8 shadow-lg border border-gray-100 sticky top-20">
						<h4 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
							<i class="fas fa-tags text-indigo-600 mr-3"></i><?php echo htmlspecialchars(getSetting('categories_title', 'Product Categories')); ?>
						</h4>
						<div class="max-h-96 overflow-y-auto scrollbar-thin scrollbar-thumb-indigo-300 scrollbar-track-gray-100">
							<div class="space-y-3">
								<!-- All Products Button -->
								<button onclick="filterProducts('all')" class="w-full text-left px-5 py-4 rounded-xl bg-yellow-50 text-black hover:bg-yellow-100 hover:shadow-md transition-all duration-300 flex items-center justify-between category-btn group active" data-category="all">
									<span class="flex items-center">
										<i class="fas fa-th-large text-black mr-3 group-hover:scale-110 transition-transform"></i>
										<span class="font-medium"><?php echo htmlspecialchars(getSetting('all_products', $currentLanguage == 'km' ? 'ផលិតផលទាំងអស់' : 'All Products')); ?></span>
									</span>
									<span class="bg-black bg-opacity-20 text-black px-3 py-1 rounded-full text-xs font-semibold"><?php echo count($allAvailableProducts); ?></span>
								</button>

								<!-- Database Categories -->
								<?php if (!empty($categories)): ?>
									<?php foreach ($categories as $category): ?>
										<?php
										// Count products in this category using base_category_id
										$categoryCount = 0;
										foreach ($allAvailableProducts as $product) {
											if ($product['base_category_id'] == $category['base_category_id']) {
												$categoryCount++;
											}
										}
										?>
										<button onclick="filterProducts('category-<?php echo $category['base_category_id']; ?>')" class="w-full text-left px-5 py-4 rounded-xl hover:bg-indigo-50 hover:shadow-md transition-all duration-300 flex items-center justify-between category-btn group" data-category="category-<?php echo $category['base_category_id']; ?>">
											<span class="flex items-center">
												<i class="fas fa-tag text-indigo-500 mr-3 group-hover:text-indigo-600 transition-colors"></i>
												<span class="font-medium text-gray-700 group-hover:text-gray-900"><?php echo htmlspecialchars($category['name']); ?></span>
											</span>
											<span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-semibold group-hover:bg-indigo-200 transition-colors"><?php echo $categoryCount; ?></span>
										</button>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>


					</div>
				</div>

			<!-- Products Grid -->
				<div class="w-full lg:w-3/4" id="ajax-products-wrapper">
					<?php
					// Pagination variables are now calculated at the top of the file
					?>
					<div id="products-container" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 md:gap-8">
						<?php
						if (!empty($pagedProducts)):
							foreach ($pagedProducts as $index => $product):
								$categoryClass = '';
								if ($product['featured']) {
									$categoryClass = 'product-featured';
								} else {
									$categoryClass = 'product-regular';
								}
								// Calculate AOS delay for staggered effect (0, 100, 200...)
								$aosDelay = ($index % 6) * 100;
						?>
							<article class="product-item bg-white rounded-[2rem] p-2 md:p-4 transition-all duration-300 group product-item <?php echo $categoryClass; ?> cursor-pointer flex flex-col h-full hover:shadow-2xl border border-gray-100/50 relative overflow-hidden" 
									 onclick="window.location.href='product_detail.php?base_id=<?php echo $product['base_product_id']; ?>'" 
									 data-category="<?php echo $product['featured'] ? 'featured' : 'regular'; ?>" 
									 data-category-id="<?php echo $product['base_category_id'] ?: ''; ?>" 
									 data-price="<?php echo $product['price']; ?>"
									 data-aos="fade-up"
									 data-aos-delay="<?php echo $aosDelay; ?>">
								
								<!-- Hover Gradient Blob (Desktop) -->
								<div class="hidden md:block absolute -top-10 -right-10 w-48 h-48 bg-yellow-100 rounded-full blur-3xl opacity-0 group-hover:opacity-60 transition-opacity"></div>
								
								<!-- Badges -->
								<div class="absolute top-4 left-4 flex flex-col gap-2 z-20">
									<?php if ($product['featured']): ?>
										<span class="bg-yellow-500 text-white px-3 py-1 rounded-xl text-[11px] font-bold shadow-sm flex items-center gap-1.5 backdrop-blur-sm bg-opacity-90">
											<i class="fas fa-star text-[9px]"></i> FEATURED
										</span>
									<?php elseif ($product['best_seller']): ?>
										<span class="bg-red-500 text-white px-3 py-1 rounded-xl text-[11px] font-bold shadow-sm flex items-center gap-1.5 backdrop-blur-sm bg-opacity-90">
											<i class="fas fa-fire text-[9px]"></i> HOT
										</span>
									<?php endif; ?>
								</div>

								<!-- Image Container -->
								<div class="product-image-container relative mb-6 pt-[100%] rounded-3xl bg-gray-50/50 md:bg-transparent overflow-hidden">
									<div class="absolute inset-0 flex items-center justify-center p-1">
										<img src="<?php echo ($product['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
											 alt="<?php echo htmlspecialchars($product['name']); ?>" 
											 class="main-img w-full h-full object-contain transform transition-transform duration-700 group-hover:scale-110 drop-shadow-2xl" 
											 style="filter: drop-shadow(0 15px 25px rgba(0,0,0,0.12));">
									</div>
									
									<!-- Mobile Quick Add/View Button (Overlay) -->
									<button class="md:hidden absolute bottom-4 right-4 w-10 h-10 rounded-full bg-white shadow-lg flex items-center justify-center text-yellow-600 active:scale-90 transition-transform z-30 border border-gray-100" onclick="event.stopPropagation(); window.location.href='product_detail.php?base_id=<?php echo $product['base_product_id']; ?>'">
										<i class="fas fa-plus"></i>
									</button>
								</div>

								<!-- Content -->
								<div class="product-info flex-1 flex flex-col pt-2">
									<!-- Title -->
									<h4 class="product-title text-base md:text-xl font-bold text-gray-900 mb-2 line-clamp-2 leading-snug min-h-[48px] md:min-h-[auto] group-hover:text-orange-600 transition-colors">
										<?php echo htmlspecialchars($product['name']); ?>
									</h4>

									<!-- Rating (Small) -->
									<div class="flex items-center gap-2 mb-4 md:mb-6">
										<div class="flex text-xs text-yellow-400">
											<i class="fas fa-star"></i>
										</div>
										<span class="text-xs md:text-sm text-gray-500 font-medium"><?php echo round($product['avg_rating'], 1); ?> (<?php echo $product['review_count']; ?>)</span>
									</div>

									<!-- Price & Action (Desktop Only) -->
									<div class="mt-auto flex items-center justify-between">
										<div class="flex flex-col">
											<?php if(!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
												<span class="text-xs text-gray-400 line-through">$<?php echo number_format($product['old_price'], 2); ?></span>
											<?php endif; ?>
											<span class="text-lg md:text-2xl font-black text-gray-900">$<?php echo number_format($product['price'], 2); ?></span>
										</div>
										
										<!-- Desktop View Arrow -->
										<div class="hidden md:flex w-12 h-12 rounded-full bg-yellow-50 items-center justify-center text-yellow-600 group-hover:bg-yellow-500 group-hover:text-white transition-all duration-300 shadow-sm">
											<i class="fas fa-arrow-right text-base"></i>
										</div>
									</div>
								</div>
							</article>
						<?php
							endforeach;
						else:
						?>
							<div class="col-span-full text-center py-12">
								<i class="fas fa-coffee text-gray-300 text-6xl mb-4"></i>
								<p class="text-gray-500 text-lg">No products available at the moment.</p>
							</div>
						<?php endif; ?>
					</div>

					<!-- iOS Style Pagination Box (AJAX Target) -->
					<div id="pagination-ui-container">
						<?php if ($totalPages > 1): ?>
						<div class="mt-12 flex justify-center">
							<nav class="inline-flex items-center bg-gray-100/80 backdrop-blur-md p-1.5 rounded-[1.5rem] border border-gray-200/50 shadow-sm">
								<!-- Previous Page -->
								<button onclick="changePage(<?php echo $currentPage - 1; ?>)" <?php echo $currentPage <= 1 ? 'disabled' : ''; ?> class="w-10 h-10 flex items-center justify-center rounded-full <?php echo $currentPage <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-white hover:text-orange-600'; ?> transition-all duration-200">
									<i class="fas fa-chevron-left text-sm"></i>
								</button>

								<!-- Page Numbers -->
								<div class="flex items-center px-2">
									<?php
									$startPage = max(1, $currentPage - 2);
									$endPage = min($totalPages, $currentPage + 2);
                                    
                                    // Always show at least 5 pages if available
									if ($endPage - $startPage < 4) {
                                        if ($startPage == 1) {
                                            $endPage = min($totalPages, 5);
                                        } else {
                                            $startPage = max(1, $totalPages - 4);
                                        }
                                    }

									if ($startPage > 1): ?>
										<button onclick="changePage(1)" class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold text-gray-500 hover:bg-white hover:text-orange-600 transition-all duration-200">1</button>
										<?php if ($startPage > 2): ?><span class="px-2 text-gray-400 text-xs italic">...</span><?php endif; ?>
									<?php endif; ?>

									<?php for ($i = $startPage; $i <= $endPage; $i++): ?>
										<button onclick="changePage(<?php echo $i; ?>)" 
										   class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold transition-all duration-300 <?php echo $i == $currentPage ? 'bg-orange-500 text-white shadow-lg shadow-orange-200 scale-110' : 'text-gray-500 hover:bg-white hover:text-orange-600'; ?>">
											<?php echo $i; ?>
										</button>
									<?php endfor; ?>

									<?php if ($endPage < $totalPages): ?>
										<?php if ($endPage < $totalPages - 1): ?><span class="px-2 text-gray-400 text-xs italic">...</span><?php endif; ?>
										<button onclick="changePage(<?php echo $totalPages; ?>)" class="w-10 h-10 flex items-center justify-center rounded-full text-sm font-bold text-gray-500 hover:bg-white hover:text-orange-600 transition-all duration-200"><?php echo $totalPages; ?></button>
									<?php endif; ?>
								</div>

								<!-- Next Page -->
								<button onclick="changePage(<?php echo $currentPage + 1; ?>)" <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?> class="w-10 h-10 flex items-center justify-center rounded-full <?php echo $currentPage >= $totalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-white hover:text-orange-600'; ?> transition-all duration-200">
									<i class="fas fa-chevron-right text-sm"></i>
								</button>
							</nav>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>
			</div>
		</div>
	</section>



	<!-- Search Modal -->
	<div id="searchModal" class="fixed inset-0 z-50 hidden">
		<div class="flex items-center justify-center min-h-screen p-4">
			<div class="modal-content bg-white max-w-2xl w-full max-h-[90vh] overflow-hidden relative">
				<!-- Search Header -->
				<div class="flex items-center justify-between">
					<h3 class="text-2xl font-bold text-gray-800 flex items-center">
						<i class="fas fa-search text-blue-500 mr-2"></i>Search Products
					</h3>
					<button id="closeSearchModal" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
						</svg>
					</button>
				</div>

				<!-- Search Input -->
				<div class="border-b border-gray-200">
					<div class="relative">
						<input type="text" id="searchInput" placeholder="<?php echo htmlspecialchars(getSetting('search_placeholder', 'Search for products...')); ?>" class="w-full px-4 py-3 pl-12 border-0 focus:outline-none focus:ring-0 text-lg">
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
						<p class="text-lg"><?php echo htmlspecialchars(getSetting('no_results', 'No products found matching your search.')); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Embed product data for JavaScript -->
	<script>
		const productsData = <?php echo json_encode($products); ?>;
		const searchProductsData = <?php echo json_encode($searchProducts); ?>;
		const translations = {
			noReviews: '<?php echo addslashes(getSetting('no_reviews', 'No reviews yet. Be the first to review this product!')); ?>',
			searchPlaceholder: '<?php echo addslashes(getSetting('search_placeholder', 'Search products...')); ?>',
			noResults: '<?php echo addslashes(getSetting('no_results', 'No products found matching your search.')); ?>',
			noCategoryResults: '<?php echo addslashes(getSetting('no_category_results', 'No products found in this category.')); ?>',
			reviewsText: '<?php echo addslashes(getSetting('reviews_text', 'reviews')); ?>',
			reviewSubmitted: '<?php echo addslashes(getSetting('review_submitted', 'Review submitted successfully!')); ?>',
			reviewError: '<?php echo addslashes(getSetting('review_error', 'Error submitting review')); ?>',
			noCategoryTitle: '<?php echo addslashes(getSetting('no_category_title', 'No products found in this category.')); ?>',
			noCategoryDesc: '<?php echo addslashes(getSetting('no_category_desc', 'Try selecting a different category or check back later.')); ?>',
			// Modal translations
			modalFeatured: '<?php echo addslashes(getSetting('modal_featured', 'Featured')); ?>',
			modalPremium: '<?php echo addslashes(getSetting('modal_premium', 'Premium')); ?>',
			modalPrice: '<?php echo addslashes(getSetting('modal_price', 'Price')); ?>',
			modalWeight: '<?php echo addslashes(getSetting('modal_weight', 'Weight')); ?>',
			modalDetailedDescription: '<?php echo addslashes(getSetting('modal_detailed_description', 'Detailed Description')); ?>',
			modalIngredients: '<?php echo addslashes(getSetting('modal_ingredients', 'Ingredients')); ?>',
			modalOrigin: '<?php echo addslashes(getSetting('modal_origin', 'Origin')); ?>',
			modalBrewingInstructions: '<?php echo addslashes(getSetting('modal_brewing_instructions', 'Brewing Instructions')); ?>',
			modalTastingNotes: '<?php echo addslashes(getSetting('modal_tasting_notes', 'Tasting Notes')); ?>',
			modalRoastLevel: '<?php echo addslashes(getSetting('modal_roast_level', 'Roast Level')); ?>',
			modalCustomFields: '<?php echo addslashes(getSetting('modal_custom_fields', 'Additional Information')); ?>',
			modalShare: '<?php echo addslashes(getSetting('modal_share', 'Share')); ?>',
			modalWhyChoose: '<?php echo addslashes(getSetting('modal_why_choose', 'Why Choose This Product?')); ?>',
			modalQualityIngredients: '<?php echo addslashes(getSetting('modal_quality_ingredients', 'Premium quality ingredients')); ?>',
			modalCarefullySourced: '<?php echo addslashes(getSetting('modal_carefully_sourced', 'Carefully sourced and roasted')); ?>',
			modalPerfectOccasion: '<?php echo addslashes(getSetting('modal_perfect_occasion', 'Perfect for any occasion')); ?>',
			modalRichFlavor: '<?php echo addslashes(getSetting('modal_rich_flavor', 'Rich in flavor and aroma')); ?>',
			modalCustomerReviews: '<?php echo addslashes(getSetting('modal_customer_reviews', 'Customer Reviews')); ?>',
			modalWriteReview: '<?php echo addslashes(getSetting('modal_write_review', 'Write a Review')); ?>',
			modalYourName: '<?php echo addslashes(getSetting('modal_your_name', 'Your Name')); ?>',
			modalRating: '<?php echo addslashes(getSetting('modal_rating', 'Rating')); ?>',
			modalYourReview: '<?php echo addslashes(getSetting('modal_your_review', 'Your Review')); ?>',
			modalSubmitReview: '<?php echo addslashes(getSetting('modal_submit_review', 'Submit Review')); ?>'
		};


		// Function to show product detail page
		window.showProductModal = function(productData) {
			const baseId = productData.base_product_id || productData.baseProductId || productData.baseProduct_id;
			if (baseId) {
				window.location.href = 'product_detail.php?base_id=' + baseId;
			}
		};

		// Search Modal functionality
		const searchModal = document.getElementById('searchModal');
		const searchButton = document.getElementById('searchButton');
		const closeSearchModalBtn = document.getElementById('closeSearchModal');
		const searchInput = document.getElementById('searchInput');
		const searchResults = document.getElementById('searchResults');
		const noResults = document.getElementById('noResults');

		function showSearchModal() {
			if (!searchModal) return;
			searchModal.classList.remove('hidden');
			document.body.style.overflow = 'hidden';
			searchInput.focus();
		}

		function hideSearchModal() {
			if (!searchModal) return;
			searchModal.classList.add('hidden');
			document.body.style.overflow = ''; // Restore default overflow
			searchInput.value = '';
			searchResults.innerHTML = '';
			noResults.classList.add('hidden');
			
			// Fix for sticky header disappearing
			const header = document.querySelector('header');
			if (header) {
				const originalPosition = header.style.position;
				header.style.position = 'relative'; // Temporarily switch to relative
				void header.offsetHeight; // Force reflow
				header.style.position = originalPosition; // Remove inline style to revert to CSS sticky
			}

			// Trigger scroll event to restore header state
			window.dispatchEvent(new Event('scroll'));
		}

		function containsKhmer(text) {
			const khmerRegex = /[\u1780-\u17FF\u19E0-\u19FF\u1A00-\u1A1F]/;
			return khmerRegex.test(text);
		}

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

		function performSearch(query) {
			if (!query.trim()) {
				searchResults.innerHTML = '';
				noResults.classList.add('hidden');
				return;
			}
			const filteredProducts = searchProductsData.filter(product => {
				const nameMatch = product.all_names.toLowerCase().includes(query.toLowerCase());
				const descMatch = product.all_descriptions.toLowerCase().includes(query.toLowerCase());
				return nameMatch || descMatch;
			});
			const displayProducts = filteredProducts.map(product => getDisplayProduct(product, query));
			displaySearchResults(displayProducts);
		}

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
					window.location.href = 'product_detail.php?base_id=' + product.base_product_id;
				};
				resultItem.innerHTML = `
					<img src="${(product.image || '/kouprey/public/assets/images/product-medium.png') + '?t=' + Date.now()}" alt="${product.name}" class="w-12 h-12 object-contain mr-4 rounded">
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

		if (searchInput) {
			searchInput.addEventListener('input', (e) => performSearch(e.target.value));
		}
		if (searchButton) searchButton.addEventListener('click', showSearchModal);
		if (closeSearchModalBtn) closeSearchModalBtn.addEventListener('click', hideSearchModal);
		if (searchModal) {
			searchModal.addEventListener('click', (e) => {
				if (e.target === searchModal) hideSearchModal();
			});
		}


		// Product filtering functionality
		function filterProducts(category) {
			const categoryBtns = document.querySelectorAll('.category-btn');
			
			// Update desktop active button
			categoryBtns.forEach(function(btn) {
				btn.classList.remove('active', 'bg-yellow-50', 'text-yellow-700');
				if (btn.getAttribute('data-category') === category) {
					btn.classList.add('active', 'bg-yellow-50', 'text-black');
				}
			});

			// Update mobile active button
			updateMobileCategoryButtons(category);

			// Update URL without reloading
			try {
				const url = new URL(window.location.href);
				if (category === 'all') {
					url.searchParams.delete('category');
				} else {
					url.searchParams.set('category', category);
				}
				url.searchParams.set('page', '1'); // Reset to page 1
				window.history.pushState({}, '', url.toString());
			} catch (e) {
				console.error('Error updating URL:', e);
			}

            // Fetch filtered products from server
            const wrapper = document.getElementById('ajax-products-wrapper');
			if (wrapper) {
				wrapper.style.opacity = '0.5';
				wrapper.style.pointerEvents = 'none';
			}

            fetch('?page=1&category=' + encodeURIComponent(category) + '&ajax_pagination=1')
				.then(function(response) { return response.json(); })
				.then(function(data) {
					// Update products content
					const container = document.getElementById('products-container');
					
					if (container) {
                        container.outerHTML = data.productsHtml;
                    } else if (wrapper) {
						// Fallback if container is missing
                        const newContainer = document.createElement('div');
                        newContainer.innerHTML = data.productsHtml;
                        wrapper.prepend(newContainer.firstChild); 
                    }
					
					// Update pagination UI
					const paginationContainer = document.getElementById('pagination-ui-container');
					if (paginationContainer) {
                        paginationContainer.innerHTML = data.paginationHtml;
                    }

					// Restore UI state
					if (wrapper) {
						wrapper.style.opacity = '1';
						wrapper.style.pointerEvents = 'auto';
					}

					// Re-initialize AOS for new elements
					if (typeof AOS !== 'undefined') {
						AOS.refreshHard();
					}
				})
				.catch(function(error) {
					console.error('Error fetching filtered products:', error);
					if (wrapper) {
						wrapper.style.opacity = '1';
						wrapper.style.pointerEvents = 'auto';
					}
				});
		}

		// Function to filter by category from mobile/spotlight sections
		function filterMobileCategory(categoryId) {
			if (!categoryId) return;
			filterProducts('category-' + categoryId);
			
			// Scroll to products section
			const productsSection = document.getElementById('products');
			if (productsSection) {
				productsSection.scrollIntoView({ behavior: 'smooth' });
			}
		}

		function changePage(page) {
			const wrapper = document.getElementById('ajax-products-wrapper');
			wrapper.style.opacity = '0.5';
			wrapper.style.pointerEvents = 'none';

			const urlParams = new URLSearchParams(window.location.search);
			const currentCategory = urlParams.get('category') || 'all';
			
			fetch(`?page=${page}&category=${currentCategory}&ajax_pagination=1`)
				.then(response => response.json())
				.then(data => {
					// Update products content
					const container = document.getElementById('products-container');
					container.outerHTML = data.productsHtml;
					
					// Update pagination UI
					const paginationContainer = document.getElementById('pagination-ui-container');
					paginationContainer.innerHTML = data.paginationHtml;

					// Restore UI state
					wrapper.style.opacity = '1';
					wrapper.style.pointerEvents = 'auto';

					// Scroll back to top of products section
					document.getElementById('products').scrollIntoView({ behavior: 'smooth' });

					// Re-initialize AOS for new elements
					if (typeof AOS !== 'undefined') {
						AOS.refreshHard();
					}
				})
				.catch(error => {
					console.error('Error fetching page:', error);
					wrapper.style.opacity = '1';
					wrapper.style.pointerEvents = 'auto';
				});
		}

		// Initialize with category from URL or 'all'
		document.addEventListener('DOMContentLoaded', function() {
			const urlParams = new URLSearchParams(window.location.search);
			const categoryParam = urlParams.get('category');
			
			if (categoryParam) {
				// If it's a numeric ID, prefix with 'category-'
				const filterValue = isNaN(categoryParam) ? categoryParam : 'category-' + categoryParam;
				filterProducts(filterValue);
				
				// Optional: scroll to products section
				const productsSection = document.getElementById('products');
				if (productsSection) {
					productsSection.scrollIntoView({ behavior: 'smooth' });
				}
			} else {
				filterProducts('all');
			}
		});

		// Clear all filters function
		function clearAllFilters() {
			// Reset category to 'all'
			filterProducts('all');
		}

		// Mobile categories modal functions
		function toggleCategoriesModal() {
			const modal = document.getElementById('categoriesModal');
			if (!modal) return;
			const modalContent = modal.querySelector('.absolute.bottom-0');
			const isHidden = modal.classList.contains('hidden');

			if (isHidden) {
				modal.classList.remove('hidden');
				document.body.style.overflow = 'hidden'; // Prevent background scrolling
				// Reset any previous transform
				if (modalContent) {
					modalContent.style.transform = 'translateY(0)';
				}
			} else {
				modal.classList.add('hidden');
				document.body.style.overflow = 'auto'; // Restore scrolling
			}
		}

		// Swipe to close functionality for mobile categories modal
		let startY = 0;
		let currentY = 0;
		let isDragging = false;

		document.addEventListener('DOMContentLoaded', function() {
			const modal = document.getElementById('categoriesModal');
			const modalContent = modal ? modal.querySelector('.absolute.bottom-0') : null;

			if (modalContent) {
				// Touch start
				modalContent.addEventListener('touchstart', function(e) {
					startY = e.touches[0].clientY;
					isDragging = true;
					modalContent.style.transition = 'none'; // Disable transition during drag
				}, { passive: true });

				// Touch move
				modalContent.addEventListener('touchmove', function(e) {
					if (!isDragging) return;

					currentY = e.touches[0].clientY;
					const deltaY = currentY - startY;

					// Only allow downward movement
					if (deltaY > 0) {
						const translateY = Math.min(deltaY * 0.8, 300); // Less dampening and more drag distance
						modalContent.style.transform = `translateY(${translateY}px)`;

						// Add visual feedback - fade background
						const opacity = Math.max(0.2, 1 - (translateY / 300));
						const bgElement = modal.querySelector('.bg-black');
						if (bgElement) {
							bgElement.style.opacity = opacity;
						}
					}
				}, { passive: true });

				// Touch end
				modalContent.addEventListener('touchend', function(e) {
					if (!isDragging) return;

					isDragging = false;
					modalContent.style.transition = 'transform 0.3s ease-out'; // Re-enable transition

					const deltaY = currentY - startY;

					// If dragged down more than 120px, close the modal
					if (deltaY > 120) {
						toggleCategoriesModal();
					} else {
						// Snap back to original position
						modalContent.style.transform = 'translateY(0)';
						const bgElement = modal.querySelector('.bg-black');
						if (bgElement) {
							bgElement.style.opacity = '0.5';
						}
					}
				}, { passive: true });
			}
		});



		// Update mobile category button active states
		function updateMobileCategoryButtons(activeCategory) {
			const mobileBtns = document.querySelectorAll('.category-btn-mobile');
			mobileBtns.forEach(btn => {
				btn.classList.remove('active', 'bg-yellow-50', 'text-yellow-700');
				if (btn.getAttribute('data-category') === activeCategory) {
					btn.classList.add('active', 'bg-yellow-50', 'text-black');
				}
			});
		}

		// Hide/show floating categories button based on scroll position (Mobile Only)
		document.addEventListener('DOMContentLoaded', function() {
			const floatingBtn = document.getElementById('floatingCategoriesBtn');
			const productsSection = document.getElementById('products');

			// Check if we're on mobile (screen width < 768px)
			const isMobile = window.innerWidth < 768;

			if (!isMobile) {
				// Hide button completely on desktop/tablet
				if (floatingBtn) {
					floatingBtn.style.display = 'none';
				}
				return;
			}

			// Hide button initially on mobile
			if (floatingBtn) {
				floatingBtn.style.display = 'none';
			}

			// Create intersection observer to show button when products section is visible (mobile only)
			if (productsSection && floatingBtn) {
				const observer = new IntersectionObserver((entries) => {
					entries.forEach(entry => {
						if (entry.isIntersecting) {
							// Show button with fade-in animation
							floatingBtn.style.display = 'block';
							floatingBtn.style.opacity = '0';
							floatingBtn.style.transform = 'translate(-50%, 20px)';
							floatingBtn.style.transition = 'all 0.3s ease-out';

							setTimeout(() => {
								floatingBtn.style.opacity = '1';
								floatingBtn.style.transform = 'translate(-50%, 0)';
							}, 100);
						} else {
							// Hide button with fade-out animation
							floatingBtn.style.opacity = '0';
							floatingBtn.style.transform = 'translate(-50%, 20px)';
							setTimeout(() => {
								floatingBtn.style.display = 'none';
							}, 300);
						}
					});
				}, {
					threshold: 0.1, // Trigger when 10% of the section is visible
					rootMargin: '-50px 0px -50px 0px' // Trigger slightly before the section comes into full view
				});

				observer.observe(productsSection);
			}

			// Handle window resize to update button visibility
			let currentIsMobile = isMobile;
			window.addEventListener('resize', function() {
				const newIsMobile = window.innerWidth < 768;
				if (currentIsMobile !== newIsMobile) {
					currentIsMobile = newIsMobile;
					if (!newIsMobile) {
						// Switched to desktop - hide button
						if (floatingBtn) {
							floatingBtn.style.display = 'none';
						}
					} else {
						// Switched to mobile - reset to initial state
						if (floatingBtn) {
							floatingBtn.style.display = 'none';
							floatingBtn.style.opacity = '1';
							floatingBtn.style.transform = 'translate(-50%, 0)';
						}
					}
				}
			});
		});

	</script>





	<!-- Map Section -->
	<!-- Map Section -->
	<section id="location" class="py-20 md:py-32 relative overflow-hidden bg-gray-50/50">
		<!-- Decorative Elements -->
		<div class="absolute top-0 left-0 w-full h-full pointer-events-none opacity-30">
			<div class="absolute top-[-10%] right-[-5%] w-[500px] h-[500px] bg-orange-200/40 rounded-full blur-[100px]"></div>
			<div class="absolute bottom-[-10%] left-[-5%] w-[400px] h-[400px] bg-blue-200/40 rounded-full blur-[100px]"></div>
		</div>

		<div class="max-w-7xl mx-auto px-4 md:px-6 relative z-10">
			<div class="text-center mb-16" data-aos="fade-up">
				<span class="text-orange-600 font-bold tracking-widest uppercase text-xs md:text-sm mb-3 block">Visit Us</span>
				<h3 class="text-4xl md:text-6xl font-black text-gray-900 font-freeman mb-6 drop-shadow-sm">
					<?php echo $currentLanguage == 'km' ? 'ទីតាំងរបស់យើង' : 'Our Locations'; ?>
				</h3>
				<p class="text-gray-500 max-w-2xl mx-auto text-lg">Come experience the aroma and taste of our premium coffee in person.</p>
			</div>

			<div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col lg:flex-row min-h-[600px] transform hover:shadow-[0_20px_60px_rgba(0,0,0,0.12)] transition-shadow duration-500" data-aos="zoom-in">
				<!-- Info Side -->
				<div class="w-full lg:w-2/5 p-8 md:p-12 lg:p-16 flex flex-col justify-center relative bg-white">
					<!-- Decorative Pattern -->
					<div class="absolute right-0 top-0 w-32 h-32 bg-orange-50 rounded-bl-[100%] opacity-50"></div>
					
					<h4 class="text-2xl font-bold text-gray-900 mb-10 flex items-center gap-3">
						<span class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-sm">
							<i class="fas fa-store"></i>
						</span>
						KouPrey HQ
					</h4>

					<div class="space-y-6 relative z-10">
						<!-- Address -->
						<div class="group flex items-start gap-5 p-4 rounded-2xl hover:bg-gray-50 transition-colors duration-300 cursor-default border border-transparent hover:border-gray-100">
							<div class="w-14 h-14 rounded-2xl bg-orange-100/50 group-hover:bg-orange-100 flex items-center justify-center text-orange-600 flex-shrink-0 transition-colors shadow-sm">
								<i class="fas fa-map-location-dot text-2xl"></i>
							</div>
							<div>
								<span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Address</span>
								<h5 class="font-bold text-gray-900 text-lg mb-1"><?php echo $currentLanguage == 'km' ? 'ហាងរបស់យើង' : 'Our Store'; ?></h5>
								<p class="text-gray-600 leading-relaxed font-medium"><?php echo nl2br(htmlspecialchars(getSetting('company_address', 'Phnom Penh, Cambodia'))); ?></p>
							</div>
						</div>

						<!-- Hours -->
						<div class="group flex items-start gap-5 p-4 rounded-2xl hover:bg-gray-50 transition-colors duration-300 cursor-default border border-transparent hover:border-gray-100">
							<div class="w-14 h-14 rounded-2xl bg-blue-100/50 group-hover:bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0 transition-colors shadow-sm">
								<i class="fas fa-clock text-2xl"></i>
							</div>
							<div>
								<span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Schedule</span>
								<h5 class="font-bold text-gray-900 text-lg mb-1"><?php echo $currentLanguage == 'km' ? 'ម៉ោងបើកដំណើរការ' : 'Opening Hours'; ?></h5>
								<p class="text-gray-600 leading-relaxed font-medium"><?php echo nl2br(htmlspecialchars(getSetting('company_hours', 'Daily: 7AM - 8PM'))); ?></p>
							</div>
						</div>

						<!-- Contact -->
						<div class="group flex items-start gap-5 p-4 rounded-2xl hover:bg-gray-50 transition-colors duration-300 cursor-default border border-transparent hover:border-gray-100">
							<div class="w-14 h-14 rounded-2xl bg-green-100/50 group-hover:bg-green-100 flex items-center justify-center text-green-600 flex-shrink-0 transition-colors shadow-sm">
								<i class="fas fa-headset text-2xl"></i>
							</div>
							<div>
								<span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1 block">Get in Touch</span>
								<h5 class="font-bold text-gray-900 text-lg mb-1"><?php echo $currentLanguage == 'km' ? 'ពត៌មានលម្អិតសម្រាប់ការទំនាក់ទំនង' : 'Contact Details'; ?></h5>
								<p class="text-gray-800 font-bold text-lg"><?php echo htmlspecialchars(getSetting('company_phone', '+855 12 345 678')); ?></p>
								<p class="text-gray-500 text-sm mt-0.5"><?php echo htmlspecialchars(getSetting('company_email', 'info@kouprey.com')); ?></p>
							</div>
						</div>
					</div>

					<a href="https://maps.app.goo.gl/v88Vyavc1UoykzgNA" target="_blank" 
					   class="mt-10 group relative flex items-center justify-center gap-3 bg-gray-900 text-white py-5 px-8 rounded-2xl font-bold overflow-hidden transition-all hover:bg-orange-600 shadow-xl hover:shadow-orange-500/30 transform hover:-translate-y-1">
						<div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-shimmer"></div>
						<i class="fas fa-location-arrow text-xl group-hover:rotate-45 transition-transform duration-300"></i>
						<span>Get Directions</span>
					</a>
				</div>

				<!-- Map Side -->
				<div class="w-full lg:w-3/5 h-[500px] lg:h-auto min-h-[500px] relative">
					<iframe
						src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d292.34896165878865!2d104.91197826608598!3d11.55083956811418!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e1!3m2!1sen!2skh!4v1767834278383!5m2!1sen!2skh"
						class="absolute inset-0 w-full h-full border-0 grayscale-[0%] contrast-[1.05]"
						allowfullscreen=""
						loading="lazy"
						title="KouPrey Coffee Location">
					</iframe>
				</div>
			</div>
		</div>
	</section>

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

                                <!-- Quick Links - Desktop: Original List, Mobile: Flex Cards -->
                                <div>
                                        <h3 class="text-lg font-semibold mb-4 text-white"><?php echo htmlspecialchars(getSetting('footer_quick_links', 'Quick Links')); ?></h3>

                                        <!-- Desktop Version - Original List -->
                                        <ul class="hidden md:block space-y-2">
                                                <li><a href="/kouprey/public/" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_home', 'Home')); ?></a></li>
												<li><a href="/kouprey/public/product.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_products', 'Products')); ?></a></li>
                                                <li><a href="/kouprey/public/about.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_about_us', 'About Us')); ?></a></li>
                                                <li><a href="/kouprey/public/reviews.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_reviews', 'Reviews')); ?></a></li>
                                                <li><a href="/kouprey/admin/login.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_admin', 'Admin')); ?></a></li>
                                        </ul>

                                        <!-- Mobile Version - Flex Cards -->
                                        <div class="md:hidden flex flex-wrap gap-3">
                                                <a href="/kouprey/public/" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-home text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight"><?php echo htmlspecialchars(getSetting('footer_home', 'Home')); ?></span>
                                                        </div>
                                                </a>
												<a href="/kouprey/public/product.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-coffee text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight"><?php echo htmlspecialchars(getSetting('footer_products', 'Products')); ?></span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/public/about.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-info-circle text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight"><?php echo htmlspecialchars(getSetting('footer_about_us', 'About')); ?></span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/public/reviews.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-star text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight"><?php echo htmlspecialchars(getSetting('footer_reviews', 'Reviews')); ?></span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/admin/login.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-cog text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight"><?php echo htmlspecialchars(getSetting('footer_admin', 'Admin')); ?></span>
                                                        </div>
                                                </a>
                                        </div>
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
                                                        <p class="text-gray-300 text-sm mb-2"><?php echo htmlspecialchars(getSetting('newsletter_title', 'Stay Updated')); ?></p>
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
                                        <a href="javascript:void(0)" onclick="openContactModal()" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_contact_us', 'Contact Us')); ?></a>
                                </div>
                        </div>
                </div>
        </footer>

        <!-- Swiper JS -->
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        <script>
            // Global functions
            // Modal functionality
            const modal = document.getElementById('productModal');	
            const closeModalBtn = document.getElementById('closeModal');

			window.showProductModal = function(productData) {
				const baseId = productData.base_product_id || productData.baseProductId || productData.baseProduct_id;
				if (baseId) {
					window.location.href = 'product_detail.php?base_id=' + baseId;
				}
			}

			// Function to hide modal
			window.hideProductModal = function() {
				if (!modal) return;
				modal.classList.add('hidden');
				document.body.style.overflow = 'auto';
				
				// Hide loading indicator
				const loadingEl = document.getElementById('modalLoading');
				if (loadingEl) loadingEl.style.display = 'none';
			}

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


            // Product info label functionality for mobile
            function updateMobileProductInfoLabel() {
                const activeSlide = document.querySelector('.mobile-featured-swiper .swiper-slide-active');
                const label = document.getElementById('main-mobile-product-info-label');
                
				if (activeSlide && label) {
					const productName = activeSlide.querySelector('h4')?.textContent;
					const productPrice = activeSlide.querySelector('p.font-bold')?.textContent || '';
					const productDesc = activeSlide.dataset.detailedDescription || activeSlide.querySelector('p.text-xs.text-gray-600')?.textContent;
					const productWeight = activeSlide.dataset.weight;

					if (productName) {
                        const nameEl = document.getElementById('main-mobile-product-name');
                        const priceEl = document.getElementById('main-mobile-product-price');
                        const weightEl = document.getElementById('main-mobile-product-weight');
                        const descEl = document.getElementById('main-mobile-product-description');
                        const catEl = document.getElementById('main-mobile-product-category');
                        
                        if (nameEl) nameEl.textContent = productName;
						if (priceEl) priceEl.textContent = '';
                        if (weightEl) weightEl.textContent = productWeight ? `Weight: ${productWeight}` : 'Weight: Not specified';
                        if (descEl) descEl.textContent = 'Detailed Description: ' + (productDesc || 'Premium coffee product with exceptional quality and rich flavor profile.');
                        if (catEl) catEl.textContent = 'Featured';
                        label.classList.remove('opacity-0');
                        label.classList.add('opacity-100');
                    }
                } else if (label) {
                    label.classList.remove('opacity-100');
                    label.classList.add('opacity-0');
                }
            }


            document.addEventListener('DOMContentLoaded', function() {
			// Banner Swiper
            const bannerSwiper = new Swiper('.banner-swiper', {
                slidesPerView: 1,
                spaceBetween: 30,
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.banner-swiper .swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.banner-swiper .swiper-button-next',
                    prevEl: '.banner-swiper .swiper-button-prev',
                },
                effect: 'fade',
                fadeEffect: {
                    crossFade: true
                },
                grabCursor: true,
                keyboard: {
                    enabled: true,
                },
            });

            // Product info label functionality for main page desktop featured swiper
            function updateMainProductInfoLabel() {
                const activeSlide = document.querySelector('.featured-swiper .swiper-slide-active');
                const label = document.getElementById('main-product-info-label');
                
					if (activeSlide && label) {
					const productName = activeSlide.querySelector('.product-title')?.textContent || activeSlide.querySelector('h4')?.textContent;
					const productPrice = activeSlide.querySelector('.product-price')?.textContent || '';
					const productDesc = activeSlide.dataset.detailedDescription || activeSlide.querySelector('.product-description')?.textContent;
					const productWeight = activeSlide.dataset.weight;

					if (productName) {
                        const nameEl = document.getElementById('main-product-name');
                        const priceEl = document.getElementById('main-product-price');
                        const weightEl = document.getElementById('main-product-weight');
                        const descEl = document.getElementById('main-product-description');
                        const catEl = document.getElementById('main-product-category');
                        
                        if (nameEl) nameEl.textContent = productName;
						if (priceEl) priceEl.textContent = ''; // prices intentionally hidden
                        if (weightEl) weightEl.textContent = productWeight ? `Weight: ${productWeight}` : 'Weight: Not specified';
                        if (descEl) descEl.textContent = 'Detailed Description: ' + (productDesc || 'Premium coffee product with exceptional quality and rich flavor profile.');
                        if (catEl) catEl.textContent = 'Featured Product';
                        label.classList.remove('opacity-0');
                        label.classList.add('opacity-100');
                    }
                } else if (label) {
                    label.classList.remove('opacity-100');
                    label.classList.add('opacity-0');
                }
            }

            // Product info label functionality for main page mobile featured swiper
            function updateMobileMainProductInfoLabel() {
                var activeSlide = document.querySelector('.mobile-featured-swiper .swiper-slide-active');
                var label = document.getElementById('main-mobile-product-info-label');
                
                if (!activeSlide || !label) {
                    if (label) {
                        label.classList.remove('opacity-100');
                        label.classList.add('opacity-0');
                    }
                    return;
                }

                var h4 = activeSlide.querySelector('h4');
                var productName = h4 ? h4.textContent : null;
                
                var pBold = activeSlide.querySelector('p.font-bold');
                var productPrice = pBold ? pBold.textContent : null;

                if (productName && productPrice) {
                    var descElSource = activeSlide.querySelector('p.text-xs.text-gray-600');
                    var productDesc = activeSlide.dataset.detailedDescription;
                    if (!productDesc && descElSource) {
                        productDesc = descElSource.textContent;
                    }
                    var productWeight = activeSlide.dataset.weight;

                    var nameEl = document.getElementById('main-mobile-product-name');
                    var priceEl = document.getElementById('main-mobile-product-price');
                    var weightEl = document.getElementById('main-mobile-product-weight');
                    var descEl = document.getElementById('main-mobile-product-description');
                    var catEl = document.getElementById('main-mobile-product-category');
                    
                    if (nameEl) nameEl.textContent = productName;
                    if (priceEl) priceEl.textContent = productPrice;
                    if (weightEl) weightEl.textContent = productWeight ? 'Weight: ' + productWeight : 'Weight: Not specified';
                    if (descEl) descEl.textContent = 'Detailed Description: ' + (productDesc || 'Premium coffee product with exceptional quality and rich flavor profile.');
                    if (catEl) catEl.textContent = 'Featured';
                    
                    label.classList.remove('opacity-0');
                    label.classList.add('opacity-100');
                } else {
                    label.classList.remove('opacity-100');
                    label.classList.add('opacity-0');
                }
            }

            // Initialize main page featured swipers only if they exist
            let mainFeaturedSwiper = null;
            let mainMobileFeaturedSwiper = null;

            if (document.querySelector('.featured-swiper')) {
				mainFeaturedSwiper = new Swiper('.featured-swiper', {
					slidesPerView: 1,
					spaceBetween: 30,
					centeredSlides: true,
					loop: true,
					slideToClickedSlide: true,
					pagination: {
						el: '.featured-swiper .swiper-pagination',
						clickable: true,
					},
					navigation: {
						nextEl: '.featured-swiper .swiper-button-next',
						prevEl: '.featured-swiper .swiper-button-prev',
					},
					autoplay: {
						delay: 3000,
						disableOnInteraction: false,
						pauseOnMouseEnter: true,
					},
					speed: 600,
					grabCursor: true,
					simulateTouch: true,
					touchRatio: 1,
					resistanceRatio: 0.85,
					keyboard: {
						enabled: true,
					},
					effect: 'slide',
					watchSlidesProgress: true,
                    breakpoints: {
						900: {
							slidesPerView: 3,
							spaceBetween: 30,
							centeredSlides: true,
							slideToClickedSlide: true,
							effect: 'slide',
						}
                    }
                });
            }

            if (document.querySelector('.mobile-featured-swiper')) {
				mainMobileFeaturedSwiper = new Swiper('.mobile-featured-swiper', {
					slidesPerView: 1,
					spaceBetween: 20,
					centeredSlides: false,
					loop: true,
					slideToClickedSlide: true,
					pagination: {
						el: '.mobile-featured-swiper .swiper-pagination',
						clickable: true,
					},
					navigation: {
						nextEl: '.mobile-featured-swiper .swiper-button-next',
						prevEl: '.mobile-featured-swiper .swiper-button-prev',
					},
					autoplay: {
						delay: 2500,
						disableOnInteraction: false,
						pauseOnMouseEnter: true,
					},
					speed: 520,
					grabCursor: true,
					simulateTouch: true,
					touchRatio: 1,
				});
            }

            // Update labels on slide change for main page swipers
            if (mainFeaturedSwiper) {
                mainFeaturedSwiper.on('slideChangeTransitionStart', updateMainProductInfoLabel);
                mainFeaturedSwiper.on('slideChange', updateMainProductInfoLabel);
            }
            if (mainMobileFeaturedSwiper) {
                mainMobileFeaturedSwiper.on('slideChangeTransitionStart', updateMobileMainProductInfoLabel);
                mainMobileFeaturedSwiper.on('slideChange', updateMobileMainProductInfoLabel);
            }

            // Observe changes for main page swipers
            const mainObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const target = mutation.target;
                        if (target.classList.contains('swiper-slide') && target.classList.contains('swiper-slide-active')) {
                            if (target.closest('.featured-swiper')) {
                                updateMainProductInfoLabel();
                            } else if (target.closest('.mobile-featured-swiper')) {
                                updateMobileMainProductInfoLabel();
                            }
                        }
                    }
                });
            });

            // Observe changes to main page swiper slides if they exist
            const desktopSlides = document.querySelectorAll('.featured-swiper .swiper-slide');
            const mobileSlides = document.querySelectorAll('.mobile-featured-swiper .swiper-slide');
            
            desktopSlides.forEach(function(slide) {
                mainObserver.observe(slide, { attributes: true, attributeFilter: ['class'] });
            });
            
            mobileSlides.forEach(function(slide) {
                mainObserver.observe(slide, { attributes: true, attributeFilter: ['class'] });
            });

            // Initial updates for main page labels
            updateMainProductInfoLabel();
            updateMobileMainProductInfoLabel();

            const heroSwiper = new Swiper('.hero-swiper', {
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: false,
                },
                loop: true,
                pagination: {
                    el: '.hero-swiper .swiper-pagination',
                    clickable: true,
                },
            });

            // Ensure hero autoplay starts immediately
            heroSwiper.autoplay.start();

            // Share button functionality
            const shareBtn = document.getElementById('shareBtn');
            if (shareBtn) {
                shareBtn.addEventListener('click', function() {
                    const modalProductName = document.getElementById('modalProductName');
                    if (modalProductName) {
                        const productName = modalProductName.textContent;
                        const productUrl = window.location.href;

                        if (navigator.share) {
                            navigator.share({
                                title: productName,
                                text: 'Check out this amazing coffee product!',
                                url: productUrl
                            });
                        } else {
                            // Fallback for browsers that don't support Web Share API
                            const text = `Check out "${productName}" - ${productUrl}`;
                            navigator.clipboard.writeText(text).then(function() {
                                alert('ðŸ“‹ Product link copied to clipboard!');
                            });
                        }
                    }
                });
            }

            // Close modal events
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', hideProductModal);
            }

            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        hideProductModal();
                    }
                });
            }

            // Add click listeners to all product cards and View Details buttons
            document.addEventListener('click', function(e) {
                const target = e.target;
                const card = target.closest('article');
                const isButton = target.classList.contains('product-button') || target.closest('.product-button');

                if (card && (isButton || target === card || card.contains(target))) {
                    e.preventDefault();

                    // Extract product name from the card
                    let productName = '';
                    if (card.querySelector('.product-title') || card.querySelector('h4')) {
                        const titleElement = card.querySelector('.product-title') || card.querySelector('h4');
                        productName = titleElement.textContent.trim();
                    }

                    // Find the product data from our embedded data
                    const productData = productsData.find(product => product.name === productName);

                    if (productData) {
                        showProductModal(productData);
                    } else {
                        // Fallback for default products without database entries
                        let fallbackData = {
                            name: productName,
                            image: card.querySelector('img') ? card.querySelector('img').src : '/kouprey/public/assets/images/product-medium.png',
                            price: card.querySelector('.product-price') || card.querySelector('p.text-yellow-500') || card.querySelector('p.text-yellow-600') ?
                                   (card.querySelector('.product-price') || card.querySelector('p.text-yellow-500') || card.querySelector('p.text-yellow-600')).textContent.trim().replace('$', '') : '24.00',
                            description: 'Premium coffee product with exceptional quality and taste.',
                            featured: 0,
                            best_seller: 0
                        };

                        // Determine product type for fallback
                        if (card.querySelector('.bg-blue-500')) {
                            fallbackData.featured = 1;
                        }

                        showProductModal(fallbackData);
                    }
                }
            });

            // Keyboard support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    hideProductModal();
                }
                if (e.key === 'Escape' && searchModal && !searchModal.classList.contains('hidden')) {
                    hideSearchModal();
                }
                if (e.key === 'Escape') {
                    closeRelatedProductZoom();
                }
            });

			// Shared Configuration for Card Swipers
			const cardSwiperConfig = {
				slidesPerView: 'auto',
				spaceBetween: 20,
				freeMode: true,
				grabCursor: true,
				observer: true,
				observeParents: true,
				pagination: {
					el: '.swiper-pagination',
					clickable: true,
					dynamicBullets: true,
				},
			};

			// Locking mechanism to prevent infinite loops during sync
			let isSyncing = false;

			const syncSwipers = (source, target) => {
				if (isSyncing) return;
				isSyncing = true;
				
				try {
					const sourceIndex = source.realIndex;
					if (target.realIndex !== sourceIndex) {
						target.slideToLoop(sourceIndex);
					}
				} finally {
					// Add a small delay to release lock, allowing animation to start
					setTimeout(() => {
						isSyncing = false;
					}, 50);
				}
			};

			// Initialize Category Spotlight Swipers & Card Swipers (Linked)
			
			// --- Syrup Collection ---
			if (document.querySelector('.category-swiper-syrup')) {
				const syrupBigSwiper = new Swiper('.category-swiper-syrup', {
					loop: true,
					effect: 'creative',
					creativeEffect: {
						perspective: 1000,
						prev: {
							shadow: false,
							translate: [0, '110%', -300],
							rotate: [20, 0, 0],
							scale: 0.6,
							opacity: 0,
						},
						next: {
							translate: [0, '110%', -300],
							rotate: [20, 0, 0],
							scale: 0.6,
							opacity: 0,
						},
					},
					speed: 900,
					observer: true,
					observeParents: true,
					autoplay: {
						delay: 4000,
						disableOnInteraction: false,
					},
					pagination: {
						el: '.category-swiper-syrup .swiper-pagination',
						clickable: true,
						dynamicBullets: true,
					},
				});

				const syrupSmallSwiper = new Swiper('.category-product-cards-swiper-syrup', {
					...cardSwiperConfig,
					loop: true,
					centeredSlides: true,
					slideToClickedSlide: true, 
					pagination: {
						el: '.category-product-cards-swiper-syrup .swiper-pagination',
						clickable: true,
						dynamicBullets: true,
					},
					on: {
						click: function (swiper) {
							if (!isSyncing) {
								syncSwipers(swiper, syrupBigSwiper);
							}
						}
					}
				});

				// Manual 2-way Binding
				syrupBigSwiper.on('slideChange', () => syncSwipers(syrupBigSwiper, syrupSmallSwiper));
				syrupSmallSwiper.on('slideChange', () => syncSwipers(syrupSmallSwiper, syrupBigSwiper));
			}


			// --- Powder Selection ---
			if (document.querySelector('.category-swiper-powder')) {
				const powderBigSwiper = new Swiper('.category-swiper-powder', {
					loop: true,
					effect: 'creative',
					creativeEffect: {
						perspective: 1000,
						prev: {
							shadow: false,
							translate: [0, '110%', -300],
							rotate: [20, 0, 0],
							scale: 0.6,
							opacity: 0,
						},
						next: {
							translate: [0, '110%', -300],
							rotate: [20, 0, 0],
							scale: 0.6,
							opacity: 0,
						},
					},
					speed: 900,
					observer: true,
					observeParents: true,
					autoplay: {
						delay: 4500,
						disableOnInteraction: false,
					},
					pagination: {
						el: '.category-swiper-powder .swiper-pagination',
						clickable: true,
						dynamicBullets: true,
					},
				});

				const powderSmallSwiper = new Swiper('.category-product-cards-swiper-powder', {
					...cardSwiperConfig,
					loop: true,
					centeredSlides: true,
					slideToClickedSlide: true,
					pagination: {
						el: '.category-product-cards-swiper-powder .swiper-pagination',
						clickable: true,
						dynamicBullets: true,
					},
					on: {
						click: function (swiper) {
							if (!isSyncing) {
								syncSwipers(swiper, powderBigSwiper);
							}
						}
					}
				});

				// Manual 2-way Binding
				powderBigSwiper.on('slideChange', () => syncSwipers(powderBigSwiper, powderSmallSwiper));
				powderSmallSwiper.on('slideChange', () => syncSwipers(powderSmallSwiper, powderBigSwiper));
			}


			// --- Bean Collection (Independent for now, or link if desired) ---
			if (document.querySelector('.category-swiper-bean')) {
				new Swiper('.category-swiper-bean', {
					loop: true,
					effect: 'creative',
					creativeEffect: {
						perspective: 1000,
						prev: {
							shadow: false,
							translate: [0, '110%', -300],
							rotate: [20, 0, 0],
							scale: 0.6,
							opacity: 0,
						},
						next: {
							translate: [0, '110%', -300],
							rotate: [20, 0, 0],
							scale: 0.6,
							opacity: 0,
						},
					},
					speed: 900,
					autoplay: {
						delay: 5000,
						disableOnInteraction: false,
					},
					pagination: {
						el: '.category-swiper-bean .swiper-pagination',
						clickable: true,
						dynamicBullets: true,
					},
				});

				new Swiper('.category-product-cards-swiper-bean', {
					...cardSwiperConfig,
					pagination: {
						el: '.category-product-cards-swiper-bean .swiper-pagination',
						clickable: true,
						dynamicBullets: true,
					},
				});
			}




            // Header scroll effect
            const header = document.querySelector('header');
            if (header) {
                window.addEventListener('scroll', function() {
                    if (window.scrollY > 10) {
                        header.classList.add('shadow-md');
                        header.classList.remove('shadow-sm');
                    } else {
                        header.classList.remove('shadow-md');
                        header.classList.add('shadow-sm');
                    }
                });
            }

			}); // Close DOMContentLoaded
		</script>

		<!-- Mobile Bottom Navigation -->
	<!-- Mobile Bottom Navigation (Floating Style) -->
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

        <!-- Floating Categories Button (Mobile Only) -->
        <div id="floatingCategoriesBtn" class="fixed bottom-20 right-4 z-40 md:hidden">
			<button onclick="toggleCategoriesModal()" aria-label="Open filters" title="Filters" class="w-16 h-16 rounded-full flex items-center justify-center bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-white shadow-2xl border border-white/20 backdrop-blur-sm transition-transform duration-300 transform hover:-translate-y-1 hover:scale-110 focus:outline-none focus:ring-4 focus:ring-yellow-300/30">
				<i class="fas fa-filter text-lg"></i>
			</button>
        </div>

        <!-- Categories Modal (Mobile Only) -->
        <div id="categoriesModal" class="fixed inset-0 z-50 hidden md:hidden">
            <div class="absolute inset-0 bg-black bg-opacity-50" onclick="toggleCategoriesModal()"></div>
            <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl max-h-[85vh] overflow-y-auto">
                <!-- Drag Handle -->
                <div class="flex justify-center pt-4 pb-2">
                    <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
                </div>

                <!-- Header -->
                <div class="px-6 pb-4">
                    <div class="flex justify-between items-center">
						<h3 class="text-xl font-bold text-gray-800 flex items-center">
							<i class="fas fa-filter text-yellow-500 mr-3"></i><?php echo htmlspecialchars(getSetting('filters_title', 'Filters')); ?>
						</h3>
                        <button onclick="toggleCategoriesModal()" class="text-gray-400 hover:text-gray-600 p-2">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>



                <!-- Product Categories - Mobile Optimized -->
                <div class="px-6 pb-6">
					<h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
						<i class="fas fa-tags text-purple-500 mr-2"></i><?php echo htmlspecialchars(getSetting('product_categories', 'Product Categories')); ?>
					</h4>

                    <!-- Quick Actions Row -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <!-- All Products - Prominent -->
                        <button onclick="filterProducts('all'); setTimeout(() => toggleCategoriesModal(), 300);" class="bg-yellow-50 text-black p-4 rounded-2xl hover:bg-yellow-100 transition-all duration-300 flex flex-col items-center justify-center shadow-lg hover:shadow-xl transform hover:scale-105 category-btn-mobile active" data-category="all">
                            <i class="fas fa-th-large text-2xl mb-2"></i>
                            <span class="font-semibold text-sm"><?php echo htmlspecialchars(getSetting('all_products', $currentLanguage == 'km' ? 'ផលិតផលទាំងអស់' : 'All Products')); ?></span>
                            <span class="bg-black bg-opacity-20 text-xs px-2 py-1 rounded-full mt-1 font-medium"><?php echo count($allAvailableProducts); ?></span>
                        </button>

                        <!-- Clear Filters -->
                        <button onclick="clearAllFilters(); setTimeout(() => toggleCategoriesModal(), 300);" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white p-4 rounded-2xl hover:from-gray-600 hover:to-gray-700 transition-all duration-300 flex flex-col items-center justify-center shadow-lg hover:shadow-xl transform hover:scale-105">
                            <i class="fas fa-eraser text-2xl mb-2"></i>
                            <span class="font-semibold text-sm"><?php echo htmlspecialchars(getSetting('clear_filters', 'Clear All')); ?></span>
                            <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full mt-1 font-medium">Reset</span>
                        </button>
                    </div>

                    <!-- Category List - Improved Mobile Layout -->
                    <div class="bg-gray-50 rounded-2xl p-4">
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <?php
                                    $categoryCount = 0;
                                    foreach ($allAvailableProducts as $product) {
                                        if ($product['base_category_id'] == $category['base_category_id']) {
                                            $categoryCount++;
                                        }
                                    }
                                    ?>
                                    <button onclick="filterProducts('category-<?php echo $category['base_category_id']; ?>'); setTimeout(() => toggleCategoriesModal(), 300);"
                                            class="w-full bg-white p-4 rounded-xl hover:bg-purple-50 transition-all duration-200 flex items-center justify-between shadow-sm hover:shadow-md border border-gray-100 hover:border-purple-200 category-btn-mobile"
                                            data-category="category-<?php echo $category['base_category_id']; ?>">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                <i class="fas fa-tag text-purple-600 text-sm"></i>
                                            </div>
                                            <div class="flex-1 min-w-0 text-left">
                                                <span class="font-medium text-gray-800 block truncate"><?php echo htmlspecialchars($category['name']); ?></span>
                                                <span class="text-xs text-gray-500 block"><?php echo $categoryCount; ?> products</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center ml-3">
                                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-semibold mr-2"><?php echo $categoryCount; ?></span>
                                            <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p>No categories available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Apply Filters Button -->
                <div class="px-6 pb-6">
                    <button onclick="toggleCategoriesModal()" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-200 flex items-center justify-center shadow-lg hover:shadow-xl">
                        <i class="fas fa-check mr-2"></i>
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
		

		<!-- Hover Zoom Styles & Script - Inner Zoom Implementation -->
		<style>
		.product-image-container, .featured-swiper .swiper-slide, .mobile-featured-swiper .swiper-slide {
			overflow: hidden !important;
		}
		
		.main-img, .featured-swiper img, .mobile-featured-swiper img {
			transition: transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
			transform-origin: center center;
			will-change: transform, transform-origin;
		}
		</style>
		<script>
		// Advanced Inner Zoom Implementation
		(function(){
			const zoomConfigs = [
				{ containerSelector: '.product-image-container', targetSelector: 'img' },
				{ containerSelector: '.featured-swiper .swiper-slide', targetSelector: 'img' },
				{ containerSelector: '.mobile-featured-swiper .swiper-slide', targetSelector: 'img' },
				{ containerSelector: '.modal-image-container', targetSelector: '#modalImage' }
			];

			function attachZoom(cfg) {
				document.querySelectorAll(cfg.containerSelector).forEach(container => {
					if (container.getAttribute('data-zoom-attached')) return;
					
					const img = container.querySelector(cfg.targetSelector);
					if (!img) return;

					container.setAttribute('data-zoom-attached', '1');
					container.style.overflow = 'hidden';
					img.style.willChange = 'transform, transform-origin';
					img.style.transition = 'transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1)';

					container.addEventListener('mouseenter', () => {
						img.style.transition = 'transform 0.2s ease-out';
						img.style.transform = 'scale(4)';
						container.style.cursor = 'zoom-in';
					});

					container.addEventListener('mousemove', (e) => {
						const rect = container.getBoundingClientRect();
						const x = ((e.clientX - rect.left) / rect.width) * 100;
						const y = ((e.clientY - rect.top) / rect.height) * 100;
						img.style.transition = 'none';
						img.style.transformOrigin = `${x}% ${y}%`;
						img.style.transform = 'scale(4)';
					});

					container.addEventListener('mouseleave', () => {
						img.style.transition = 'transform 0.4s cubic-bezier(0.165, 0.84, 0.44, 1)';
						img.style.transform = 'scale(1)';
						img.style.transformOrigin = 'center center';
					});
				});
			}

			function initZoom() {
				zoomConfigs.forEach(cfg => attachZoom(cfg));
			}

			const observer = new MutationObserver(() => initZoom());
			observer.observe(document.body, { childList: true, subtree: true });

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initZoom);
			} else {
				initZoom();
			}
		})();
		</script>
		
		<!-- Related Products Zoom Overlay -->
		<div id="relatedProductZoomOverlay" class="related-product-zoom-overlay" onclick="closeRelatedProductZoom()">
			<img id="relatedProductZoomImage" src="" alt="Zoomed Product">
		</div>
		
</body>
</html>

<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		if (window.AOS) {
			AOS.init({ once: true, duration: 800 });
		}
	});

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

