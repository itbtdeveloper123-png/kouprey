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


// Get shared catalog and search data from cache
$catalogData = getCatalogData($currentLanguage);
$searchProducts = $catalogData['searchProducts'] ?? [];
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
    <link rel="stylesheet" href="/kouprey/public/css/rte-content.css?v=1.6">
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

    <!-- Header (Native App Bar Layout) -->
    <header class="px-3 sm:px-4 md:px-6 py-2.5 md:py-3 sticky top-0 z-50 transition-all duration-300 bg-white/70 backdrop-blur-xl border-b border-gray-100">
        <div class="flex items-center justify-between max-w-6xl mx-auto h-full">
            <!-- Left: Back Button -->
            <div class="flex items-center">
                <a href="product.php#products" class="h-10 px-3 rounded-full bg-gray-100/90 hover:bg-orange-50 hover:text-orange-600 text-gray-700 transition-all duration-200 flex items-center gap-1.5 text-xs font-bold active:scale-95 shadow-2xs border border-gray-200/50">
                    <i class="fas fa-chevron-left text-xs"></i>
                    <span class="hidden sm:inline"><?php echo $currentLanguage == 'km' ? 'ត្រឡប់ក្រោយ' : 'Back'; ?></span>
                </a>
            </div>

            <!-- Center: Brand Logo -->
            <div class="flex items-center justify-center">
                <a href="product.php" class="flex items-center transform active:scale-95 transition-transform">
                    <img src="/kouprey/public/assets/images/logo-black.png" onerror="if(this.src.indexOf('assets/images/logo-black.png')===-1){this.src='assets/images/logo-black.png';}else{this.src='/kouprey/public/assets/images/logo.png';}" alt="<?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?>" class="h-10 sm:h-12 w-auto object-contain brightness-0" style="filter: brightness(0);">
                </a>
            </div>
            
            <!-- Right: Language Switcher -->
            <div class="flex items-center gap-2">
                <button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center gap-1.5 bg-gray-100/90 hover:bg-gray-200/90 rounded-full px-3 py-1.5 transition-all active:scale-95 border border-gray-200/50 shadow-2xs text-xs font-bold text-gray-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span><?php echo getCurrentLanguage() === 'en' ? 'EN' : 'KM'; ?></span>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 sm:py-6 pb-24 md:pb-16">
        
        <!-- Breadcrumb -->
        <nav class="flex items-center text-xs md:text-sm text-gray-500 mb-3 md:mb-6 overflow-x-auto scrollbar-none whitespace-nowrap py-1" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-1.5 md:space-x-2">
                <li class="flex items-center">
                    <a href="product.php" class="hover:text-orange-600 transition-colors flex items-center gap-1">
                        <i class="fas fa-home text-xs"></i>
                        <span class="hidden sm:inline"><?php echo htmlspecialchars(getSetting('nav_home', 'Home')); ?></span>
                    </a>
                    <i class="fas fa-chevron-right text-[9px] mx-1.5 text-gray-300"></i>
                </li>
                <li class="flex items-center">
                    <a href="product.php#products" class="hover:text-orange-600 transition-colors">
                        <?php echo htmlspecialchars(getSetting('nav_product', $currentLanguage == 'km' ? 'ផលិតផល' : 'Products')); ?>
                    </a>
                    <i class="fas fa-chevron-right text-[9px] mx-1.5 text-gray-300"></i>
                </li>
                <?php if ($categoryId): ?>
                <li class="flex items-center">
                    <a href="product.php?category=<?php echo $categoryId; ?>#products" class="hover:text-orange-600 transition-colors">
                        <?php echo htmlspecialchars($categoryName); ?>
                    </a>
                    <i class="fas fa-chevron-right text-[9px] mx-1.5 text-gray-300"></i>
                </li>
                <?php endif; ?>
                <li class="text-gray-900 font-bold truncate max-w-[140px] sm:max-w-[200px] md:max-w-xs" aria-current="page">
                    <?php echo htmlspecialchars($product['name']); ?>
                </li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:gap-14 items-start">
            
            <!-- Left: Image Gallery Showcase -->
            <div class="space-y-4 lg:sticky lg:top-24" data-aos="fade-right">
                <div class="product-image-frame w-full h-72 sm:h-84 md:h-[420px] lg:h-[480px] p-4 sm:p-6 md:p-8 flex justify-center items-center rounded-3xl bg-gradient-to-b from-gray-50/90 via-amber-50/15 to-white border border-gray-100/90 shadow-xs relative overflow-hidden">
                    <!-- Badges -->
                    <div class="absolute top-3 left-3 md:top-4 md:left-4 flex flex-col gap-1.5 z-20 pointer-events-none">
                        <?php if ($product['featured']): ?>
                            <span class="bg-yellow-500/95 text-white px-2.5 py-1 rounded-xl text-[10px] md:text-xs font-bold shadow-xs flex items-center gap-1.5 backdrop-blur-sm">
                                <i class="fas fa-star text-[9px]"></i> FEATURED
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($product['best_seller'])): ?>
                            <span class="bg-red-500/95 text-white px-2.5 py-1 rounded-xl text-[10px] md:text-xs font-bold shadow-xs flex items-center gap-1.5 backdrop-blur-sm">
                                <i class="fas fa-fire text-[9px]"></i> HOT
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Category Pill -->
                    <div class="absolute top-3 right-3 md:top-4 md:right-4 z-20">
                        <span class="bg-white/95 text-gray-700 px-2.5 py-1 rounded-xl text-[10px] md:text-xs font-bold shadow-xs border border-gray-100 flex items-center gap-1 backdrop-blur-sm">
                            <i class="fas fa-tag text-orange-500 text-[9px]"></i>
                            <span><?php echo htmlspecialchars($categoryName); ?></span>
                        </span>
                    </div>

                    <!-- Main Image with Inner Zoom -->
                    <div class="product-image-container w-full h-full flex items-center justify-center relative overflow-hidden">
                        <img src="<?php echo htmlspecialchars($product['image'] ?: '/kouprey/public/assets/images/product-medium.png'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             loading="eager"
                             decoding="async"
                             class="main-img max-w-full max-h-full object-contain filter drop-shadow-xl transform transition-transform duration-500 hover:scale-105">
                    </div>
                </div>
            </div>

            <!-- Right: Product Details & Specs -->
            <div class="space-y-6" data-aos="fade-left">
                <div>
                    <!-- Product Title -->
                    <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 mb-3 leading-snug tracking-tight">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>
                    
                    <!-- Rating & Reviews Bar -->
                    <div class="flex items-center gap-3 mb-4 pb-4 border-b border-gray-100">
                        <div class="flex items-center text-amber-400 gap-0.5">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $i <= round($avgRating) ? '<i class="fas fa-star text-xs sm:text-sm"></i>' : '<i class="far fa-star text-xs sm:text-sm text-gray-200"></i>';
                            }
                            ?>
                        </div>
                        <span class="text-xs sm:text-sm text-gray-500 font-semibold">
                            <strong class="text-gray-800"><?php echo number_format($avgRating, 1); ?></strong> (<?php echo $totalReviews; ?> <?php echo $currentLanguage == 'km' ? 'ការវាយតម្លៃ' : 'Reviews'; ?>)
                        </span>
                    </div>

                    <!-- Quick Info Grid (Weight, Roast, Quality) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-3 mb-6">
                        <!-- Weight -->
                        <div class="bg-gradient-to-br from-orange-50/60 to-white border border-orange-100/80 rounded-2xl p-2.5 sm:p-3 flex items-center gap-2.5 shadow-2xs">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-orange-500/10 text-orange-600 flex items-center justify-center shrink-0">
                                <i class="fas fa-weight-hanging text-sm sm:text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[9px] sm:text-[10px] font-bold text-orange-600 uppercase tracking-wider"><?php echo htmlspecialchars(getSetting('modal_weight', $currentLanguage == 'km' ? 'ទម្ងន់' : 'Weight')); ?></div>
                                <div class="text-xs sm:text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($product['weight'] ?: '250g'); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($product['roast_level'])): ?>
                        <!-- Roast Level -->
                        <div class="bg-gradient-to-br from-amber-50/60 to-white border border-amber-100/80 rounded-2xl p-2.5 sm:p-3 flex items-center gap-2.5 shadow-2xs">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-amber-600/10 text-amber-700 flex items-center justify-center shrink-0">
                                <i class="fas fa-fire text-sm sm:text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[9px] sm:text-[10px] font-bold text-amber-700 uppercase tracking-wider"><?php echo htmlspecialchars(getSetting('modal_roast_level', $currentLanguage == 'km' ? 'កម្រិតលីង' : 'Roast')); ?></div>
                                <div class="text-xs sm:text-sm font-bold text-gray-900 truncate"><?php echo htmlspecialchars($product['roast_level']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Quality -->
                        <div class="bg-gradient-to-br from-emerald-50/60 to-white border border-emerald-100/80 rounded-2xl p-2.5 sm:p-3 flex items-center gap-2.5 shadow-2xs">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                                <i class="fas fa-certificate text-sm sm:text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[9px] sm:text-[10px] font-bold text-emerald-600 uppercase tracking-wider"><?php echo $currentLanguage == 'km' ? 'គុណភាព' : 'Quality'; ?></div>
                                <div class="text-xs sm:text-sm font-bold text-gray-900 truncate">Premium</div>
                            </div>
                        </div>
                    </div>

                    <!-- Short Description -->
                    <div class="text-gray-600 leading-relaxed mb-6">
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Product Sections (Detailed Information) -->
                <div class="space-y-4">
                    <?php if (!empty($product['detailed_description'])): ?>
                    <div class="bg-gray-50/80 border border-gray-100 rounded-2xl p-4 sm:p-5">
                        <h3 class="text-sm sm:text-base font-bold text-gray-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-info-circle text-orange-500"></i>
                            <span><?php echo $currentLanguage == 'km' ? 'ព័ត៌មានលម្អិតបន្ថែម' : 'Detailed Information'; ?></span>
                        </h3>
                        <p class="text-xs sm:text-sm text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['detailed_description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Custom Fields Section -->
                    <?php 
                    $customFields = json_decode($product['custom_fields'] ?? '{}', true);
                    if (!empty($customFields)):
                        echo '<div class="space-y-6 mt-6 border-t border-gray-100 pt-6">';
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
                                                $valuesHtml .= '<span class="font-bold text-gray-900 ml-4">' . htmlspecialchars($val) . '</span>';
                                            }
                                        }
                                        
                                        $rowsGrid .= '
                                            <div class="flex justify-between items-center flex-wrap sm:flex-nowrap gap-2 py-2.5 border-b border-gray-100 last:border-0 hover:bg-gray-50 px-3 transition-colors rounded-lg">
                                                <span class="text-xs sm:text-sm text-gray-500 font-medium">' . htmlspecialchars($label) . '</span>
                                                <div class="text-xs sm:text-sm font-secondary">' . $valuesHtml . '</div>
                                            </div>';
                                    }
                                    
                                    $fieldHtml = '<div class="mt-2 bg-white border border-gray-200/80 rounded-2xl overflow-hidden shadow-2xs">' . $rowsGrid . '</div>';
                                else:
                                    // Default Text Layout with Card Styling
                                    $fieldValue = $fieldData['value'][$lang] ?? $fieldData['value']['en'] ?? '';
                                    if ($fieldValue) {
                                        $fieldHtml = '
                                        <div class="mt-2 bg-gradient-to-br from-gray-50 to-white border border-gray-100 rounded-2xl p-4 shadow-2xs">
                                            <p class="text-gray-700 leading-relaxed text-xs sm:text-sm md:text-base font-medium">' . nl2br(htmlspecialchars($fieldValue)) . '</p>
                                        </div>';
                                    }
                                endif;
                            elseif (is_string($fieldData)):
                                // Legacy simple string format
                                $fieldName = str_replace(':', '', $fieldId);
                                $fieldHtml = '
                                <div class="mt-2 bg-gradient-to-br from-gray-50 to-white border border-gray-100 rounded-2xl p-4 shadow-2xs">
                                    <p class="text-gray-700 leading-relaxed text-xs sm:text-sm md:text-base font-medium">' . nl2br(htmlspecialchars($fieldData)) . '</p>
                                </div>';
                            endif;

                            if ($fieldHtml):
                    ?>
                        <div class="animate-fade-in-up">
                            <h3 class="text-sm sm:text-base font-bold text-gray-900 flex items-center gap-2">
                                <span class="text-orange-600 text-sm">✦</span>
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
        <section class="mt-12 md:mt-24 max-w-5xl mx-auto" data-aos="fade-up">
            <div class="text-center mb-8 md:mb-14">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars(getSetting('modal_customer_reviews', 'Customer Reviews')); ?></h2>
                <div class="w-16 h-1 bg-gradient-to-r from-orange-400 to-orange-600 mx-auto rounded-full"></div>
                <p class="text-gray-500 mt-3 text-sm sm:text-base">Real feedback from our coffee community</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 mb-12">
                <!-- Rating Summary -->
                <div class="lg:col-span-4 lg:sticky lg:top-24 h-fit">
                    <div class="glass-card p-5 sm:p-6 md:p-8 text-center border border-orange-100/60 rounded-3xl">
                        <div class="text-5xl sm:text-6xl md:text-7xl font-bold bg-gradient-to-br from-gray-900 to-gray-600 bg-clip-text text-transparent mb-1.5">
                            <?php echo number_format($avgRating, 1); ?>
                        </div>
                        <div class="flex justify-center text-amber-400 text-lg mb-2">
                            <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $i <= round($avgRating) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-gray-200"></i>';
                            }
                            ?>
                        </div>
                        <div class="text-gray-500 text-xs sm:text-sm font-medium mb-6">Based on <?php echo $totalReviews; ?> reviews</div>
                        
                        <!-- Star Bars -->
                        <div class="space-y-2 sm:space-y-3">
                            <?php
                            $starCounts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
                            foreach($reviews as $r) {
                                if(isset($starCounts[(int)$r['rating']])) $starCounts[(int)$r['rating']]++;
                            }
                            for($i=5; $i>=1; $i--):
                                $percent = $totalReviews > 0 ? ($starCounts[$i] / $totalReviews) * 100 : 0;
                            ?>
                            <div class="flex items-center gap-2 sm:gap-4 text-xs sm:text-sm">
                                <span class="w-3 font-bold text-gray-700"><?php echo $i; ?></span>
                                <div class="flex-1 h-1.5 sm:h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-orange-400 rounded-full transition-all duration-1000" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                                <span class="w-8 sm:w-10 text-gray-400 text-[10px] sm:text-xs font-semibold"><?php echo round($percent); ?>%</span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Review Feed -->
                <div class="lg:col-span-8 space-y-4 sm:space-y-6">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="glass-card p-4 sm:p-6 md:p-8 border border-white/60 hover:border-orange-200 transition-all duration-300 rounded-2xl group">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-orange-100 to-orange-200 rounded-xl flex items-center justify-center text-orange-600 font-bold text-base shadow-inner group-hover:scale-105 transition-transform">
                                        <?php echo strtoupper(substr($review['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <h4 class="font-bold text-gray-900 text-sm sm:text-base"><?php echo htmlspecialchars($review['name']); ?></h4>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-green-100 text-green-600 uppercase tracking-tighter">
                                                <i class="fas fa-check-circle mr-0.5"></i>
                                            </span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 font-medium uppercase mt-0.5"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="flex text-amber-400 gap-0.5 bg-yellow-50/50 px-2.5 py-1 rounded-lg border border-yellow-100/70 self-start sm:self-auto">
                                    <?php 
                                    for($i=1; $i<=5; $i++) {
                                        echo $i <= $review['rating'] ? '<i class="fas fa-star text-xs"></i>' : '<i class="far fa-star text-gray-200 text-xs"></i>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="relative">
                                <p class="text-gray-600 text-sm sm:text-base leading-relaxed italic">
                                    "<?php echo htmlspecialchars($review['review']); ?>"
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12 sm:py-16 glass-card bg-gray-50/50 border border-dashed border-gray-200 rounded-3xl">
                            <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="far fa-comment-dots text-2xl text-gray-400"></i>
                            </div>
                            <h4 class="text-base sm:text-lg font-bold text-gray-800 mb-1">No reviews yet</h4>
                            <p class="text-gray-500 text-xs sm:text-sm max-w-xs mx-auto"><?php echo htmlspecialchars(getSetting('no_reviews', 'Be the first to share your experience with this premium coffee!')); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Related Products (Same Category) -->
        <?php if (!empty($relatedProducts)): ?>
        <section class="mt-12 md:mt-24" data-aos="fade-up">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2.5">
                    <i class="fas fa-coffee text-orange-500"></i> <?php echo htmlspecialchars(getSetting('related_products_title', 'You May Also Like')); ?>
                </h3>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4 md:gap-6">
                <?php foreach (array_slice($relatedProducts, 0, 5) as $rp): ?>
                <a href="product_detail.php?base_id=<?php echo $rp['base_product_id']; ?>" class="group block bg-white rounded-2xl p-2.5 sm:p-3 border border-gray-100 shadow-2xs hover:shadow-md transition-all active:scale-98">
                    <div class="product-image-container aspect-square mb-2 flex items-center justify-center relative overflow-hidden text-center rounded-xl bg-gray-50/70">
                        <div class="absolute inset-0 flex items-center justify-center p-2">
                            <img src="<?php echo htmlspecialchars($rp['image'] ?: '/kouprey/public/assets/images/product-medium.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($rp['name']); ?>" 
                                 loading="lazy"
                                 decoding="async"
                                 class="main-img max-w-full max-h-full object-contain relative z-10 transform group-hover:scale-105 transition-transform" 
                                 style="filter: drop-shadow(0 6px 10px rgba(0,0,0,0.12));">
                        </div>
                    </div>
                    <div class="text-center pt-1">
                        <h4 class="font-bold text-gray-900 text-xs sm:text-sm line-clamp-2 leading-tight min-h-[1.9rem] flex items-center justify-center mb-1 group-hover:text-orange-600 transition-colors">
                            <?php echo htmlspecialchars($rp['name']); ?>
                        </h4>
                        <p class="text-[9px] sm:text-[10px] text-gray-400 uppercase tracking-wider font-semibold"><?php echo htmlspecialchars($categoryName); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <!-- Mobile Sticky Floating Bottom Bar -->
    <div class="md:hidden fixed bottom-4 left-3 right-3 z-40">
        <div class="bg-white/85 backdrop-blur-2xl border border-white/80 shadow-[0_12px_40px_rgba(0,0,0,0.18)] rounded-2xl p-2 flex items-center gap-2">
            <!-- Back to Catalog -->
            <a href="product.php#products" class="flex-1 py-2.5 px-3 rounded-xl bg-gray-100/90 hover:bg-gray-200 text-gray-800 font-bold text-xs flex items-center justify-center gap-1.5 transition-all active:scale-95 shadow-2xs">
                <i class="fas fa-th-large text-orange-500 text-xs"></i>
                <span class="truncate"><?php echo $currentLanguage == 'km' ? 'ផលិតផលផ្សេងទៀត' : 'All Products'; ?></span>
            </a>

            <!-- Inquire / Contact via Telegram -->
            <?php 
            $tgUrl = getSetting('social_telegram', 'https://t.me/Bos_Sauveli98');
            $phone = getSetting('company_phone', '+855 12 345 678');
            ?>
            <a href="<?php echo htmlspecialchars($tgUrl ?: 'tel:' . preg_replace('/[^0-9+]/', '', $phone)); ?>" 
               target="_blank" 
               class="flex-1 py-2.5 px-3 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition-all active:scale-95 shadow-md shadow-orange-500/25">
                <i class="fab fa-telegram-plane text-sm"></i>
                <span class="truncate"><?php echo $currentLanguage == 'km' ? 'កុម្ម៉ង់ / សាកសួរ' : 'Order / Inquire'; ?></span>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-auto bg-gray-900 text-white py-12">
        <div class="max-w-6xl mx-auto px-4 md:px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2 sm:gap-3 mb-4">
                        <img src="/kouprey/public/assets/images/logo.png" onerror="if(this.src.indexOf('assets/images/logo.png')===-1){this.src='assets/images/logo.png';}else{this.src='https://i.ibb.co/Wv0j3ZTQ/logo.png';}" alt="<?php echo htmlspecialchars(getSetting('company_name', $currentLanguage == 'km' ? 'ហ្គោ ហ្គោ' : 'KouPrey')); ?>" class="h-12 sm:h-14 md:h-16 w-auto object-contain filter drop-shadow-md shrink-0">
                        <span class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black text-white tracking-wide leading-none whitespace-nowrap"><?php echo htmlspecialchars(getSetting('company_name', $currentLanguage == 'km' ? 'ហ្គោ ហ្គោ' : 'KouPrey Coffee')); ?></span>
                    </div>
                    <p class="text-gray-300 mb-4 leading-relaxed">
                        <?php echo htmlspecialchars(getSetting('site_description', $currentLanguage == 'km' ? 'ធ្វើឱ្យគ្រឿងភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិ' : 'Premium coffee beans and sustainable brewing solutions')); ?>
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
                        <li><a href="product.php" class="text-gray-400 hover:text-white transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-yellow-400"></i><?php echo htmlspecialchars(getSetting('nav_product', 'Products')); ?></a></li>
                        <li><a href="features.php" class="text-gray-400 hover:text-white transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-yellow-400"></i><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></a></li>
                        <li><a href="reviews.php" class="text-gray-400 hover:text-white transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-yellow-400"></i><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition-colors flex items-center"><i class="fas fa-chevron-right text-xs mr-2 text-yellow-400"></i><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></a></li>
                    </ul>
                </div>

                <!-- Social & Legal -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-white"><?php echo htmlspecialchars(getSetting('footer_connect', 'Connect With Us')); ?></h3>
                    <div class="flex space-x-4 mb-6">
                        <?php if (getSetting('social_facebook')): ?><a href="<?php echo htmlspecialchars(getSetting('social_facebook')); ?>" target="_blank" class="text-gray-300 hover:text-blue-400 transition-colors"><i class="fab fa-facebook-f text-xl"></i></a><?php endif; ?>
                        <?php if (getSetting('social_telegram')): ?><a href="<?php echo htmlspecialchars(getSetting('social_telegram')); ?>" target="_blank" class="text-gray-300 hover:text-blue-500 transition-colors"><i class="fab fa-telegram-plane text-xl"></i></a><?php endif; ?>
                        <?php if (getSetting('social_tiktok')): ?><a href="<?php echo htmlspecialchars(getSetting('social_tiktok')); ?>" target="_blank" class="text-gray-300 hover:text-white transition-colors"><i class="fab fa-tiktok text-xl"></i></a><?php endif; ?>
                        <?php if (getSetting('social_instagram')): ?><a href="<?php echo htmlspecialchars(getSetting('social_instagram')); ?>" target="_blank" class="text-gray-300 hover:text-pink-500 transition-colors"><i class="fab fa-instagram text-xl"></i></a><?php endif; ?>
                    </div>
                    <div class="space-y-2 text-sm text-gray-400">
                        <div><a href="privacy_policy.php" class="hover:text-white transition-colors"><?php echo htmlspecialchars(getSetting('nav_privacy_policy', 'Privacy Policy')); ?></a></div>
                        <div><a href="terms_of_service.php" class="hover:text-white transition-colors"><?php echo htmlspecialchars(getSetting('nav_terms_of_service', 'Terms of Service')); ?></a></div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-400">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('company_name', $currentLanguage == 'km' ? 'ហ្គោ ហ្គោ' : 'KouPrey')); ?>. All rights reserved.
            </div>
        </div>
    </footer>




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
    <!-- Instant Navigation & Touch Prefetcher -->
    <script src="/kouprey/public/assets/js/instant-nav.js" defer></script>
</body>
</html>
