<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';
require_once __DIR__ . '/../Config/catalog_cache.php';

// Determine language (default to Khmer, support switch to English)
$currentLanguage = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'km';

// Load catalog data (Cached for sub-millisecond loading)
$catalogData = getCatalogData($currentLanguage);
$products = $catalogData['products'] ?? [];
$categories = $catalogData['categories'] ?? [];

// General store settings
$storeName = getSetting('company_name', 'GoGo Brand');
$storeLogo = getSetting('company_logo', '');
if (empty($storeLogo)) {
    $storeLogo = getSetting('site_logo', '/kouprey/public/assets/images/logo.png');
}

// Build clean categories list and initialize count
$cleanCategories = [];
$categoryCounts = [];

foreach ($categories as $cat) {
    $catKey = (string)($cat['base_category_id'] ?: $cat['id']);
    $cleanCategories[$catKey] = [
        'key' => $catKey,
        'base_id' => (string)($cat['base_category_id'] ?? ''),
        'id' => (string)($cat['id'] ?? ''),
        'name' => $cat['name'] ?? ''
    ];
    $categoryCounts[$catKey] = 0;
}

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
    $cleanProducts[] = [
        'id' => (int)($p['id'] ?? 0),
        'base_id' => (int)($p['base_product_id'] ?? $p['id'] ?? 0),
        'name' => $p['name'] ?? '',
        'price' => (float)($p['price'] ?? 0),
        'image' => $image,
        'description' => trim($p['description'] ?? ''),
        'detailed_description' => trim($p['detailed_description'] ?? ''),
        'weight' => trim($p['weight'] ?? ''),
        'roast_level' => trim($p['roast_level'] ?? ''),
        'custom_fields' => $customFields,
        'category_key' => $matchedCatKey,
        'category_name' => $matchedCatName ?: ($currentLanguage === 'km' ? 'ផលិតផល' : 'Product'),
        'featured' => !empty($p['featured']),
        'best_seller' => !empty($p['best_seller']),
        'avg_rating' => (float)($p['avg_rating'] ?? 5.0),
        'review_count' => (int)($p['review_count'] ?? 0)
    ];
}

$totalProductCount = count($cleanProducts);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($storeName); ?> - GoGo Brand Catalog</title>

    <!-- Telegram WebApp SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <!-- Google Fonts (Kantumruy Pro & Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            <div class="px-4 pb-2.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-orange-50 flex items-center justify-center p-1 border border-orange-200/50 overflow-hidden flex-shrink-0">
                        <img src="<?php echo htmlspecialchars($storeLogo); ?>" 
                             alt="Logo" 
                             class="w-full h-full object-contain"
                             onerror="this.src='/kouprey/public/assets/images/logo.png'">
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-gray-900 leading-tight flex items-center gap-1.5">
                            <span><?php echo htmlspecialchars($storeName); ?></span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-orange-100 text-orange-700">GoGo</span>
                        </h1>
                        <p class="text-[11px] text-gray-500 font-medium">
                            <?php echo $currentLanguage === 'km' ? 'កាតាឡុកផលិតផលផ្លូវការ' : 'Official Product Catalog'; ?>
                        </p>
                    </div>
                </div>

                <!-- Actions: Fullscreen Button (if supported) + Language Switcher -->
                <div class="flex items-center gap-1.5">
                    <button id="fullscreen-btn" 
                            type="button"
                            onclick="toggleFullscreen(); hapticFeedback('light');" 
                            class="hidden inline-flex items-center justify-center w-8 h-8 rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200 active:scale-95 transition-all text-xs"
                            title="<?php echo $currentLanguage === 'km' ? 'ពង្រីកពេញអេក្រង់' : 'Full Screen'; ?>">
                        <i class="fas fa-expand text-[11px]" id="fullscreen-icon"></i>
                    </button>
                    <a href="?lang=<?php echo $currentLanguage === 'km' ? 'en' : 'km'; ?>" 
                       onclick="hapticFeedback('selection')"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200 active:scale-95 transition-all">
                        <span><?php echo $currentLanguage === 'km' ? '🇬🇧 EN' : '🇰🇭 ខ្មែរ'; ?></span>
                    </a>
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
        <main class="flex-1 px-3.5 pt-3 pb-8">
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
    <!-- Product Detail Modal (Bottom Sheet - Full Details Showcase) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="product-modal" class="fixed inset-0 z-50 sheet-hidden flex flex-col justify-end" style="display: none;">
        <!-- Backdrop -->
        <div class="sheet-backdrop absolute inset-0 bg-black/60 backdrop-blur-xs" onclick="closeProductModal()"></div>

        <!-- Sheet Content -->
        <div class="sheet-content relative bg-white w-full max-w-2xl mx-auto rounded-t-[2rem] max-h-[92vh] flex flex-col overflow-hidden shadow-2xl z-10">
            
            <!-- Sticky Modal Top Header -->
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-white sticky top-0 z-20">
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
                         class="w-full h-full object-contain filter drop-shadow-md">
                    
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

                <!-- Back / Close Button -->
                <div class="pt-2">
                    <button onclick="closeProductModal()" 
                            class="w-full py-3 px-4 rounded-xl bg-gray-900 hover:bg-black active:scale-98 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md transition-all">
                        <i class="fas fa-arrow-left text-xs"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'ត្រឡប់ទៅកាន់បញ្ជីផលិតផល' : 'Back to Product Catalog'; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

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

                // Update fullscreen button icon
                const fsIcon = document.getElementById('fullscreen-icon');
                if (fsIcon && tg) {
                    fsIcon.className = isFull ? 'fas fa-compress text-[11px]' : 'fas fa-expand text-[11px]';
                }
            } catch (e) {}
        }

        function toggleFullscreen() {
            if (!tg) return;
            try {
                if (tg.isFullscreen && typeof tg.exitFullscreen === 'function') {
                    tg.exitFullscreen();
                } else if (typeof tg.requestFullscreen === 'function') {
                    tg.requestFullscreen();
                }
            } catch (e) {}
        }

        if (tg) {
            try {
                tg.ready();
                tg.expand();

                // Show Fullscreen button if API is supported
                const fsBtn = document.getElementById('fullscreen-btn');
                if (fsBtn && typeof tg.requestFullscreen === 'function') {
                    fsBtn.classList.remove('hidden');
                }

                // Automatically request Fullscreen mode (Telegram Bot API 8.0+)
                if (typeof tg.requestFullscreen === 'function') {
                    tg.requestFullscreen();
                }

                updateTelegramSafeArea();

                tg.onEvent?.('fullscreenChanged', updateTelegramSafeArea);
                tg.onEvent?.('fullscreenFailed', updateTelegramSafeArea);
                tg.onEvent?.('safeAreaChanged', updateTelegramSafeArea);
                tg.onEvent?.('contentSafeAreaChanged', updateTelegramSafeArea);
                tg.onEvent?.('viewportChanged', updateTelegramSafeArea);

                // Only set colors if supported by Telegram client version (6.1+)
                if (typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1')) {
                    tg.setHeaderColor?.('#ffffff');
                    tg.setBackgroundColor?.('#f8fafc');
                }
            } catch (e) {}
        }

        // Retry fullscreen on user gesture if needed
        const ensureFullscreen = () => {
            if (tg && typeof tg.requestFullscreen === 'function' && !tg.isFullscreen) {
                try {
                    tg.requestFullscreen();
                } catch (e) {}
            }
        };
        window.addEventListener('touchstart', ensureFullscreen, { passive: true, once: true });
        window.addEventListener('click', ensureFullscreen, { passive: true, once: true });

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

        // HTML escape helper
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Filter and Render Products
        function filterAndRenderProducts() {
            const grid = document.getElementById('product-grid');
            const emptyState = document.getElementById('empty-state');
            const counter = document.getElementById('product-counter');

            const q = searchQuery.toLowerCase().trim();

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
                                 onerror="this.src='/kouprey/public/assets/images/product-medium.png'"
                                 class="w-full h-full object-contain filter drop-shadow-xs group-hover:scale-105 transition-transform duration-300">
                            
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
            hapticFeedback('light');
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

            document.getElementById('modal-img').src = product.image;
            document.getElementById('modal-title').innerText = product.name;
            document.getElementById('modal-price').innerText = '$' + product.price.toFixed(2);
            document.getElementById('modal-category').innerText = product.category_name;
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

            const modal = document.getElementById('product-modal');
            modal.style.display = 'flex';
            // Trigger reflow for smooth bottom-sheet animation
            modal.offsetHeight;
            modal.classList.remove('sheet-hidden');
            document.body.style.overflow = 'hidden';

            // Show Telegram BackButton when modal is open if supported
            if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.BackButton) {
                tg.BackButton.show();
                tg.BackButton.onClick(closeProductModal);
            }
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

            // Hide Telegram BackButton if supported
            if (tg && typeof tg.isVersionAtLeast === 'function' && tg.isVersionAtLeast('6.1') && tg.BackButton) {
                tg.BackButton.hide();
            }
        }

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
            filterAndRenderProducts();
        });
    </script>
</body>
</html>
