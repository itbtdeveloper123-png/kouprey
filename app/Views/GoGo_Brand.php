<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';
require_once __DIR__ . '/../Config/catalog_cache.php';

// Determine language (default to Khmer, support switch to English, persist in session)
if (isset($_GET['lang'])) {
    $currentLanguage = ($_GET['lang'] === 'en') ? 'en' : 'km';
    $_SESSION['catalog_lang'] = $currentLanguage;
} elseif (isset($_SESSION['catalog_lang'])) {
    $currentLanguage = ($_SESSION['catalog_lang'] === 'en') ? 'en' : 'km';
} else {
    $currentLanguage = 'km';
}

// Load catalog data (Cached for sub-millisecond loading)
$catalogData = getCatalogData($currentLanguage);
$products = $catalogData['products'] ?? [];
$categories = $catalogData['categories'] ?? [];

// General store settings
$storeName = getSetting('company_name', $currentLanguage === 'km' ? 'ហ្គោ ហ្គោ' : 'GoGo Brand');
if ($currentLanguage === 'km' && ($storeName === 'GoGo Brand' || $storeName === 'GoGo' || empty($storeName))) {
    $storeName = 'ហ្គោ ហ្គោ';
}
$storeLogo = getSetting('company_logo', '');
if (empty($storeLogo)) {
    $storeLogo = getSetting('site_logo', '/kouprey/public/assets/images/logo.png');
}
$storeTelegram = getSetting('social_telegram', 'https://t.me/Bos_Sauveli98');
if (empty($storeTelegram)) {
    $storeTelegram = 'https://t.me/Bos_Sauveli98';
}
$tgUsername = preg_replace('#^https?://t\.me/#i', '', trim($storeTelegram));
$tgUsername = ltrim($tgUsername, '@');
$tgUsername = explode('/', $tgUsername)[0];
$tgUsername = explode('?', $tgUsername)[0];

// Contact, Location & About Settings
$storePhone = getSetting('company_phone', '+855 12 345 678');
$storeEmail = getSetting('company_email', 'info@kouprey.com');
$storeAddress = getSetting('company_address', $currentLanguage === 'km' ? 'រាជធានីភ្នំពេញ ប្រទេសកម្ពុជា' : 'Phnom Penh, Cambodia');
$storeHours = getSetting('company_hours', $currentLanguage === 'km' ? 'រៀងរាល់ថ្ងៃ: 7:00 ព្រឹក - 8:00 យប់' : 'Daily: 7:00 AM - 8:00 PM');
$storeDescription = getSetting('site_description', $currentLanguage === 'km' ? 'ធ្វើឱ្យគ្រឿងភេសជ្ជៈរបស់អ្នកកាន់តែមានរស់ជាតិ' : 'Make your beverages more delicious');

// External Links
$mapsUrl = getSetting('google_map_link', 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA');
if (empty($mapsUrl)) {
    $mapsUrl = 'https://maps.app.goo.gl/v88Vyavc1UoykzgNA';
}

$websiteUrl = 'https://www.kouprey.asia/';

// Build clean categories list and initialize count
$cleanCategories = [];
$categoryCounts = [];

foreach ($categories as $cat) {
    $catKey = (string)($cat['base_category_id'] ?: $cat['id']);
    $catRawName = $cat['name'] ?? '';
    $catCleanName = function_exists('normalizeKhmerSpelling') ? normalizeKhmerSpelling($catRawName) : str_replace(["\xE1\x9E\x9F\xE1\x9E\xBB\xE1\x9E\xB8", "សុី"], "ស៊ី", $catRawName);
    $cleanCategories[$catKey] = [
        'key' => $catKey,
        'base_id' => (string)($cat['base_category_id'] ?? ''),
        'id' => (string)($cat['id'] ?? ''),
        'name' => $catCleanName
    ];
    $categoryCounts[$catKey] = 0;
}

// Explicitly order categories: Syrup (19) first, Powder (13) second, followed by others
uasort($cleanCategories, function($a, $b) {
    $priority = function($c) {
        $baseId = (string)($c['base_id'] ?? '');
        $id = (string)($c['id'] ?? '');
        $name = $c['name'] ?? '';
        if ($baseId === '19' || $id === '19' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/iu', $name)) {
            return 1;
        }
        if ($baseId === '13' || $id === '13' || preg_match('/powder|matcha|ម្សៅ/iu', $name)) {
            return 2;
        }
        return 3;
    };

    $pA = $priority($a);
    $pB = $priority($b);
    if ($pA !== $pB) {
        return $pA <=> $pB;
    }
    return strcmp($a['name'] ?? '', $b['name'] ?? '');
});

// Clean products array and associate with matching category
$cleanProducts = [];
foreach ($products as $p) {
    $pBaseId = (string)($p['base_category_id'] ?? '');
    $pCatId = (string)($p['category_id'] ?? '');

    $matchedCatKey = '';
    $matchedCatName = '';

    foreach ($cleanCategories as $catKey => $cat) {
        $cBaseId = $cat['base_id'];
        $cId = $cat['id'];

        $matches = false;
        if (!empty($cBaseId) && !empty($pBaseId) && $cBaseId === $pBaseId) {
            $matches = true;
        } elseif (!empty($cId) && !empty($pCatId) && $cId === $pCatId) {
            $matches = true;
        } elseif (!empty($cBaseId) && !empty($pCatId) && $cBaseId === $pCatId) {
            $matches = true;
        } elseif (($cBaseId === '19' || $cId === '19') && preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i', $p['name'] ?? '')) {
            $matches = true;
        } elseif (($cBaseId === '13' || $cId === '13') && preg_match('/powder|matcha|ម្សៅ/i', $p['name'] ?? '')) {
            $matches = true;
        }

        if ($matches) {
            $matchedCatKey = $catKey;
            $matchedCatName = $cat['name'];
            break;
        }
    }

    if (!empty($matchedCatKey) && isset($categoryCounts[$matchedCatKey])) {
        $categoryCounts[$matchedCatKey]++;
    }

    // Parse custom fields if available
    $customFields = [];
    if (!empty($p['custom_fields'])) {
        $decoded = is_string($p['custom_fields']) ? json_decode($p['custom_fields'], true) : $p['custom_fields'];
        if (is_array($decoded)) {
            $customFields = $decoded;
        }
    }

    $image = $p['image'] ?: '/kouprey/public/assets/images/product-medium.png';
    $rawProdName = $p['name'] ?? '';
    $cleanProdName = function_exists('normalizeKhmerSpelling') ? normalizeKhmerSpelling($rawProdName) : str_replace(["\xE1\x9E\x9F\xE1\x9E\xBB\xE1\x9E\xB8", "សុី"], "ស៊ី", $rawProdName);
    $cleanCatName = function_exists('normalizeKhmerSpelling') ? normalizeKhmerSpelling($matchedCatName) : str_replace(["\xE1\x9E\x9F\xE1\x9E\xBB\xE1\x9E\xB8", "សុី"], "ស៊ី", $matchedCatName);

    $cleanProducts[] = [
        'id' => (int)($p['id'] ?? 0),
        'base_id' => (int)($p['base_product_id'] ?? $p['id'] ?? 0),
        'name' => $cleanProdName,
        'price' => (float)($p['price'] ?? 0),
        'image' => $image,
        'description' => trim($p['description'] ?? ''),
        'detailed_description' => trim($p['detailed_description'] ?? ''),
        'weight' => trim($p['weight'] ?? ''),
        'roast_level' => trim($p['roast_level'] ?? ''),
        'custom_fields' => $customFields,
        'category_key' => $matchedCatKey,
        'category_name' => $cleanCatName ?: ($currentLanguage === 'km' ? 'ផលិតផល' : 'Product'),
        'featured' => !empty($p['featured']),
        'best_seller' => !empty($p['best_seller']),
        'avg_rating' => (float)($p['avg_rating'] ?? 5.0),
        'review_count' => (int)($p['review_count'] ?? 0)
    ];
}

// Explicitly sort products: Syrup first, Powder second, followed by others
usort($cleanProducts, function($a, $b) {
    $priority = function($p) {
        $catKey = (string)($p['category_key'] ?? '');
        $name = $p['name'] ?? '';
        if ($catKey === '19' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/iu', $name)) {
            return 1;
        }
        if ($catKey === '13' || preg_match('/powder|matcha|ម្សៅ/iu', $name)) {
            return 2;
        }
        return 3;
    };

    $pA = $priority($a);
    $pB = $priority($b);
    if ($pA !== $pB) {
        return $pA <=> $pB;
    }
    // Within same group: featured first, then best sellers, then newest
    if (!empty($a['featured']) !== !empty($b['featured'])) {
        return !empty($b['featured']) ? 1 : -1;
    }
    if (!empty($a['best_seller']) !== !empty($b['best_seller'])) {
        return !empty($b['best_seller']) ? 1 : -1;
    }
    return $b['id'] <=> $a['id'];
});

// Select spotlight products for top compact banner slider, prioritizing Syrup and Powder
$bannerProducts = [];

// 1. Featured or best-selling Syrups and Powders first
foreach ($cleanProducts as $p) {
    $catKey = (string)($p['category_key'] ?? '');
    $name = $p['name'] ?? '';
    $isSyrupOrPowder = ($catKey === '19' || $catKey === '13' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ|powder|matcha|ម្សៅ/iu', $name));
    if ($isSyrupOrPowder && (!empty($p['featured']) || !empty($p['best_seller']))) {
        $bannerProducts[] = $p;
    }
    if (count($bannerProducts) >= 5) break;
}

// 2. Any other Syrups and Powders if we need more slides
if (count($bannerProducts) < 5) {
    foreach ($cleanProducts as $p) {
        $catKey = (string)($p['category_key'] ?? '');
        $name = $p['name'] ?? '';
        $isSyrupOrPowder = ($catKey === '19' || $catKey === '13' || preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ|powder|matcha|ម្សៅ/iu', $name));
        if ($isSyrupOrPowder) {
            $exists = false;
            foreach ($bannerProducts as $bp) {
                if ($bp['id'] === $p['id']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $bannerProducts[] = $p;
            }
        }
        if (count($bannerProducts) >= 5) break;
    }
}

// 3. Fallback to other featured or best seller products if fewer than 3
if (count($bannerProducts) < 3) {
    foreach ($cleanProducts as $p) {
        $exists = false;
        foreach ($bannerProducts as $bp) {
            if ($bp['id'] === $p['id']) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $bannerProducts[] = $p;
        }
        if (count($bannerProducts) >= 5) break;
    }
}
$bannerProducts = array_slice($bannerProducts, 0, 5);

$totalProductCount = count($cleanProducts);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($storeName); ?> - GoGo Brand Catalog</title>

    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <!-- Telegram WebApp SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        // Execute immediately in <head> so iOS Telegram receives expand before rendering
        (function() {
            function earlyTrigger() {
                try {
                    if (window.Telegram && window.Telegram.WebView && window.Telegram.WebView.postEvent) {
                        window.Telegram.WebView.postEvent('web_app_expand');
                        window.Telegram.WebView.postEvent('web_app_request_fullscreen');
                        window.Telegram.WebView.postEvent('web_app_setup_swipe_behavior', false, { allow_vertical_swipe: false });
                    }
                    var tg = window.Telegram && window.Telegram.WebApp;
                    if (tg) {
                        if (typeof tg.ready === 'function') tg.ready();
                        if (typeof tg.expand === 'function') tg.expand();
                        if (typeof tg.disableVerticalSwipes === 'function') tg.disableVerticalSwipes();
                        if (typeof tg.requestFullscreen === 'function') tg.requestFullscreen();
                    }
                } catch(e) {}
            }
            earlyTrigger();
            [20, 60, 120, 250, 450, 750, 1200, 2000].forEach(function(t) {
                setTimeout(earlyTrigger, t);
            });
            window.addEventListener('DOMContentLoaded', earlyTrigger);
            window.addEventListener('load', earlyTrigger);
        })();
    </script>

    <!-- Google Fonts (Kantumruy Pro & Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Swiper 11 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Kantumruy Pro"', '"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: var(--tg-theme-bg-color, #f8fafc);
            color: var(--tg-theme-text-color, #0f172a);
            font-family: 'Kantumruy Pro', 'Plus Jakarta Sans', sans-serif;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: none;
        }

        /* Hide native scrollbar for horizontal pills */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Bottom Sheet Transition */
        #product-modal {
            transition: visibility 0.25s ease;
        }
        #product-modal.sheet-hidden {
            visibility: hidden !important;
            pointer-events: none !important;
        }
        #product-modal.sheet-hidden * {
            pointer-events: none !important;
        }
        .sheet-backdrop {
            transition: opacity 0.25s ease-out;
        }
        .sheet-content {
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform;
        }
        .sheet-content.dragging {
            transition: none !important;
        }
        .sheet-drag-handle {
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
        }
        .img-fade-in {
            opacity: 0;
            transition: opacity 0.25s ease-in;
        }
        .img-fade-in.loaded {
            opacity: 1;
        }
        .sheet-hidden .sheet-backdrop {
            opacity: 0;
            pointer-events: none;
        }
        .sheet-hidden .sheet-content {
            transform: translateY(100%);
            pointer-events: none;
        }

        /* Telegram Safe Area Top Spacing */
        :root {
            --app-safe-top: 12px;
        }

        .tg-safe-header {
            padding-top: var(--tg-safe-top-dynamic, var(--app-safe-top));
            transition: padding-top 0.15s ease-out;
        }

        @media (min-width: 768px) {
            :root {
                --app-safe-top: 16px;
            }
        }

        /* Compact Banner Slider Custom Dots */
        .compact-banner-dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 9999px;
            background: #fed7aa;
            margin: 0 3px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .compact-banner-dot-active {
            width: 16px;
            background: #f97316;
            border-radius: 9999px;
        }

        /* Gentle Floating Micro-Animation for Bottom-Right Action Icons */
        @keyframes gentleBob {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        .fab-float-1 {
            animation: gentleBob 2.8s ease-in-out infinite;
            will-change: transform;
        }
        .fab-float-2 {
            animation: gentleBob 2.8s ease-in-out 0.4s infinite;
            will-change: transform;
        }
        .fab-float-3 {
            animation: gentleBob 2.8s ease-in-out 0.8s infinite;
            will-change: transform;
        }

        .fab-float-1:active, .fab-float-2:active, .fab-float-3:active {
            transform: scale(0.92);
            animation-play-state: paused;
        }

        button, .category-pill, [onclick] {
            cursor: pointer;
            touch-action: manipulation;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Container wrapper for clean desktop & mobile presentation -->
    <div class="w-full max-w-2xl mx-auto flex flex-col min-h-screen bg-white shadow-xs border-x border-gray-100">

        <!-- ───────────────────────────────────────────────────────────── -->
        <!-- Top Sticky Header: Safe Top Padding + Brand + Search + Category Pills -->
        <!-- ───────────────────────────────────────────────────────────── -->
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100/80 shadow-2xs tg-safe-header">
            <!-- Brand Row -->
            <div class="px-4 pb-2 pt-0.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <!-- Store Logo -->
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-gradient-to-br from-orange-50 to-orange-100/60 p-1 border border-orange-200/60 shadow-2xs flex items-center justify-center flex-shrink-0">
                        <img src="<?php echo htmlspecialchars($storeLogo); ?>" 
                             alt="Logo" 
                             class="w-full h-full object-contain"
                             onerror="this.src='/kouprey/public/assets/images/logo.png'">
                    </div>

                    <!-- Store Title (Clean Khmer title with generous line-height to prevent clipping descenders/ជើង) -->
                    <div class="flex items-center min-w-0 py-0.5">
                        <h1 class="text-base sm:text-lg font-extrabold text-gray-900 tracking-tight leading-normal py-0.5">
                            <?php echo htmlspecialchars($storeName); ?>
                        </h1>
                    </div>
                </div>

                <!-- Language Switcher Pill (KM | EN) -->
                <div class="flex items-center bg-gray-100/90 p-0.5 rounded-xl border border-gray-200/80 shadow-2xs flex-shrink-0" role="group" aria-label="Language Switcher">
                    <button onclick="switchLanguage('km')" 
                            type="button"
                            title="Khmer"
                            aria-label="Khmer Language"
                            class="px-2.5 py-1 rounded-lg text-xs transition-all <?php echo $currentLanguage === 'km' ? 'bg-white text-orange-600 shadow-xs font-black' : 'text-gray-500 hover:text-gray-900 font-semibold'; ?>">
                        KM
                    </button>
                    <button onclick="switchLanguage('en')" 
                            type="button"
                            title="English"
                            aria-label="English Language"
                            class="px-2.5 py-1 rounded-lg text-xs transition-all <?php echo $currentLanguage === 'en' ? 'bg-white text-orange-600 shadow-xs font-black' : 'text-gray-500 hover:text-gray-900 font-semibold'; ?>">
                        EN
                    </button>
                </div>
            </div>

            <!-- Personalized Greeting Strip (Dedicated Row Above Search Bar) -->
            <div id="user-greeting-container" class="hidden px-4 pb-2">
                <div class="flex items-center gap-1.5 text-xs text-gray-700 font-medium leading-normal py-0.5">
                    <span id="user-greeting-badge" class="leading-normal py-0.5"></span>
                </div>
            </div>

            <!-- Live Instant Search Bar -->
            <div class="px-4 pb-2.5">
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" 
                           id="search-input" 
                           placeholder="<?php echo $currentLanguage === 'km' ? 'ស្វែងរកឈ្មោះផលិតផល...' : 'Search products...'; ?>" 
                           class="w-full bg-gray-100 text-gray-900 placeholder-gray-400 text-xs sm:text-sm pl-9 pr-8 py-2.5 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500/40 focus:bg-white transition-all">
                    <button id="search-clear-btn" 
                            onclick="clearSearch()" 
                            class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-[10px] hover:bg-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Horizontal Category Filter Pills Row -->
            <div class="border-t border-gray-100 px-3 py-2">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth" id="category-pills">
                    <!-- All Products Pill -->
                    <button onclick="selectCategory('all', this)" 
                            data-cat-key="all"
                            class="category-pill flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold transition-all bg-orange-500 text-white shadow-xs">
                        <span><?php echo $currentLanguage === 'km' ? 'ទាំងអស់' : 'All'; ?></span>
                        <span class="ml-1 opacity-85 text-[10px]">(<?php echo $totalProductCount; ?>)</span>
                    </button>

                    <!-- Database Category Pills -->
                    <?php foreach ($cleanCategories as $catKey => $cat): 
                        $count = $categoryCounts[$catKey] ?? 0;
                    ?>
                        <button onclick="selectCategory('<?php echo htmlspecialchars($catKey); ?>', this)" 
                                data-cat-key="<?php echo htmlspecialchars($catKey); ?>"
                                class="category-pill flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200">
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                            <span class="ml-1 text-[10px] text-gray-400">(<?php echo $count; ?>)</span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </header>

        <!-- ───────────────────────────────────────────────────────────── -->
        <!-- Products Catalog Grid (Mobile 2-Column) -->
        <!-- ───────────────────────────────────────────────────────────── -->
        <main class="flex-1 px-3.5 pt-3 pb-28">
            <!-- ───────────────────────────────────────────────────────────── -->
            <!-- Top Compact Product Banner Slider (Spotlight) -->
            <!-- ───────────────────────────────────────────────────────────── -->
            <?php if (!empty($bannerProducts)): ?>
            <section id="top-banner-section" class="mb-3.5" aria-label="Spotlight Products">
                <div class="swiper compact-banner-swiper rounded-2xl overflow-hidden">
                    <div class="swiper-wrapper">
                        <?php foreach ($bannerProducts as $idx => $bp): 
                            $gradients = [
                                'from-amber-500/10 via-orange-500/10 to-amber-50/50 border-amber-200/60',
                                'from-rose-500/10 via-orange-500/10 to-rose-50/50 border-rose-200/60',
                                'from-emerald-500/10 via-teal-500/10 to-emerald-50/50 border-emerald-200/60',
                                'from-blue-500/10 via-indigo-500/10 to-sky-50/50 border-blue-200/60',
                                'from-purple-500/10 via-pink-500/10 to-purple-50/60 border-purple-200/60',
                            ];
                            $gradClass = $gradients[$idx % count($gradients)];
                        ?>
                        <div class="swiper-slide cursor-pointer" onclick="openProductModal(<?php echo (int)$bp['id']; ?>)">
                            <div class="relative w-full h-[98px] sm:h-[108px] bg-gradient-to-r <?php echo $gradClass; ?> border rounded-2xl p-2.5 sm:p-3 flex items-center justify-between overflow-hidden group shadow-2xs active:scale-[0.99] transition-all">
                                <!-- Background subtle light glow -->
                                <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/40 rounded-full blur-xl pointer-events-none"></div>

                                <!-- Left Info Section -->
                                <div class="flex-1 min-w-0 pr-2 z-10 flex flex-col justify-between h-full py-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <?php if (!empty($bp['best_seller'])): ?>
                                            <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider text-amber-800 bg-amber-200/80 px-1.5 py-0.5 rounded-md">
                                                <i class="fas fa-crown text-[8px] text-amber-600"></i>
                                                <span><?php echo $currentLanguage === 'km' ? 'លក់ដាច់' : 'HOT'; ?></span>
                                            </span>
                                        <?php elseif (!empty($bp['featured'])): ?>
                                            <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider text-orange-800 bg-orange-200/80 px-1.5 py-0.5 rounded-md">
                                                <i class="fas fa-fire text-[8px] text-orange-600"></i>
                                                <span><?php echo $currentLanguage === 'km' ? 'ពិសេស' : 'FEATURED'; ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider text-gray-700 bg-white/80 px-1.5 py-0.5 rounded-md">
                                                <i class="fas fa-star text-[8px] text-amber-500"></i>
                                                <span>TOP</span>
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-[10px] text-gray-500 font-medium truncate"><?php echo htmlspecialchars($bp['category_name']); ?></span>
                                    </div>

                                    <h3 class="text-xs sm:text-sm font-bold text-gray-900 truncate leading-snug group-hover:text-orange-600 transition-colors">
                                        <?php echo htmlspecialchars($bp['name']); ?>
                                    </h3>

                                    <div class="flex items-center gap-2">
                                        <span class="text-xs sm:text-sm font-extrabold text-orange-600">
                                            $<?php echo number_format((float)$bp['price'], 2); ?>
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-[9px] sm:text-[10px] font-semibold text-gray-700 bg-white/90 group-hover:bg-orange-500 group-hover:text-white px-2 py-0.5 rounded-full border border-gray-200/80 shadow-2xs transition-colors">
                                            <span><?php echo $currentLanguage === 'km' ? 'មើលលម្អិត' : 'View'; ?></span>
                                            <i class="fas fa-chevron-right text-[7px]"></i>
                                        </span>
                                    </div>
                                </div>

                                <!-- Right Image Section -->
                                <div class="relative w-16 h-16 sm:w-20 sm:h-20 flex-shrink-0 flex items-center justify-center z-10">
                                    <div class="absolute inset-0 bg-white/85 rounded-xl shadow-2xs border border-white/60"></div>
                                    <img src="<?php echo htmlspecialchars($bp['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($bp['name']); ?>" 
                                         class="relative w-14 h-14 sm:w-16 sm:h-16 object-contain drop-shadow-sm group-hover:scale-105 transition-transform duration-300"
                                         onerror="this.src='/kouprey/public/assets/images/logo.png'">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Pagination bullets -->
                    <div class="compact-banner-pagination flex justify-center items-center pt-1.5 pb-0.5"></div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Products Count / Active Filter Info -->
            <div class="flex items-center justify-between mb-3 px-1">
                <span class="text-xs font-bold text-gray-600" id="product-counter">
                    <?php echo $currentLanguage === 'km' ? 'ផលិតផលសរុប' : 'Total Products'; ?>: <?php echo $totalProductCount; ?>
                </span>
                <button type="button" 
                        class="text-xs text-orange-600 font-semibold cursor-pointer hover:underline" 
                        onclick="selectCategory('all'); clearSearch();">
                    <?php echo $currentLanguage === 'km' ? 'កំណត់ឡើងវិញ' : 'Reset Filters'; ?>
                </button>
            </div>

            <!-- Dynamic Product Cards Grid -->
            <div id="product-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <!-- Injected via JavaScript for instant performance -->
            </div>

            <!-- Empty State (No matching products) -->
            <div id="empty-state" class="hidden py-16 text-center">
                <div class="w-16 h-16 rounded-full bg-orange-50 text-orange-400 flex items-center justify-center mx-auto mb-3 text-2xl">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3 class="text-sm font-bold text-gray-800 mb-1">
                    <?php echo $currentLanguage === 'km' ? 'រកមិនឃើញផលិតផលទេ' : 'No products found'; ?>
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    <?php echo $currentLanguage === 'km' ? 'សូមជ្រើសរើសប្រភេទផ្សេង ឬស្វែងរកឈ្មោះផ្សេង' : 'Try another search term or category'; ?>
                </p>
                <button onclick="selectCategory('all'); clearSearch();" 
                        class="px-4 py-2 rounded-xl bg-orange-500 text-white text-xs font-bold shadow-xs active:scale-95 transition-transform">
                    <?php echo $currentLanguage === 'km' ? 'មើលផលិតផលទាំងអស់' : 'Show all products'; ?>
                </button>
            </div>
        </main>

    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Floating Action Dock (Bottom Right Stack: Website, Maps, About) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-full max-w-2xl px-4 pointer-events-none z-40 flex justify-end">
        <aside id="fab-dock" aria-label="Quick Actions" class="flex flex-col items-center gap-2.5 pointer-events-auto transition-all duration-300">
            <!-- 1. Website Button -->
            <a href="<?php echo htmlspecialchars($websiteUrl); ?>" 
               onclick="openExternalUrl('<?php echo htmlspecialchars($websiteUrl); ?>', event)" 
               target="_blank" 
               rel="noopener noreferrer"
               class="fab-float-1 group flex flex-col items-center focus:outline-none cursor-pointer"
               aria-label="<?php echo $currentLanguage === 'km' ? 'គេហទំព័រ' : 'Website'; ?>">
                <div class="w-11 h-11 rounded-full bg-white/95 backdrop-blur-md shadow-lg border border-gray-200/90 flex items-center justify-center text-blue-600 transition-all duration-200 group-hover:scale-105 group-hover:bg-blue-50 group-hover:border-blue-300 group-active:scale-95 shadow-blue-500/10">
                    <i class="fas fa-globe text-base"></i>
                </div>
                <span class="text-[9px] font-bold text-gray-700 bg-white/95 backdrop-blur-xs px-1.5 py-0.2 rounded-md shadow-2xs mt-0.5 border border-gray-200/60 leading-tight">
                    <?php echo $currentLanguage === 'km' ? 'គេហទំព័រ' : 'Website'; ?>
                </span>
            </a>

            <!-- 2. Maps Button -->
            <a href="<?php echo htmlspecialchars($mapsUrl); ?>" 
               onclick="openExternalUrl('<?php echo htmlspecialchars($mapsUrl); ?>', event)" 
               target="_blank" 
               rel="noopener noreferrer"
               class="fab-float-2 group flex flex-col items-center focus:outline-none cursor-pointer"
               aria-label="<?php echo $currentLanguage === 'km' ? 'ផែនទី' : 'Maps'; ?>">
                <div class="w-11 h-11 rounded-full bg-white/95 backdrop-blur-md shadow-lg border border-gray-200/90 flex items-center justify-center text-emerald-600 transition-all duration-200 group-hover:scale-105 group-hover:bg-emerald-50 group-hover:border-emerald-300 group-active:scale-95 shadow-emerald-500/10">
                    <i class="fas fa-map-marker-alt text-base"></i>
                </div>
                <span class="text-[9px] font-bold text-gray-700 bg-white/95 backdrop-blur-xs px-1.5 py-0.2 rounded-md shadow-2xs mt-0.5 border border-gray-200/60 leading-tight">
                    <?php echo $currentLanguage === 'km' ? 'ផែនទី' : 'Maps'; ?>
                </span>
            </a>

            <!-- 3. About Button -->
            <button onclick="openAboutModal()" 
                    type="button"
                    class="fab-float-3 group flex flex-col items-center focus:outline-none cursor-pointer"
                    aria-label="<?php echo $currentLanguage === 'km' ? 'អំពីយើង' : 'About'; ?>">
                <div class="w-11 h-11 rounded-full bg-white/95 backdrop-blur-md shadow-lg border border-gray-200/90 flex items-center justify-center text-orange-600 transition-all duration-200 group-hover:scale-105 group-hover:bg-orange-50 group-hover:border-orange-300 group-active:scale-95 shadow-orange-500/10">
                    <i class="fas fa-info text-base"></i>
                </div>
                <span class="text-[9px] font-bold text-gray-700 bg-white/95 backdrop-blur-xs px-1.5 py-0.2 rounded-md shadow-2xs mt-0.5 border border-gray-200/60 leading-tight">
                    <?php echo $currentLanguage === 'km' ? 'អំពីយើង' : 'About'; ?>
                </span>
            </button>
        </aside>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- About Store Modal (Bottom Sheet - Brand Info, Maps, Contact) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="about-modal" class="fixed inset-0 z-50 sheet-hidden flex flex-col justify-end" style="display: none;">
        <!-- Backdrop -->
        <div class="sheet-backdrop absolute inset-0 bg-black/60 backdrop-blur-xs" onclick="closeAboutModal()"></div>

        <!-- Sheet Content -->
        <div class="sheet-content relative bg-white w-full max-w-2xl mx-auto rounded-t-[2rem] max-h-[90vh] flex flex-col overflow-hidden shadow-2xl z-10" id="about-sheet-content-el">
            
            <!-- Pull-down Drag Handle Pill -->
            <div class="w-full flex justify-center pt-2.5 pb-1 bg-white cursor-grab active:cursor-grabbing sheet-drag-handle" id="about-drag-handle">
                <div class="w-10 h-1 rounded-full bg-gray-300 hover:bg-gray-400 transition-colors"></div>
            </div>

            <!-- Sticky Modal Top Header -->
            <div class="flex items-center justify-between px-5 py-2.5 border-b border-gray-100 bg-white sticky top-0 z-20">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div>
                    <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <?php echo $currentLanguage === 'km' ? 'អំពី ហ្គោ ហ្គោ' : 'About GoGo Brand'; ?>
                    </span>
                </div>
                <button onclick="closeAboutModal()" 
                        aria-label="Close"
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center text-xs active:scale-90 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Scrollable Body with Store Details -->
            <div class="overflow-y-auto px-5 pt-3 pb-8 space-y-4">
                
                <!-- Brand Header Card -->
                <div class="p-4 rounded-2xl bg-gradient-to-br from-orange-50/80 via-white to-orange-50/30 border border-orange-100 flex items-center gap-3.5 shadow-2xs">
                    <div class="w-14 h-14 rounded-2xl bg-white p-1.5 border border-orange-200/60 shadow-xs flex items-center justify-center flex-shrink-0">
                        <img src="<?php echo htmlspecialchars($storeLogo); ?>" 
                             alt="Logo" 
                             class="w-full h-full object-contain"
                             onerror="this.src='/kouprey/public/assets/images/logo.png'">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-extrabold text-gray-900 leading-tight">
                            <?php echo htmlspecialchars($storeName); ?>
                        </h3>
                        <p class="text-xs text-orange-600 font-semibold mt-0.5 leading-snug">
                            <?php echo htmlspecialchars($storeDescription); ?>
                        </p>
                    </div>
                </div>

                <!-- Info List Cards -->
                <div class="space-y-2.5">
                    <!-- Address / Location Card -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-map-marker-alt text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-800">
                                <?php echo $currentLanguage === 'km' ? 'អាសយដ្ឋាន' : 'Address'; ?>
                            </h4>
                            <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                                <?php echo htmlspecialchars($storeAddress); ?>
                            </p>
                            <a href="<?php echo htmlspecialchars($mapsUrl); ?>" 
                               onclick="openExternalUrl('<?php echo htmlspecialchars($mapsUrl); ?>', event)" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold active:scale-95 transition-all shadow-xs">
                                <i class="fas fa-location-arrow text-[10px]"></i>
                                <span><?php echo $currentLanguage === 'km' ? 'មើលលើ Google Maps' : 'Open in Google Maps'; ?></span>
                            </a>
                        </div>
                    </div>

                    <!-- Business Hours Card -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-clock text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-800">
                                <?php echo $currentLanguage === 'km' ? 'ម៉ោងធ្វើការ' : 'Business Hours'; ?>
                            </h4>
                            <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                                <?php echo htmlspecialchars($storeHours); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Contact & Telegram Card -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-phone-volume text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-800">
                                <?php echo $currentLanguage === 'km' ? 'ទំនាក់ទំនង' : 'Contact & Support'; ?>
                            </h4>
                            <p class="text-xs text-gray-600 mt-0.5">
                                <?php echo htmlspecialchars($storePhone); ?>
                            </p>
                            <div class="flex items-center gap-2 mt-2 flex-wrap">
                                <?php if (!empty($storePhone)): ?>
                                <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $storePhone); ?>" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-bold active:scale-95 transition-all">
                                    <i class="fas fa-phone text-[10px]"></i>
                                    <span><?php echo $currentLanguage === 'km' ? 'ទូរស័ព្ទ' : 'Call'; ?></span>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($storeTelegram); ?>" 
                                   onclick="openExternalUrl('<?php echo htmlspecialchars($storeTelegram); ?>', event)" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-500 hover:bg-sky-600 text-white text-xs font-bold active:scale-95 transition-all shadow-xs">
                                    <i class="fab fa-telegram-plane text-[10px]"></i>
                                    <span><?php echo $currentLanguage === 'km' ? 'ផ្ញើសារ Telegram' : 'Telegram Chat'; ?></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Official Website Link Card -->
                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-globe text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-800">
                                <?php echo $currentLanguage === 'km' ? 'គេហទំព័រផ្លូវការ' : 'Official Website'; ?>
                            </h4>
                            <p class="text-xs text-indigo-600 font-semibold mt-0.5 break-all">
                                <?php echo htmlspecialchars($websiteUrl); ?>
                            </p>
                            <a href="<?php echo htmlspecialchars($websiteUrl); ?>" 
                               onclick="openExternalUrl('<?php echo htmlspecialchars($websiteUrl); ?>', event)" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold active:scale-95 transition-all shadow-xs">
                                <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
                                <span><?php echo $currentLanguage === 'km' ? 'ចូលមើលគេហទំព័រ' : 'Visit Website'; ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Back / Close Button -->
                <div class="pt-2">
                    <button onclick="closeAboutModal()" 
                            type="button"
                            class="w-full py-3.5 px-4 rounded-xl bg-gray-900 hover:bg-black active:scale-98 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md transition-all cursor-pointer">
                        <i class="fas fa-arrow-left text-xs"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'ត្រឡប់ទៅកាន់បញ្ជីផលិតផល' : 'Back to Product Catalog'; ?></span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Product Detail Modal (Bottom Sheet - Full Details Showcase) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="product-modal" class="fixed inset-0 z-50 sheet-hidden flex flex-col justify-end" style="display: none;">
        <!-- Backdrop -->
        <div class="sheet-backdrop absolute inset-0 bg-black/60 backdrop-blur-xs" onclick="closeProductModal()"></div>

        <!-- Sheet Content -->
        <div class="sheet-content relative bg-white w-full max-w-2xl mx-auto rounded-t-[2rem] max-h-[92vh] flex flex-col overflow-hidden shadow-2xl z-10" id="sheet-content-el">
            
            <!-- Pull-down Drag Handle Pill -->
            <div class="w-full flex justify-center pt-2.5 pb-1 bg-white cursor-grab active:cursor-grabbing sheet-drag-handle" id="sheet-drag-handle">
                <div class="w-10 h-1 rounded-full bg-gray-300 hover:bg-gray-400 transition-colors"></div>
            </div>

            <!-- Sticky Modal Top Header -->
            <div class="flex items-center justify-between px-5 py-2.5 border-b border-gray-100 bg-white sticky top-0 z-20">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full bg-orange-500"></div>
                    <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <?php echo $currentLanguage === 'km' ? 'ព័ត៌មានលម្អិតផលិតផល' : 'Product Details'; ?>
                    </span>
                </div>
                <button onclick="closeProductModal()" 
                        aria-label="Close"
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center text-xs active:scale-90 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Scrollable Body with Full Product Details -->
            <div class="overflow-y-auto px-5 pt-3 pb-8 space-y-4">
                
                <!-- Main Product Image Frame with Badges -->
                <div class="relative w-full aspect-square max-h-[340px] bg-gradient-to-b from-gray-50 to-orange-50/20 rounded-2xl flex items-center justify-center p-4 overflow-hidden border border-gray-100">
                    <img id="modal-img" 
                         src="" 
                         alt="" 
                         class="w-full h-full object-contain filter drop-shadow-md img-fade-in"
                         onerror="this.src='/kouprey/public/assets/images/logo.png'">
                    
                    <div id="modal-badges" class="absolute top-3 left-3 flex flex-col gap-1"></div>
                </div>

                <!-- Product Name, Category & Price -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <span id="modal-category" class="inline-block text-[11px] font-bold text-orange-600 uppercase tracking-wider bg-orange-50 px-2.5 py-0.5 rounded-md border border-orange-100"></span>
                        <div class="flex items-center gap-1 text-amber-400 text-xs font-bold">
                            <i class="fas fa-star text-[10px]"></i>
                            <span id="modal-rating" class="text-gray-800">5.0</span>
                            <span id="modal-reviews" class="text-gray-400 text-[10px]">(0)</span>
                        </div>
                    </div>

                    <h2 id="modal-title" class="text-lg sm:text-xl font-bold text-gray-900 leading-snug"></h2>

                    <div class="pt-1 flex items-baseline gap-2">
                        <span id="modal-price" class="text-2xl sm:text-3xl font-black text-orange-600"></span>
                        <span class="text-xs text-gray-400 font-semibold">USD</span>
                    </div>
                </div>

                <!-- Quick Specs Grid (Weight, Roast, Quality) -->
                <div class="grid grid-cols-3 gap-2 pt-1" id="modal-specs-grid">
                    <!-- Weight -->
                    <div class="p-2.5 rounded-xl bg-gray-50 border border-gray-100 text-center">
                        <span class="text-[10px] font-semibold text-gray-400 block uppercase">
                            <?php echo $currentLanguage === 'km' ? 'ទម្ងន់' : 'Weight'; ?>
                        </span>
                        <span id="modal-weight" class="text-xs font-bold text-gray-800">250g</span>
                    </div>

                    <!-- Roast / Type -->
                    <div class="p-2.5 rounded-xl bg-gray-50 border border-gray-100 text-center" id="modal-roast-box">
                        <span class="text-[10px] font-semibold text-gray-400 block uppercase">
                            <?php echo $currentLanguage === 'km' ? 'កម្រិត' : 'Type'; ?>
                        </span>
                        <span id="modal-roast" class="text-xs font-bold text-gray-800">Standard</span>
                    </div>

                    <!-- Quality Badge -->
                    <div class="p-2.5 rounded-xl bg-gray-50 border border-gray-100 text-center">
                        <span class="text-[10px] font-semibold text-gray-400 block uppercase">
                            <?php echo $currentLanguage === 'km' ? 'គុណភាព' : 'Quality'; ?>
                        </span>
                        <span class="text-xs font-bold text-emerald-600 flex items-center justify-center gap-1">
                            <i class="fas fa-check-circle text-[10px]"></i> Premium
                        </span>
                    </div>
                </div>

                <!-- Short Description -->
                <div class="bg-gray-50/80 p-3.5 rounded-xl border border-gray-100 space-y-1.5" id="modal-desc-box">
                    <h4 class="font-bold text-gray-900 text-xs flex items-center gap-1.5">
                        <i class="fas fa-align-left text-orange-500 text-[11px]"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'ការពិពណ៌នា' : 'Description'; ?></span>
                    </h4>
                    <p id="modal-desc" class="text-xs sm:text-sm text-gray-600 leading-relaxed whitespace-pre-line"></p>
                </div>

                <!-- Detailed Information (If available) -->
                <div class="hidden bg-gray-50/80 p-3.5 rounded-xl border border-gray-100 space-y-1.5" id="modal-detail-desc-box">
                    <h4 class="font-bold text-gray-900 text-xs flex items-center gap-1.5">
                        <i class="fas fa-info-circle text-orange-500 text-[11px]"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'ព័ត៌មានលម្អិតបន្ថែម' : 'Detailed Specifications'; ?></span>
                    </h4>
                    <p id="modal-detail-desc" class="text-xs sm:text-sm text-gray-600 leading-relaxed whitespace-pre-line"></p>
                </div>

                <!-- Custom Fields / Table Specs (If available) -->
                <div id="modal-custom-fields-box" class="hidden space-y-2"></div>

                <!-- Related Products Section (ផលិតផលពាក់ព័ន្ធ) -->
                <div id="modal-related-section" class="pt-3 border-t border-gray-100 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                            <h4 class="font-bold text-gray-900 text-xs sm:text-sm tracking-tight">
                                <?php echo $currentLanguage === 'km' ? 'ផលិតផលពាក់ព័ន្ធ' : 'Related Products'; ?>
                            </h4>
                        </div>
                        <span class="text-[10px] text-gray-400 font-medium">
                            <?php echo $currentLanguage === 'km' ? 'ប្រភេទដូចគ្នា' : 'Similar items'; ?>
                        </span>
                    </div>

                    <!-- Horizontal Scroll Container for Related Products -->
                    <div id="modal-related-container" class="flex gap-2.5 overflow-x-auto no-scrollbar pb-1.5 pt-0.5 scroll-smooth">
                        <!-- Injected dynamically via JS based on current product & category -->
                    </div>
                </div>

                <!-- Back / Close Button -->
                <div class="pt-2">
                    <button onclick="closeProductModal()" 
                            class="w-full py-3.5 px-4 rounded-xl bg-gray-900 hover:bg-black active:scale-98 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md transition-all cursor-pointer">
                        <i class="fas fa-arrow-left text-xs"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'ត្រឡប់ទៅកាន់បញ្ជីផលិតផល' : 'Back to Product Catalog'; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Swiper 11 JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Client-side Logic (Fast, Offline-Capable, Telegram Integrated) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <script>
        // Injected data from server
        const ALL_PRODUCTS = <?php echo json_encode($cleanProducts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const CURRENT_LANG = '<?php echo $currentLanguage; ?>';

        // State
        let selectedCategory = 'all';
        let searchQuery = '';
        let currentModalProduct = null;

        // Telegram WebApp Setup & Fullscreen Integration
        const tg = window.Telegram?.WebApp;

        function updateTelegramSafeArea() {
            try {
                const isFull = Boolean(tg?.isFullscreen);
                const contentTop = tg?.contentSafeAreaInset?.top || 0;
                const safeTop = tg?.safeAreaInset?.top || 0;

                if (isFull) {
                    // In Fullscreen mode: clear floating Close button & notch/Dynamic Island
                    const topPadding = contentTop > 0 ? contentTop : Math.max(safeTop, 54);
                    document.documentElement.style.setProperty('--tg-safe-top-dynamic', `${topPadding + 4}px`);
                } else if (contentTop > 0) {
                    document.documentElement.style.setProperty('--tg-safe-top-dynamic', `${contentTop + 4}px`);
                } else if (safeTop > 0) {
                    document.documentElement.style.setProperty('--tg-safe-top-dynamic', `${safeTop + 4}px`);
                } else {
                    // Standard sheet mode (Telegram native header is present above webview)
                    document.documentElement.style.setProperty('--tg-safe-top-dynamic', '12px');
                }
            } catch (e) {}
        }

        function forceExpandAndFullscreen() {
            try {
                // 1. Direct post to Telegram native WebView bridge (bypasses SDK version checks)
                if (window.Telegram?.WebView?.postEvent) {
                    window.Telegram.WebView.postEvent('web_app_expand');
                    window.Telegram.WebView.postEvent('web_app_request_fullscreen');
                    window.Telegram.WebView.postEvent('web_app_setup_swipe_behavior', false, { allow_vertical_swipe: false });
                }

                // 2. Ready & expand unconditionally (fixes sheet stuck in the middle "ពាក់កណ្តាល")
                if (tg && typeof tg.ready === 'function') {
                    tg.ready();
                }
                if (tg && typeof tg.expand === 'function') {
                    tg.expand();
                }

                // 3. Prevent vertical swipe drag from collapsing the sheet back down
                if (tg && typeof tg.disableVerticalSwipes === 'function') {
                    try { tg.disableVerticalSwipes(); } catch (e) {}
                }

                // 4. Request true Fullscreen (Bot API 8.0+)
                if (tg && typeof tg.requestFullscreen === 'function') {
                    try { tg.requestFullscreen(); } catch (e) {}
                }

                updateTelegramSafeArea();
            } catch (e) {}
        }

        if (tg) {
            try {
                // Immediate trigger
                forceExpandAndFullscreen();

                // Multi-interval retry: iOS Telegram takes ~300ms to finish its modal presentation animation.
                // Calling expand/fullscreen during that animation is dropped by iOS Telegram. Retrying at these
                // intervals guarantees it expands to full height as soon as the transition completes.
                [30, 100, 200, 350, 500, 750, 1200, 2000].forEach(delay => {
                    setTimeout(forceExpandAndFullscreen, delay);
                });

                tg.onEvent?.('fullscreenChanged', updateTelegramSafeArea);
                tg.onEvent?.('fullscreenFailed', updateTelegramSafeArea);
                tg.onEvent?.('safeAreaChanged', updateTelegramSafeArea);
                tg.onEvent?.('contentSafeAreaChanged', updateTelegramSafeArea);
                tg.onEvent?.('viewportChanged', () => {
                    forceExpandAndFullscreen();
                });

                // Only set colors if supported by Telegram client version (6.1+)
                if (typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1')) {
                    tg.setHeaderColor?.('#ffffff');
                    tg.setBackgroundColor?.('#f8fafc');
                }
            } catch (e) {}
        }

        // Retry expand and fullscreen on lifecycle events & user gestures
        document.addEventListener('DOMContentLoaded', forceExpandAndFullscreen);
        window.addEventListener('load', forceExpandAndFullscreen);
        window.addEventListener('touchstart', forceExpandAndFullscreen, { passive: true });
        window.addEventListener('pointerdown', forceExpandAndFullscreen, { passive: true });
        window.addEventListener('click', forceExpandAndFullscreen, { passive: true });

        // Haptic feedback helper
        function hapticFeedback(type = 'light') {
            try {
                if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.HapticFeedback) {
                    if (type === 'selection') tg.HapticFeedback.selectionChanged();
                    else if (type === 'medium') tg.HapticFeedback.impactOccurred('medium');
                    else tg.HapticFeedback.impactOccurred('light');
                }
            } catch (e) {}
        }

        // Switch Language Helper (KM / EN)
        function switchLanguage(lang) {
            hapticFeedback('selection');
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('lang', lang);
                window.location.href = url.toString();
            } catch (e) {
                window.location.href = '?lang=' + encodeURIComponent(lang);
            }
        }

        // Open External Links via Telegram SDK if available
        function openExternalUrl(url, event) {
            hapticFeedback('medium');
            if (!url) return;

            // 1. Telegram links (t.me/... or tg://)
            if (url.includes('t.me/') || url.startsWith('tg:')) {
                let tgOpened = false;
                try {
                    if (tg && typeof tg.openTelegramLink === 'function') {
                        tg.openTelegramLink(url);
                        tgOpened = true;
                    } else if (window.Telegram?.WebView?.postEvent) {
                        window.Telegram.WebView.postEvent('web_app_open_tg_link', false, { path_full: url });
                        tgOpened = true;
                    }
                } catch (e) {}

                if (tgOpened) {
                    if (event && typeof event.preventDefault === 'function') event.preventDefault();
                    return;
                }
                if (!event) window.location.href = url;
                return;
            }

            // 2. Regular Web / Maps Links (openLink in Telegram)
            let webOpened = false;
            try {
                if (tg && typeof tg.openLink === 'function') {
                    tg.openLink(url, { try_instant_view: false });
                    webOpened = true;
                } else if (window.Telegram?.WebView?.postEvent) {
                    window.Telegram.WebView.postEvent('web_app_open_link', false, { url: url, try_instant_view: false });
                    webOpened = true;
                }
            } catch (e) {}

            if (webOpened) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                return;
            }

            // 3. Fallback for standalone browser / non-Telegram
            if (!event) {
                try {
                    const win = window.open(url, '_blank', 'noopener,noreferrer');
                    if (!win) window.location.href = url;
                } catch (e) {
                    window.location.href = url;
                }
            }
        }

        // Telegram Chat Order / Inquiry Helper
        function openTelegramOrder(product) {
            const url = '<?php echo htmlspecialchars($storeTelegram); ?>';
            openExternalUrl(url);
        }

        // Normalize Khmer Unicode typo: ស + ុ (U+17BB) + ី (U+17B8) -> ស + ៊ (U+17CA, Triisap) + ី (U+17B8) = ស៊ី
        function normalizeKhmer(str) {
            if (!str) return '';
            return String(str)
                .replace(/\u179F\u17BB\u17B8/g, '\u179F\u17CA\u17B8')
                .replace(/\u179F\u17B8\u17BB/g, '\u179F\u17CA\u17B8')
                .replace(/សុី/g, 'ស៊ី');
        }

        // HTML escape helper
        function escapeHtml(str) {
            if (!str) return '';
            return normalizeKhmer(String(str))
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Live DOM cleanup for any initial server-rendered elements
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.category-pill span, #top-banner-section h3, #top-banner-section span').forEach(el => {
                if (el.innerText && (el.innerText.includes('សុី') || el.innerText.includes('\u179F\u17BB\u17B8'))) {
                    el.innerText = normalizeKhmer(el.innerText);
                }
            });
        });

        // Filter and Render Products
        function filterAndRenderProducts() {
            const grid = document.getElementById('product-grid');
            const emptyState = document.getElementById('empty-state');
            const counter = document.getElementById('product-counter');
            const topBanner = document.getElementById('top-banner-section');

            const q = searchQuery.toLowerCase().trim();

            // Hide compact top banner slider when search query is typed, show when empty
            if (topBanner) {
                if (q.length > 0) {
                    topBanner.classList.add('hidden');
                } else {
                    topBanner.classList.remove('hidden');
                }
            }

            const filtered = ALL_PRODUCTS.filter(p => {
                // Exact category match
                const matchCat = (selectedCategory === 'all') || (String(p.category_key) === String(selectedCategory));

                // Search match (name or description)
                const matchSearch = !q || p.name.toLowerCase().includes(q) || (p.description && p.description.toLowerCase().includes(q));

                return matchCat && matchSearch;
            });

            // Update counter text
            if (counter) {
                const prefix = CURRENT_LANG === 'km' ? 'រកឃើញ' : 'Showing';
                counter.innerText = `${prefix}: ${filtered.length}`;
            }

            if (filtered.length === 0) {
                grid.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');

            let html = '';
            filtered.forEach(p => {
                const priceFormatted = '$' + p.price.toFixed(2);
                const safeName = escapeHtml(p.name);
                const safeCat = escapeHtml(p.category_name);

                html += `
                    <div class="bg-white rounded-2xl p-2.5 shadow-xs hover:shadow-md border border-gray-100 flex flex-col justify-between cursor-pointer active:scale-[0.98] transition-all group"
                         onclick="openProductModal(${p.id})">
                        
                        <!-- Image Container -->
                        <div class="relative w-full aspect-square bg-gradient-to-b from-gray-50 to-orange-50/20 rounded-xl overflow-hidden p-2 flex items-center justify-center mb-2">
                            <img src="${p.image}" 
                                 alt="${safeName}" 
                                 loading="lazy"
                                 onload="this.classList.add('loaded')"
                                 onerror="this.src='/kouprey/public/assets/images/product-medium.png'; this.classList.add('loaded')"
                                 class="w-full h-full object-contain filter drop-shadow-xs group-hover:scale-105 transition-transform duration-300 img-fade-in">
                            
                            ${p.featured ? `
                                <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-md bg-yellow-500 text-white text-[9px] font-bold shadow-xs flex items-center gap-1">
                                    <i class="fas fa-star text-[7px]"></i>
                                    <span>${CURRENT_LANG === 'km' ? 'ពិសេស' : 'Featured'}</span>
                                </span>
                            ` : ''}

                            ${p.best_seller ? `
                                <span class="absolute top-1.5 right-1.5 px-1.5 py-0.5 rounded-md bg-red-500 text-white text-[9px] font-bold shadow-xs">
                                    HOT
                                </span>
                            ` : ''}
                        </div>

                        <!-- Product Info -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <span class="text-[10px] text-orange-600 font-semibold block truncate mb-0.5">${safeCat}</span>
                                <h3 class="text-xs sm:text-sm font-bold text-gray-900 line-clamp-2 leading-tight group-hover:text-orange-600 transition-colors">
                                    ${safeName}
                                </h3>
                            </div>

                            <div class="pt-2 flex items-center justify-between border-t border-gray-50 mt-1.5">
                                <span class="text-sm font-extrabold text-gray-900">${priceFormatted}</span>
                                <span class="text-[10px] font-bold text-orange-600 flex items-center gap-0.5 bg-orange-50 px-2 py-0.5 rounded-md">
                                    <span>${CURRENT_LANG === 'km' ? 'លម្អិត' : 'View'}</span>
                                    <i class="fas fa-chevron-right text-[7px]"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        // Category Selection
        function selectCategory(catKey, btnEl = null) {
            hapticFeedback('selection');
            selectedCategory = String(catKey);

            // Update pill styling
            document.querySelectorAll('.category-pill').forEach(el => {
                if (el.getAttribute('data-cat-key') === String(catKey)) {
                    el.className = 'category-pill flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold transition-all bg-orange-500 text-white shadow-xs';
                } else {
                    el.className = 'category-pill flex-shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200';
                }
            });

            // Scroll pill into view smoothly if element provided
            if (btnEl) {
                btnEl.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }

            filterAndRenderProducts();
        }

        // Search Handlers
        const searchInput = document.getElementById('search-input');
        const searchClearBtn = document.getElementById('search-clear-btn');

        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            if (searchQuery.length > 0) {
                searchClearBtn.classList.remove('hidden');
            } else {
                searchClearBtn.classList.add('hidden');
            }
            filterAndRenderProducts();
        });

        function clearSearch() {
            hapticFeedback('medium');
            searchQuery = '';
            searchInput.value = '';
            searchClearBtn.classList.add('hidden');
            filterAndRenderProducts();
        }

        // Product Modal Handlers
        function openProductModal(productId) {
            const product = ALL_PRODUCTS.find(p => p.id == productId);
            if (!product) return;

            hapticFeedback('light');
            currentModalProduct = product;

            const modalImg = document.getElementById('modal-img');
            modalImg.classList.remove('loaded');
            modalImg.onload = () => modalImg.classList.add('loaded');
            modalImg.src = product.image;

            document.getElementById('modal-title').innerText = normalizeKhmer(product.name);
            document.getElementById('modal-price').innerText = '$' + product.price.toFixed(2);
            document.getElementById('modal-category').innerText = normalizeKhmer(product.category_name);
            document.getElementById('modal-rating').innerText = product.avg_rating.toFixed(1);
            document.getElementById('modal-reviews').innerText = `(${product.review_count || 0})`;

            // Weight & Roast
            document.getElementById('modal-weight').innerText = product.weight || '250g';
            const roastBox = document.getElementById('modal-roast-box');
            if (product.roast_level) {
                document.getElementById('modal-roast').innerText = product.roast_level;
                roastBox.classList.remove('hidden');
            } else {
                document.getElementById('modal-roast').innerText = CURRENT_LANG === 'km' ? 'ទូទៅ' : 'Standard';
            }

            // Description
            const descEl = document.getElementById('modal-desc');
            const descBox = document.getElementById('modal-desc-box');
            if (product.description) {
                descEl.innerText = product.description;
                descBox.classList.remove('hidden');
            } else {
                descBox.classList.add('hidden');
            }

            // Detailed Description
            const detailDescEl = document.getElementById('modal-detail-desc');
            const detailDescBox = document.getElementById('modal-detail-desc-box');
            if (product.detailed_description) {
                detailDescEl.innerText = product.detailed_description;
                detailDescBox.classList.remove('hidden');
            } else {
                detailDescBox.classList.add('hidden');
            }

            // Custom Fields Section
            const customBox = document.getElementById('modal-custom-fields-box');
            customBox.innerHTML = '';
            if (product.custom_fields && Object.keys(product.custom_fields).length > 0) {
                const textRows = [];
                const tableCards = [];

                for (const [key, field] of Object.entries(product.custom_fields)) {
                    // Ignore internal keys or empty fields
                    if (key === 'show_in_collection' || typeof field === 'boolean' || field === null) continue;

                    if (typeof field === 'object') {
                        // Check if it's a structured table (e.g. Nutrition Facts)
                        if (field.type === 'table' && Array.isArray(field.value) && field.value.length > 0) {
                            tableCards.push(field);
                        } else {
                            const label = field.name?.[CURRENT_LANG] || field.name?.en || (typeof field.name === 'string' ? field.name : key);
                            let val = '';
                            if (field.value && typeof field.value === 'object') {
                                val = field.value[CURRENT_LANG] || field.value.en || '';
                            } else if (field.value !== undefined && field.value !== null) {
                                val = String(field.value);
                            }
                            if (val && String(val).trim()) {
                                textRows.push({ label: String(label), value: String(val).trim() });
                            }
                        }
                    } else if (typeof field === 'string' && field.trim()) {
                        textRows.push({ label: key, value: field.trim() });
                    }
                }

                let cfHtml = '';

                // Helper to format values with clean line breaks
                const formatSpecValue = (val) => {
                    const rawLines = val.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
                    if (rawLines.length > 1) {
                        return '<div class="space-y-1.5">' + rawLines.map((line, idx) => `
                            <div class="${idx > 0 ? 'pt-1.5 border-t border-dashed border-gray-100' : ''} leading-relaxed text-gray-800">
                                ${escapeHtml(line)}
                            </div>
                        `).join('') + '</div>';
                    }
                    return `<div class="leading-relaxed text-gray-800 font-medium">${escapeHtml(val)}</div>`;
                };

                // 1. General Specifications Table Card
                if (textRows.length > 0) {
                    let rowsHtml = '';
                    textRows.forEach((row, idx) => {
                        const cleanLabel = row.label.trim().replace(/[:：៖\s]+$/, '');
                        const valHtml = formatSpecValue(row.value);
                        const isEven = idx % 2 === 1;

                        rowsHtml += `
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-orange-50/20 transition-colors">
                                <td class="w-[105px] min-w-[95px] max-w-[120px] py-2.5 px-3 bg-gray-50/80 text-gray-700 font-bold text-xs align-top border-r border-gray-100 leading-snug select-none">
                                    ${escapeHtml(cleanLabel)}
                                </td>
                                <td class="py-2.5 px-3.5 ${isEven ? 'bg-gray-50/30' : 'bg-white'} text-gray-800 text-xs leading-relaxed align-top">
                                    ${valHtml}
                                </td>
                            </tr>
                        `;
                    });

                    cfHtml += `
                        <div class="rounded-2xl overflow-hidden border border-gray-200/90 bg-white shadow-2xs">
                            <div class="flex items-center gap-2 px-3.5 py-2.5 bg-gradient-to-r from-orange-50/90 via-amber-50/40 to-white border-b border-orange-100/70">
                                <span class="w-6 h-6 rounded-lg bg-orange-500/10 text-orange-600 flex items-center justify-center text-xs">
                                    <i class="fas fa-clipboard-list text-[11px]"></i>
                                </span>
                                <h4 class="font-bold text-gray-900 text-xs tracking-tight">
                                    ${CURRENT_LANG === 'km' ? 'លក្ខណៈបច្ចេកទេស' : 'Product Specifications'}
                                </h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left border-collapse">
                                    <tbody>
                                        ${rowsHtml}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                // 2. Specialized Data Table Cards (e.g. Nutrition Facts)
                if (tableCards.length > 0) {
                    tableCards.forEach(tCard => {
                        const tableName = tCard.name?.[CURRENT_LANG] || tCard.name?.en || (CURRENT_LANG === 'km' ? 'ព័ត៌មានអាហារូបត្ថម្ភ' : 'Nutrition Information');
                        let tRowsHtml = '';

                        tCard.value.forEach((row, idx) => {
                            const rowLabel = row.label?.[CURRENT_LANG] || row.label?.en || '';
                            let valArr = [];
                            if (Array.isArray(row.value)) {
                                valArr = row.value.map(v => (typeof v === 'object' && v !== null ? (v[CURRENT_LANG] || v.en || '') : String(v)));
                            } else if (typeof row.value === 'object' && row.value !== null) {
                                valArr = [row.value[CURRENT_LANG] || row.value.en || ''];
                            } else if (row.value !== undefined && row.value !== null) {
                                valArr = [String(row.value)];
                            }

                            const val1 = valArr[0] || '-';
                            const val2 = valArr[1] || '';
                            const isEven = idx % 2 === 1;

                            tRowsHtml += `
                                <tr class="border-b border-gray-100 last:border-0 hover:bg-emerald-50/20 transition-colors">
                                    <td class="w-[105px] min-w-[95px] max-w-[120px] py-2 px-3 bg-gray-50/70 text-gray-700 font-semibold border-r border-gray-100 align-middle">
                                        ${escapeHtml(rowLabel)}
                                    </td>
                                    <td class="py-2 px-3.5 ${isEven ? 'bg-gray-50/30' : 'bg-white'} text-gray-900 font-medium align-middle">
                                        ${escapeHtml(val1)}
                                    </td>
                                    ${val2 ? `
                                        <td class="py-2 px-3 ${isEven ? 'bg-gray-50/30' : 'bg-white'} text-right text-gray-500 font-medium text-[11px] align-middle">
                                            ${escapeHtml(val2)}
                                        </td>
                                    ` : ''}
                                </tr>
                            `;
                        });

                        cfHtml += `
                            <div class="rounded-2xl overflow-hidden border border-gray-200/90 bg-white shadow-2xs mt-2.5">
                                <div class="flex items-center justify-between px-3.5 py-2.5 bg-gradient-to-r from-emerald-50/90 via-teal-50/30 to-white border-b border-emerald-100/70">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-xs">
                                            <i class="fas fa-heart-pulse text-[11px]"></i>
                                        </span>
                                        <h4 class="font-bold text-gray-900 text-xs tracking-tight">
                                            ${escapeHtml(tableName)}
                                        </h4>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-medium">100g / NRV%</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left border-collapse">
                                        <tbody>
                                            ${tRowsHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    });
                }

                if (cfHtml) {
                    customBox.innerHTML = cfHtml;
                    customBox.classList.remove('hidden');
                } else {
                    customBox.classList.add('hidden');
                }
            } else {
                customBox.classList.add('hidden');
            }

            // Badges
            const badgesContainer = document.getElementById('modal-badges');
            let badgesHtml = '';
            if (product.featured) {
                badgesHtml += `<span class="px-2 py-0.5 rounded-md bg-yellow-500 text-white text-[10px] font-bold shadow-xs">★ ${CURRENT_LANG === 'km' ? 'ពិសេស' : 'Featured'}</span>`;
            }
            if (product.best_seller) {
                badgesHtml += `<span class="px-2 py-0.5 rounded-md bg-red-500 text-white text-[10px] font-bold shadow-xs">HOT</span>`;
            }
            badgesContainer.innerHTML = badgesHtml;

            // Render Related Products (ផលិតផលពាក់ព័ន្ធ)
            renderRelatedProducts(product);

            // Reset scroll position of sheet body to top
            const sheetBody = document.querySelector('#product-modal .overflow-y-auto');
            if (sheetBody) {
                sheetBody.scrollTop = 0;
            }

            const modal = document.getElementById('product-modal');
            modal.style.display = 'flex';
            // Trigger reflow for smooth bottom-sheet animation
            modal.offsetHeight;
            modal.classList.remove('sheet-hidden');
            document.body.style.overflow = 'hidden';

            const dock = document.getElementById('fab-dock');
            if (dock) dock.classList.add('opacity-0', 'pointer-events-none', 'scale-90');

            // Show Telegram BackButton when modal is open if supported
            if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.BackButton) {
                tg.BackButton.show();
                tg.BackButton.onClick(closeProductModal);
            }
        }

        // Render Related Products (ផលិតផលពាក់ព័ន្ធ) inside Detail Modal
        function renderRelatedProducts(currentProduct) {
            const container = document.getElementById('modal-related-container');
            const section = document.getElementById('modal-related-section');
            if (!container || !section) return;

            // 1. Same category products first (excluding current product)
            const sameCategory = ALL_PRODUCTS.filter(p => 
                p.id !== currentProduct.id && 
                String(p.category_key) === String(currentProduct.category_key)
            );

            // 2. If fewer than 6, supplement with other top products
            let related = [...sameCategory];
            if (related.length < 6) {
                const others = ALL_PRODUCTS.filter(p => 
                    p.id !== currentProduct.id && 
                    !related.some(r => r.id === p.id)
                );
                for (const o of others) {
                    related.push(o);
                    if (related.length >= 6) break;
                }
            } else if (related.length > 10) {
                related = related.slice(0, 10);
            }

            if (related.length === 0) {
                section.classList.add('hidden');
                return;
            }

            section.classList.remove('hidden');

            let html = '';
            related.forEach(rp => {
                const priceFormatted = '$' + rp.price.toFixed(2);
                const safeName = escapeHtml(rp.name);
                const safeCat = escapeHtml(rp.category_name);

                html += `
                    <div class="w-[125px] min-w-[125px] sm:w-[140px] sm:min-w-[140px] bg-gray-50/90 hover:bg-orange-50/20 border border-gray-100 rounded-2xl p-2 flex flex-col justify-between cursor-pointer active:scale-95 transition-all group flex-shrink-0"
                         onclick="openProductModal(${rp.id})">
                        <div>
                            <div class="relative w-full aspect-square bg-white rounded-xl overflow-hidden p-1.5 mb-1.5 flex items-center justify-center border border-gray-100/70 shadow-2xs">
                                <img src="${rp.image}" 
                                     alt="${safeName}" 
                                     loading="lazy"
                                     onerror="this.src='/kouprey/public/assets/images/product-medium.png'"
                                     class="w-full h-full object-contain filter drop-shadow-xs group-hover:scale-105 transition-transform duration-300">
                                ${rp.featured ? `
                                    <span class="absolute top-1 left-1 px-1 py-0.2 rounded bg-yellow-500 text-white text-[8px] font-bold">
                                        ★
                                    </span>
                                ` : ''}
                                ${rp.best_seller ? `
                                    <span class="absolute top-1 right-1 px-1 py-0.2 rounded bg-red-500 text-white text-[8px] font-bold">
                                        HOT
                                    </span>
                                ` : ''}
                            </div>
                            <span class="text-[9px] text-orange-600 font-semibold block truncate leading-tight">${safeCat}</span>
                            <h5 class="text-[11px] font-bold text-gray-900 line-clamp-2 leading-tight group-hover:text-orange-600 transition-colors mt-0.5">
                                ${safeName}
                            </h5>
                        </div>
                        <div class="pt-1.5 flex items-center justify-between border-t border-gray-200/50 mt-1">
                            <span class="text-xs font-extrabold text-orange-600">${priceFormatted}</span>
                            <span class="w-5 h-5 rounded-full bg-white group-hover:bg-orange-500 group-hover:text-white text-gray-400 flex items-center justify-center text-[8px] transition-colors shadow-2xs">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function closeProductModal() {
            hapticFeedback('light');
            const modal = document.getElementById('product-modal');
            modal.classList.add('sheet-hidden');
            setTimeout(() => {
                if (modal.classList.contains('sheet-hidden')) {
                    modal.style.display = 'none';
                }
            }, 260);
            document.body.style.overflow = '';
            currentModalProduct = null;

            const dock = document.getElementById('fab-dock');
            if (dock) dock.classList.remove('opacity-0', 'pointer-events-none', 'scale-90');

            // Hide Telegram BackButton if supported
            if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.BackButton) {
                tg.BackButton.hide();
            }
        }

        // Open & Close About Store Modal
        function openAboutModal() {
            hapticFeedback('medium');
            const dock = document.getElementById('fab-dock');
            if (dock) dock.classList.add('opacity-0', 'pointer-events-none', 'scale-90');

            const modal = document.getElementById('about-modal');
            if (!modal) return;
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.remove('sheet-hidden');
            document.body.style.overflow = 'hidden';

            const sheetBody = document.querySelector('#about-modal .overflow-y-auto');
            if (sheetBody) sheetBody.scrollTop = 0;

            if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.BackButton) {
                tg.BackButton.show();
                tg.BackButton.onClick(closeAboutModal);
            }
        }

        function closeAboutModal() {
            hapticFeedback('light');
            const modal = document.getElementById('about-modal');
            if (!modal) return;
            modal.classList.add('sheet-hidden');
            setTimeout(() => {
                if (modal.classList.contains('sheet-hidden')) {
                    modal.style.display = 'none';
                }
            }, 260);
            document.body.style.overflow = '';

            const dock = document.getElementById('fab-dock');
            if (dock) dock.classList.remove('opacity-0', 'pointer-events-none', 'scale-90');

            if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.BackButton) {
                tg.BackButton.hide();
            }
        }

        // Setup Pull-Down / Swipe-to-Dismiss on Bottom Sheets
        function bindDragToDismiss(sheetContentId, dragHandleId, closeFn) {
            const sheetContent = document.getElementById(sheetContentId);
            const dragHandle = document.getElementById(dragHandleId);
            if (!sheetContent || !dragHandle) return;

            let startY = 0;
            let currentY = 0;
            let isDragging = false;

            const onTouchStart = (e) => {
                const scrollBox = sheetContent.querySelector('.overflow-y-auto');
                if (scrollBox && scrollBox.scrollTop > 5) return;

                startY = e.touches[0].clientY;
                currentY = startY;
                isDragging = true;
                sheetContent.classList.add('dragging');
            };

            const onTouchMove = (e) => {
                if (!isDragging) return;
                currentY = e.touches[0].clientY;
                const deltaY = currentY - startY;

                if (deltaY > 0) {
                    if (e.cancelable) e.preventDefault();
                    sheetContent.style.transform = `translateY(${deltaY}px)`;
                } else {
                    sheetContent.style.transform = '';
                }
            };

            const onTouchEnd = () => {
                if (!isDragging) return;
                isDragging = false;
                sheetContent.classList.remove('dragging');

                const deltaY = currentY - startY;
                sheetContent.style.transform = '';

                if (deltaY > 75) {
                    closeFn();
                }
            };

            dragHandle.addEventListener('touchstart', onTouchStart, { passive: true });
            dragHandle.addEventListener('touchmove', onTouchMove, { passive: false });
            dragHandle.addEventListener('touchend', onTouchEnd);

            const modalHeader = sheetContent.querySelector('.border-b');
            if (modalHeader) {
                modalHeader.addEventListener('touchstart', onTouchStart, { passive: true });
                modalHeader.addEventListener('touchmove', onTouchMove, { passive: false });
                modalHeader.addEventListener('touchend', onTouchEnd);
            }
        }

        function setupSheetDragToDismiss() {
            bindDragToDismiss('sheet-content-el', 'sheet-drag-handle', closeProductModal);
            bindDragToDismiss('about-sheet-content-el', 'about-drag-handle', closeAboutModal);
        }

        // Telegram User Personalized Greeting (Full Name)
        function initUserGreeting() {
            try {
                const container = document.getElementById('user-greeting-container');
                const badge = document.getElementById('user-greeting-badge');
                if (!container || !badge) return;
                const user = tg?.initDataUnsafe?.user;
                if (user) {
                    // Combine first_name and last_name for full name, fallback to username
                    const nameParts = [user.first_name, user.last_name].filter(p => typeof p === 'string' && p.trim().length > 0);
                    let fullName = nameParts.join(' ').trim();
                    if (!fullName && user.username) {
                        fullName = user.username;
                    }

                    if (fullName) {
                        const prefix = CURRENT_LANG === 'km' ? 'សួស្តី' : 'Hi';
                        badge.innerHTML = `<span class="flex-shrink-0 text-sm">👋</span><span>${prefix}, <strong class="font-extrabold text-orange-600">${escapeHtml(fullName)}</strong></span>`;
                        container.classList.remove('hidden');
                        return;
                    }
                }
                container.classList.add('hidden');
            } catch (e) {}
        }

        // Compact Banner Swiper Controller
        let bannerSwiperInstance = null;
        function initBannerSwiper() {
            if (typeof Swiper === 'undefined') return;
            const container = document.querySelector('.compact-banner-swiper');
            if (!container || bannerSwiperInstance) return;

            try {
                bannerSwiperInstance = new Swiper('.compact-banner-swiper', {
                    slidesPerView: 1,
                    spaceBetween: 12,
                    loop: true,
                    autoplay: {
                        delay: 3500,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    },
                    pagination: {
                        el: '.compact-banner-pagination',
                        clickable: true,
                        bulletClass: 'compact-banner-dot',
                        bulletActiveClass: 'compact-banner-dot-active',
                    },
                    touchRatio: 1,
                    resistanceRatio: 0.85,
                    on: {
                        slideChange: function() {
                            hapticFeedback('selection');
                        }
                    }
                });
            } catch (e) {}
        }

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
            filterAndRenderProducts();
            initBannerSwiper();
            setupSheetDragToDismiss();
            initUserGreeting();
        });
        window.addEventListener('load', () => {
            initBannerSwiper();
            setupSheetDragToDismiss();
            initUserGreeting();
        });
    </script>
</body>
</html>
