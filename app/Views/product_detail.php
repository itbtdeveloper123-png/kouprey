<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';
require_once __DIR__ . '/../Controllers/ProductController.php';

$currentLanguage = getCurrentLanguage();
$current_page = basename($_SERVER['PHP_SELF']);
$baseProductId = $_GET['base_id'] ?? null;

if (!$baseProductId) {
    header('Location: product.php');
    exit;
}

$controller = new ProductController();
$result = $controller->getProductByBaseId($baseProductId, $currentLanguage);

if (!$result['success'] || !$result['product']) {
    // Try fallback to just id if base_id not found (though usually we use base_id)
    header('Location: product.php');
    exit;
}

$product = $result['product'];
$categoryName = $product['category_name'] ?? 'Products';
$categoryId = $product['category_id'] ?? null;

// Get reviews and related products
$reviewsResult = $controller->getReviews($product['id']);
$relatedResult = $controller->getRelatedProducts($baseProductId, $currentLanguage);

$reviews = $reviewsResult['reviews'] ?? [];
$avgRating = $reviewsResult['avg_rating'] ?? 0;
$totalReviews = $reviewsResult['total_reviews'] ?? 0;
$relatedProducts = $relatedResult['related_products'] ?? [];


// Contact & Social Links
$telegramLink = getSetting('social_telegram', '#');
$whatsappLink = getSetting('social_whatsapp', '#');
$facebookLink = getSetting('social_facebook', '#');

// Fetch all products for search functionality
$productStmt = $pdo->prepare("SELECT * FROM products ORDER BY featured DESC, best_seller DESC, id DESC");
$productStmt->execute();
$allProducts = $productStmt->fetchAll();

// Group products by base_product_id for search index
$productsByBaseId = [];
foreach ($allProducts as $p) {
    $baseId = $p['base_product_id'];
    if (!isset($productsByBaseId[$baseId])) {
        $productsByBaseId[$baseId] = [];
    }
    $productsByBaseId[$baseId][$p['language']] = $p;
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
    foreach ($langVersions as $lang => $p) {
        $allNames[] = $p['name'];
        $allDescriptions[] = $p['description'];
    }

    $searchProduct['all_names'] = implode(' ', $allNames);
    $searchProduct['all_descriptions'] = implode(' ', $allDescriptions);
    $searchProducts[] = $searchProduct;
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($product['name']); ?> - KouPrey Coffee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Freeman&family=Hanuman:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="/kouprey/public/css/rte-content.css">
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
        .font-freeman { font-family: 'Superspace Bold', 'Freeman', serif; }
        
        /* Khmer font: Hanuman for Khmer language and elements with .kh */
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
            color: #1a1a1a;
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

        .breadcrumb-item + .breadcrumb-item::before {
            content: "/";
            padding: 0 8px;
            color: #9ca3af;
        }


        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
        }

        .product-image-frame {
            background: radial-gradient(circle, #fdfbf7 0%, #f5f5f5 100%);
            border-radius: 32px;
            overflow: hidden;
        }

        /* Safe area padding for bottom nav */
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
        /* Related Products Premium Card Styles */
        .related-product-item {
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .related-product-item:hover {
            transform: translateY(-8px);
        }

        .related-product-item .product-image-container {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            background: transparent;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .related-product-item img.main-img {
            transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .related-product-item:hover img.main-img {
            /* JS handles the zoom now */
        }


    </style>
</head>
<body class="bg-white text-gray-800 font-freeman min-h-screen pb-20 flex flex-col">

    <!-- Header (simplified from product.php) -->
    <header class="px-4 py-4 md:px-6 md:py-3 sticky top-0 z-50 transition-all duration-500">
        <div class="flex items-center justify-center max-w-6xl mx-auto h-full">
            <!-- Logo -->
            <div class="flex items-center justify-center w-full">
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
            <!-- Desktop Navigation - HIDDEN -->
            <nav class="hidden">
                <a href="product.php#products" class="<?php echo ($current_page == 'product.php' || $current_page == 'product_detail.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_product', 'Product')); ?></a>
                <a href="features.php" class="<?php echo ($current_page == 'features.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></a>
                <a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></a>
                <a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></a>
            </nav>
            
            <!-- Mobile Actions -->
            <!-- Mobile Actions - REMOVED -->
            <!-- Mobile Actions - HIDDEN -->
            <div class="hidden">
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

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-12">
        
        <!-- Breadcrumb -->
        <nav class="flex text-sm text-gray-500 mb-6 overflow-x-auto whitespace-nowrap pb-2" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2">
                <li class="flex items-center">
                    <a href="product.php" class="hover:text-orange-600 transition-colors"><i class="fas fa-home mr-1.5"></i><?php echo htmlspecialchars(getSetting('nav_home', 'Home')); ?></a>
                    <i class="fas fa-chevron-right text-[10px] mx-2 transition-colors"></i>
                </li>
                <li class="flex items-center">
                    <a href="product.php#products" class="hover:text-orange-600 transition-colors"><i class="fas fa-box-open mr-1.5"></i><?php echo htmlspecialchars(getSetting('nav_product', 'Products')); ?></a>
                    <i class="fas fa-chevron-right text-[10px] mx-2 transition-colors"></i>
                </li>
                <?php if ($categoryId): ?>
                <li class="flex items-center">
                    <a href="product.php?category=<?php echo $categoryId; ?>#products" class="hover:text-orange-600 transition-colors"><i class="fas fa-tag mr-1.5"></i><?php echo htmlspecialchars($categoryName); ?></a>
                    <i class="fas fa-chevron-right text-[10px] mx-2 transition-colors"></i>
                </li>
                <?php endif; ?>
                <li class="text-gray-800 font-bold truncate max-w-[150px] md:max-w-xs" aria-current="page">
                    <i class="fas fa-mug-hot mr-1.5 text-orange-600"></i><?php echo htmlspecialchars($product['name']); ?>
                </li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 items-start">
            
            <!-- Left: Image Gallery -->
            <div class="space-y-6 lg:sticky lg:top-24" data-aos="fade-right">
                <div class="product-image-frame p-8 flex justify-center items-center shadow-inner aspect-square overflow-hidden relative" style="height: 500px;">
                    <div class="product-image-container w-full h-full flex items-center justify-center relative overflow-hidden">
                        <img src="<?php echo ($product['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="main-img w-full h-full object-contain filter drop-shadow-xl">
                    </div>
                </div>
                
                <!-- Quick Info Grid -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-8">
                    <div class="bg-orange-50/50 border border-orange-100 rounded-2xl p-3 flex flex-col items-center text-center">
                        <div class="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center text-white mb-2 shadow-sm">
                            <i class="fas fa-weight-hanging"></i>
                        </div>
                        <span class="text-[10px] uppercase font-bold text-orange-600"><?php echo htmlspecialchars(getSetting('modal_weight', 'Weight')); ?></span>
                        <span class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($product['weight'] ?: '250g'); ?></span>
                    </div>


                    <?php if (!empty($product['roast_level'])): ?>
                    <div class="bg-amber-50/50 border border-amber-100 rounded-2xl p-3 flex flex-col items-center text-center">
                        <div class="w-10 h-10 bg-amber-700 rounded-xl flex items-center justify-center text-white mb-2 shadow-sm">
                            <i class="fas fa-fire"></i>
                        </div>
                        <span class="text-[10px] uppercase font-bold text-amber-700"><?php echo htmlspecialchars(getSetting('modal_roast_level', 'Roast')); ?></span>
                        <span class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($product['roast_level']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="bg-green-50/50 border border-green-100 rounded-2xl p-3 flex flex-col items-center text-center">
                        <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white mb-2 shadow-sm">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <span class="text-[10px] uppercase font-bold text-green-600">Quality</span>
                        <span class="text-sm font-bold text-gray-800">Premium</span>
                    </div>
                </div>
            </div>

            <!-- Right: Product Details -->
            <div class="space-y-8" data-aos="fade-left">
                <div>
                    <?php if ($product['featured']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-600 mb-4 tracking-wider uppercase">
                            <i class="fas fa-star mr-1"></i> Featured Product
                        </span>
                    <?php endif; ?>
                    
                    <h1 class="text-3xl md:text-5xl font-bold text-gray-900 mb-4 leading-tight"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="flex items-center gap-4 mb-6">
                        <div class="flex items-center text-yellow-400">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $i <= round($avgRating) ? '<i class="fas fa-star text-sm"></i>' : '<i class="far fa-star text-sm"></i>';
                            }
                            ?>
                            <span class="ml-2 text-sm text-gray-600 font-medium"><?php echo $avgRating; ?> (<?php echo $totalReviews; ?> Reviews)</span>
                        </div>
                    </div>

                    <p class="text-lg text-gray-600 leading-relaxed mb-8">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </p>
                </div>

                <!-- Product Sections (Accordion-like or Space-separated) -->
                <div class="space-y-2">
                    <?php if (!empty($product['detailed_description'])): ?>
                    <div class="glass-card p-3">
                        <h3 class="text-md font-bold text-gray-800 mb-0.5 flex items-center gap-2">
                            <i class="fas fa-info-circle text-blue-500"></i> Detailed Information
                        </h3>
                        <p class="text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['detailed_description'])); ?></p>
                    </div>
                    <?php endif; ?>



                    <!-- Custom Fields Section -->
                    <?php 
                    $customFields = json_decode($product['custom_fields'] ?? '{}', true);
                    if (!empty($customFields)):
                        echo '<div class="space-y-8 mt-8 border-t border-gray-100 pt-8">';
                        foreach ($customFields as $fieldId => $fieldData):
                            $lang = $currentLanguage;
                            $fieldName = '';
                            $fieldHtml = '';

                            if (is_array($fieldData)):
                                $fieldName = $fieldData['name'][$lang] ?? $fieldData['name']['en'] ?? $fieldId;
                                
                                if (isset($fieldData['type']) && $fieldData['type'] === 'table' && is_array($fieldData['value'])):
                                    // Modern Data Table Layout
                                    $rowsGrid = '';
                                    foreach ($fieldData['value'] as $row) {
                                        $label = $row['label'][$lang] ?? $row['label']['en'] ?? '';
                                        $valuesHtml = '';
                                        if (isset($row['value']) && is_array($row['value'])) {
                                            foreach ($row['value'] as $valPair) {
                                                $val = $valPair[$lang] ?? $valPair['en'] ?? '';
                                                $valuesHtml .= '<span class="font-bold text-gray-900 ml-6">' . htmlspecialchars($val) . '</span>';
                                            }
                                        }
                                        
                                        $rowsGrid .= '
                                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 px-3 transition-colors rounded-lg">
                                                <span class="text-sm text-gray-500 font-medium">' . htmlspecialchars($label) . '</span>
                                                <div class="text-sm font-secondary">' . $valuesHtml . '</div>
                                            </div>';
                                    }
                                    
                                    $fieldHtml = '<div class="mt-3 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">' . $rowsGrid . '</div>';
                                else:
                                    // Default Text Layout with Card Styling
                                    $fieldValue = $fieldData['value'][$lang] ?? $fieldData['value']['en'] ?? '';
                                    if ($fieldValue) {
                                        $fieldHtml = '
                                        <div class="mt-3 bg-gradient-to-br from-gray-50 to-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                                            <p class="text-gray-700 leading-relaxed text-sm md:text-base font-medium">' . nl2br(htmlspecialchars($fieldValue)) . '</p>
                                        </div>';
                                    }
                                endif;
                            elseif (is_string($fieldData)):
                                // Legacy simple string format
                                $fieldName = str_replace(':', '', $fieldId);
                                $fieldHtml = '
                                <div class="mt-3 bg-gradient-to-br from-gray-50 to-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                                    <p class="text-gray-700 leading-relaxed text-sm md:text-base font-medium">' . nl2br(htmlspecialchars($fieldData)) . '</p>
                                </div>';
                            endif;

                            if ($fieldHtml):
                    ?>
                        <div class="animate-fade-in-up">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-3">
                                <span class="text-orange-600 text-lg">✦</span>
                                <?php echo htmlspecialchars($fieldName); ?>
                            </h3>
                            <?php echo $fieldHtml; ?>
                        </div>
                    <?php 
                            endif;
                        endforeach;
                        echo '</div>';
                    endif; 
                    ?>
                </div>

            </div>
        </div>

        <!-- Customer Reviews -->
        <section class="mt-32 max-w-5xl mx-auto" data-aos="fade-up">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars(getSetting('modal_customer_reviews', 'Customer Reviews')); ?></h2>
                <div class="w-24 h-1.5 bg-gradient-to-r from-orange-400 to-orange-600 mx-auto rounded-full"></div>
                <p class="text-gray-500 mt-6 text-lg">Real feedback from our coffee community</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mb-16">
                <!-- Rating Summary -->
                <div class="lg:col-span-4 lg:sticky lg:top-24 h-fit">
                    <div class="glass-card p-8 text-center border border-orange-100/50">
                        <div class="text-7xl font-bold bg-gradient-to-br from-gray-900 to-gray-600 bg-clip-text text-transparent mb-2">
                            <?php echo number_format($avgRating, 1); ?>
                        </div>
                        <div class="flex justify-center text-yellow-400 text-xl mb-3">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $i <= round($avgRating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-gray-200"></i>';
                            }
                            ?>
                        </div>
                        <div class="text-gray-500 font-medium mb-8">Based on <?php echo $totalReviews; ?> reviews</div>
                        
                        <!-- Star Bars -->
                        <div class="space-y-3">
                            <?php
                            $starCounts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
                            foreach($reviews as $r) {
                                if(isset($starCounts[(int)$r['rating']])) $starCounts[(int)$r['rating']]++;
                            }
                            for($i=5; $i>=1; $i--):
                                $percent = $totalReviews > 0 ? ($starCounts[$i] / $totalReviews) * 100 : 0;
                            ?>
                            <div class="flex items-center gap-4 text-sm">
                                <span class="w-4 font-bold text-gray-700"><?php echo $i; ?></span>
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-orange-400 rounded-full transition-all duration-1000" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                                <span class="w-10 text-gray-400 text-xs font-semibold"><?php echo round($percent); ?>%</span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Review Feed -->
                <div class="lg:col-span-8 space-y-6">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="glass-card p-8 border border-white/50 hover:border-orange-200 transition-all duration-300 group">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-orange-100 to-orange-200 rounded-2xl flex items-center justify-center text-orange-600 font-bold text-xl shadow-inner group-hover:scale-110 transition-transform">
                                        <?php echo strtoupper(substr($review['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($review['name']); ?></h4>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-600 uppercase tracking-tighter">
                                                <i class="fas fa-check-circle mr-1"></i> Verified
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-400 font-medium uppercase tracking-widest mt-0.5"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="flex text-yellow-400 gap-0.5 bg-yellow-50/50 px-3 py-1.5 rounded-xl border border-yellow-100">
                                    <?php 
                                    for($i=1; $i<=5; $i++) {
                                        echo $i <= $review['rating'] ? '<i class="fas fa-star text-sm"></i>' : '<i class="far fa-star text-yellow-200 text-sm"></i>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="relative">
                                <i class="fas fa-quote-left absolute -left-2 -top-2 text-orange-100 text-4xl -z-10 opacity-50"></i>
                                <p class="text-gray-600 text-lg leading-relaxed relative z-10 italic">
                                    <?php echo htmlspecialchars($review['review']); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-20 glass-card bg-gray-50/50 border-2 border-dashed border-gray-200">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="far fa-comment-dots text-4xl text-gray-300"></i>
                            </div>
                            <h4 class="text-xl font-bold text-gray-800 mb-2">No reviews yet</h4>
                            <p class="text-gray-500 max-w-xs mx-auto"><?php echo htmlspecialchars(getSetting('no_reviews', 'Be the first to share your experience with this premium coffee!')); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Related Products (Same Category) -->
        <?php if (!empty($relatedProducts)): ?>
        <section class="mt-24" data-aos="fade-up">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-coffee text-orange-500"></i> <?php echo htmlspecialchars(getSetting('related_products_title', 'You May Also Like')); ?>
                </h3>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 md:gap-8">
                <?php foreach (array_slice($relatedProducts, 0, 5) as $rp): ?>
                <a href="product_detail.php?base_id=<?php echo $rp['base_product_id']; ?>" class="group block related-product-item">
                    <div class="product-image-container aspect-square mb-3 flex items-center justify-center relative overflow-hidden text-center">
                        <div class="absolute inset-0 flex items-center justify-center p-2 md:p-4">
                            <!-- Glow effect -->
                            <div class="absolute w-24 h-24 bg-orange-200 rounded-full filter blur-3xl opacity-0 group-hover:opacity-30 transition-opacity duration-500"></div>
                            
                            <img src="<?php echo ($rp['image'] ?: '/kouprey/public/assets/images/product-medium.png') . '?t=' . time(); ?>" 
                                 alt="<?php echo htmlspecialchars($rp['name']); ?>" 
                                 class="main-img max-w-full max-h-full object-contain relative z-10"
                                 style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.2));">
                        </div>
                    </div>
                    <div class="text-center">
                        <h4 class="font-bold text-gray-800 text-sm md:text-base line-clamp-2 leading-snug h-10 md:h-12 flex items-center justify-center mb-1">
                            <?php echo htmlspecialchars($rp['name']); ?>
                        </h4>
                        <p class="text-[10px] md:text-xs text-gray-400 uppercase tracking-wider font-semibold"><?php echo htmlspecialchars($categoryName); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>




    <!-- Search Modal -->
    <div id="searchModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="modal-content bg-white max-w-2xl w-full max-h-[90vh] overflow-hidden relative rounded-2xl shadow-2xl">
                <!-- Search Header -->
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-search text-orange-500 mr-2"></i>Search Products
                    </h3>
                    <button id="closeSearchModal" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="border-b border-gray-100 p-2">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="<?php echo htmlspecialchars(getSetting('search_placeholder', 'Search for products...')); ?>" class="w-full px-4 py-3 pl-12 border-0 focus:outline-none focus:ring-0 text-lg">
                        <svg class="w-5 h-5 absolute left-3 top-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Search Results -->
                <div class="overflow-y-auto max-h-[calc(90vh-200px)] p-2">
                    <div id="searchResults" class="space-y-2">
                        <!-- Results will be populated here -->
                    </div>
                    <div id="noResults" class="text-center text-gray-500 hidden py-12">
                        <i class="fas fa-search-minus text-4xl mb-4 block opacity-20"></i>
                        <p class="text-lg"><?php echo htmlspecialchars(getSetting('no_results', 'No products found matching your search.')); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                        <div class="max-w-none content-section">
                            <?php echo $contactContent; ?>
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
                                <p class="text-gray-600"><?php echo htmlspecialchars(getSetting('company_phone', '+855 12 345 678')); ?></p>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (Floating Style) -->
	<!-- Mobile Bottom Navigation (iOS 26 Floating Island) -->
    <!-- Mobile Bottom Navigation REMOVED -->
    <style>
        /* Removed padding-bottom for mobile nav */
    </style>

    <!-- Embed product search data -->
    <script>
        const searchProductsData = <?php echo json_encode($searchProducts); ?>;
        const currentLang = '<?php echo $currentLanguage; ?>';
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Search Modal functionality
        const searchModal = document.getElementById('searchModal');
        const searchButton = document.getElementById('searchButton');
        const closeSearchModalBtn = document.getElementById('closeSearchModal');
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const noResults = document.getElementById('noResults');

        function showSearchModal() {
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            searchInput.focus();
        }

        function hideSearchModal() {
            searchModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            searchInput.value = '';
            searchResults.innerHTML = '';
            noResults.classList.add('hidden');
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
            if (searchProduct.languages && searchProduct.languages[currentLang]) {
                return searchProduct.languages[currentLang];
            }
            if (searchProduct.languages && searchProduct.languages['en']) {
                return searchProduct.languages['en'];
            }
            return Object.values(searchProduct.languages)[0];
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
                    window.location.href = 'product_detail.php?base_id=' + (product.base_product_id || product.id);
                };

                resultItem.innerHTML = `
                    <img src="${product.image || '/kouprey/public/assets/images/product-medium.png'}" alt="${product.name}" class="w-12 h-12 object-contain mr-4 rounded">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800">${product.name}</h4>
                        <p class="text-sm text-gray-600">${product.price ? '$' + parseFloat(product.price).toFixed(2) : ''}</p>
                    </div>
                `;
                searchResults.appendChild(resultItem);
            });
        }

        function performSearch(query) {
            const filteredProducts = searchProductsData.filter(product => {
                const nameMatch = product.all_names.toLowerCase().includes(query.toLowerCase());
                const descMatch = product.all_descriptions.toLowerCase().includes(query.toLowerCase());
                return nameMatch || descMatch;
            });
            const displayProducts = filteredProducts.map(product => getDisplayProduct(product, query));
            displaySearchResults(displayProducts);
        }

        if (searchButton) searchButton.addEventListener('click', showSearchModal);
        if (closeSearchModalBtn) closeSearchModalBtn.addEventListener('click', hideSearchModal);
        if (searchModal) {
            searchModal.addEventListener('click', (e) => { 
                if (e.target === searchModal) hideSearchModal(); 
            });
        }
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                if (query.length > 0) performSearch(query);
                else { searchResults.innerHTML = ''; noResults.classList.add('hidden'); }
            });
        }

        function changeLanguage(lang) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'set_language=' + encodeURIComponent(lang)
            }).then(() => {
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


        
        // Inner Zoom Implementation (Focused on Related Products)
        (function(){
            const zoomConfigs = [
                { containerSelector: '.product-image-container', targetSelector: 'img.main-img' }
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
</body>
</html>
