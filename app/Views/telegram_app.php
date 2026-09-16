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
$storeName = getSetting('company_name', 'KouPrey Coffee');
$storeLogo = getSetting('company_logo', '');
if (empty($storeLogo)) {
    $storeLogo = getSetting('site_logo', '/kouprey/public/assets/images/logo.png');
}
$telegramUrl = getSetting('social_telegram', 'https://t.me/Bos_Sauveli98');
$telegramUser = preg_replace('#^https?://t\.me/#i', '', $telegramUrl);
$telegramUser = ltrim($telegramUser, '@');
if (empty($telegramUser)) {
    $telegramUser = 'Bos_Sauveli98';
}

// Calculate product counts per category
$categoryCounts = [];
$totalProductCount = count($products);
foreach ($categories as $cat) {
    $baseCatId = (string)($cat['base_category_id'] ?? $cat['id']);
    $count = 0;
    foreach ($products as $p) {
        $pCatId = (string)($p['base_category_id'] ?? $p['category_id'] ?? '');
        if ($pCatId === $baseCatId ||
            ($baseCatId === '19' && preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i', $p['name'] ?? '')) ||
            ($baseCatId === '13' && preg_match('/powder|matcha|ម្សៅ/i', $p['name'] ?? ''))
        ) {
            $count++;
        }
    }
    $categoryCounts[$baseCatId] = $count;
}

// Clean products array for client-side JavaScript searching & filtering
$cleanProducts = [];
foreach ($products as $p) {
    $baseCatId = (string)($p['base_category_id'] ?? $p['category_id'] ?? '');
    if (empty($baseCatId)) {
        if (preg_match('/syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i', $p['name'] ?? '')) $baseCatId = '19';
        elseif (preg_match('/powder|matcha|ម្សៅ/i', $p['name'] ?? '')) $baseCatId = '13';
    }

    $image = $p['image'] ?: '/kouprey/public/assets/images/product-medium.png';
    $cleanProducts[] = [
        'id' => $p['id'] ?? 0,
        'base_id' => $p['base_product_id'] ?? $p['id'] ?? 0,
        'name' => $p['name'] ?? '',
        'price' => (float)($p['price'] ?? 0),
        'image' => $image,
        'description' => trim(strip_tags($p['description'] ?? '')),
        'detailed_description' => trim(strip_tags($p['detailed_description'] ?? '')),
        'category_id' => $baseCatId,
        'category_name' => $p['category_name'] ?? '',
        'featured' => !empty($p['featured']),
        'best_seller' => !empty($p['best_seller']),
        'avg_rating' => (float)($p['avg_rating'] ?? 5.0)
    ];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($storeName); ?> - Telegram Mini App</title>

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
                    colors: {
                        tg: {
                            bg: 'var(--tg-theme-bg-color, #f8fafc)',
                            text: 'var(--tg-theme-text-color, #0f172a)',
                            hint: 'var(--tg-theme-hint-color, #64748b)',
                            link: 'var(--tg-theme-link-color, #f97316)',
                            button: 'var(--tg-theme-button-color, #f97316)',
                            buttonText: 'var(--tg-theme-button-text-color, #ffffff)',
                            secondaryBg: 'var(--tg-theme-secondary-bg-color, #ffffff)',
                        },
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                        }
                    },
                    fontFamily: {
                        sans: ['"Kantumruy Pro"', '"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --app-bg: var(--tg-theme-bg-color, #f8fafc);
            --app-card: var(--tg-theme-secondary-bg-color, #ffffff);
            --app-text: var(--tg-theme-text-color, #0f172a);
            --app-hint: var(--tg-theme-hint-color, #64748b);
            --app-button: var(--tg-theme-button-color, #f97316);
            --app-button-text: var(--tg-theme-button-text-color, #ffffff);
        }

        body {
            background-color: var(--app-bg);
            color: var(--app-text);
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
        }

        /* Image aspect ratio helper */
        .aspect-square-img {
            aspect-ratio: 1 / 1;
            object-fit: contain;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col pb-24 select-none">

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Top Sticky Header: Brand + Language Toggle + Search -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-gray-100/80 shadow-xs px-4 pt-3 pb-3">
        <div class="flex items-center justify-between gap-3 mb-2.5">
            <!-- Brand Logo & Title -->
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-orange-500/10 flex items-center justify-center p-1 border border-orange-500/20 overflow-hidden flex-shrink-0">
                    <img src="<?php echo htmlspecialchars($storeLogo); ?>" 
                         alt="Logo" 
                         class="w-full h-full object-contain"
                         onerror="this.src='/kouprey/public/assets/images/logo.png'">
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-900 leading-tight flex items-center gap-1.5">
                        <span><?php echo htmlspecialchars($storeName); ?></span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-orange-100 text-orange-700">TMA</span>
                    </h1>
                    <p class="text-[11px] text-gray-500 font-medium">
                        <?php echo $currentLanguage === 'km' ? 'កាតាឡុកផលិតផលផ្លូវការ' : 'Official Product Catalog'; ?>
                    </p>
                </div>
            </div>

            <!-- Header Actions: Language & Cart -->
            <div class="flex items-center gap-1.5">
                <!-- Language Switcher -->
                <a href="?lang=<?php echo $currentLanguage === 'km' ? 'en' : 'km'; ?>" 
                   onclick="hapticFeedback('selection')"
                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-gray-100 text-gray-700 text-xs font-semibold hover:bg-gray-200 transition-colors">
                    <span><?php echo $currentLanguage === 'km' ? '🇬🇧 EN' : '🇰🇭 ខ្មែរ'; ?></span>
                </a>

                <!-- Cart Button -->
                <button onclick="openCart(); hapticFeedback('light');" 
                        class="relative w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center hover:bg-orange-100 transition-colors">
                    <i class="fas fa-shopping-bag text-sm"></i>
                    <span id="cart-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white rounded-full text-[10px] font-bold flex items-center justify-center shadow-xs">
                        0
                    </span>
                </button>
            </div>
        </div>

        <!-- Live Instant Search Bar -->
        <div class="relative">
            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" 
                   id="search-input" 
                   placeholder="<?php echo $currentLanguage === 'km' ? 'ស្វែងរកឈ្មោះផលិតផល...' : 'Search products...'; ?>" 
                   class="w-full bg-gray-100/90 text-gray-900 placeholder-gray-400 text-xs sm:text-sm pl-9 pr-8 py-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500/40 focus:bg-white transition-all">
            <button id="search-clear-btn" 
                    onclick="clearSearch()" 
                    class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-[10px] hover:bg-gray-400">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </header>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Horizontal Category Filter Pills -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div class="sticky top-[102px] z-20 bg-white/90 backdrop-blur-md border-b border-gray-100 px-3 py-2.5">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth" id="category-pills">
            <!-- All Products Pill -->
            <button onclick="selectCategory('all', this)" 
                    data-cat-id="all"
                    class="category-pill flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-bold transition-all bg-orange-500 text-white shadow-xs">
                <span><?php echo $currentLanguage === 'km' ? 'ទាំងអស់' : 'All'; ?></span>
                <span class="ml-1 opacity-85 text-[10px]">(<?php echo $totalProductCount; ?>)</span>
            </button>

            <!-- Database Category Pills -->
            <?php foreach ($categories as $category): 
                $catId = (string)($category['base_category_id'] ?? $category['id']);
                $count = $categoryCounts[$catId] ?? 0;
            ?>
                <button onclick="selectCategory('<?php echo htmlspecialchars($catId); ?>', this)" 
                        data-cat-id="<?php echo htmlspecialchars($catId); ?>"
                        class="category-pill flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200">
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                    <span class="ml-1 text-[10px] text-gray-400">(<?php echo $count; ?>)</span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Products Catalog Grid (Mobile 2-Column) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <main class="flex-1 px-3.5 pt-3">
        <!-- Products Count / Active Filter Info -->
        <div class="flex items-center justify-between mb-2.5 px-0.5">
            <span class="text-xs font-bold text-gray-500" id="product-counter">
                <?php echo $currentLanguage === 'km' ? 'ផលិតផលសរុប' : 'Total Products'; ?>: <?php echo $totalProductCount; ?>
            </span>
            <span class="text-[11px] text-orange-600 font-medium cursor-pointer hover:underline" onclick="selectCategory('all'); clearSearch();">
                <?php echo $currentLanguage === 'km' ? 'កំណត់ឡើងវិញ' : 'Reset Filters'; ?>
            </span>
        </div>

        <!-- Dynamic Product Cards Grid -->
        <div id="product-grid" class="grid grid-cols-2 gap-2.5 sm:gap-3.5">
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
                <?php echo $currentLanguage === 'km' ? 'សូមសាកល្បងស្វែងរកពាក្យផ្សេង ឬដោះការច្រោះប្រភេទផលិតផល' : 'Try another search term or reset filters'; ?>
            </p>
            <button onclick="selectCategory('all'); clearSearch();" 
                    class="px-4 py-2 rounded-xl bg-orange-500 text-white text-xs font-bold shadow-xs active:scale-95 transition-transform">
                <?php echo $currentLanguage === 'km' ? 'មើលផលិតផលទាំងអស់' : 'Show all products'; ?>
            </button>
        </div>
    </main>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Floating Bottom Bar: Fast Cart / Checkout Summary -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="floating-cart-bar" class="hidden fixed bottom-3 left-3 right-3 z-30 max-w-md mx-auto">
        <div class="bg-gray-900 text-white px-4 py-3 rounded-2xl shadow-xl flex items-center justify-between border border-gray-800/80 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-orange-500 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <span id="floating-cart-count">0</span>
                </div>
                <div>
                    <span class="text-[11px] text-gray-400 block font-medium">
                        <?php echo $currentLanguage === 'km' ? 'តម្លៃសរុប' : 'Total Amount'; ?>
                    </span>
                    <span class="text-sm font-bold text-orange-400" id="floating-cart-total">$0.00</span>
                </div>
            </div>
            <button onclick="openCart(); hapticFeedback('medium');" 
                    class="px-4 py-2 rounded-xl bg-orange-500 text-white text-xs font-bold hover:bg-orange-600 active:scale-95 transition-all flex items-center gap-1.5 shadow-md shadow-orange-500/30">
                <span><?php echo $currentLanguage === 'km' ? 'មើលកន្ត្រក' : 'View Cart'; ?></span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </button>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Product Detail Modal (Native-like Bottom Sheet) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="product-modal" class="fixed inset-0 z-50 sheet-hidden flex flex-col justify-end">
        <!-- Backdrop -->
        <div class="sheet-backdrop absolute inset-0 bg-black/50 backdrop-blur-xs" onclick="closeProductModal()"></div>

        <!-- Sheet Content -->
        <div class="sheet-content relative bg-white rounded-t-[2rem] max-h-[88vh] flex flex-col overflow-hidden shadow-2xl z-10">
            <!-- Drag Handle Bar -->
            <div class="w-full pt-3 pb-1 flex justify-center cursor-pointer" onclick="closeProductModal()">
                <div class="w-12 h-1 rounded-full bg-gray-300"></div>
            </div>

            <!-- Scrollable Body -->
            <div class="overflow-y-auto px-5 pt-2 pb-6 space-y-4">
                <!-- Image Container with Badges -->
                <div class="relative w-full aspect-square bg-gray-50 rounded-2xl flex items-center justify-center p-4 overflow-hidden border border-gray-100">
                    <img id="modal-img" src="" alt="" class="w-full h-full object-contain filter drop-shadow-md">
                    
                    <button onclick="closeProductModal()" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/90 text-gray-600 shadow-sm flex items-center justify-center text-xs hover:bg-white active:scale-90">
                        <i class="fas fa-times"></i>
                    </button>

                    <div id="modal-badges" class="absolute top-3 left-3 flex flex-col gap-1"></div>
                </div>

                <!-- Product Title & Price -->
                <div>
                    <span id="modal-category" class="inline-block text-[11px] font-bold text-orange-600 uppercase tracking-wider mb-1"></span>
                    <h2 id="modal-title" class="text-lg font-bold text-gray-900 leading-snug"></h2>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span id="modal-price" class="text-2xl font-black text-gray-900"></span>
                        <span class="text-xs text-gray-400 font-medium">USD</span>
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-xs text-gray-600 leading-relaxed space-y-2">
                    <h4 class="font-bold text-gray-900 text-xs uppercase tracking-wider">
                        <?php echo $currentLanguage === 'km' ? 'ព័ត៌មានលម្អិត' : 'Description'; ?>
                    </h4>
                    <p id="modal-desc"></p>
                </div>

                <!-- Quantity Selector -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-gray-100/70 border border-gray-200/50">
                    <span class="text-xs font-bold text-gray-700">
                        <?php echo $currentLanguage === 'km' ? 'បរិមាណ (ចំនួន)' : 'Quantity'; ?>:
                    </span>
                    <div class="flex items-center gap-3">
                        <button onclick="adjustModalQty(-1)" class="w-8 h-8 rounded-lg bg-white text-gray-800 font-bold shadow-xs flex items-center justify-center active:scale-90">
                            <i class="fas fa-minus text-[10px]"></i>
                        </button>
                        <span id="modal-qty" class="text-sm font-bold text-gray-900 min-w-[20px] text-center">1</span>
                        <button onclick="adjustModalQty(1)" class="w-8 h-8 rounded-lg bg-white text-gray-800 font-bold shadow-xs flex items-center justify-center active:scale-90">
                            <i class="fas fa-plus text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- Action Buttons: Add to Cart & Direct Order via Telegram -->
                <div class="grid grid-cols-2 gap-2.5 pt-1">
                    <button onclick="addModalProductToCart()" 
                            class="py-3 px-3 rounded-xl bg-orange-50 text-orange-600 font-bold text-xs hover:bg-orange-100 active:scale-95 transition-all flex items-center justify-center gap-1.5 border border-orange-200/60">
                        <i class="fas fa-cart-plus"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'ដាក់កន្ត្រក' : 'Add to Cart'; ?></span>
                    </button>

                    <button onclick="orderCurrentProductViaTelegram()" 
                            class="py-3 px-3 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-white font-bold text-xs shadow-md shadow-orange-500/25 active:scale-95 transition-all flex items-center justify-center gap-1.5">
                        <i class="fab fa-telegram-plane text-sm"></i>
                        <span><?php echo $currentLanguage === 'km' ? 'កុម្ម៉ង់ឥឡូវនេះ' : 'Order Now'; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- Cart Modal (Bottom Sheet) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="cart-modal" class="fixed inset-0 z-50 sheet-hidden flex flex-col justify-end">
        <div class="sheet-backdrop absolute inset-0 bg-black/50 backdrop-blur-xs" onclick="closeCart()"></div>
        <div class="sheet-content relative bg-white rounded-t-[2rem] max-h-[85vh] flex flex-col overflow-hidden shadow-2xl z-10">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center text-xs">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">
                        <?php echo $currentLanguage === 'km' ? 'កន្ត្រកទំនិញរបស់អ្នក' : 'Your Shopping Cart'; ?>
                    </h3>
                </div>
                <button onclick="closeCart()" class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs hover:bg-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Items List -->
            <div id="cart-items-container" class="flex-1 overflow-y-auto p-5 space-y-3">
                <!-- Cart items rendered here via JS -->
            </div>

            <!-- Cart Footer with Send to Telegram Button -->
            <div id="cart-footer" class="p-5 border-t border-gray-100 bg-gray-50/70 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-600">
                        <?php echo $currentLanguage === 'km' ? 'តម្លៃទំនិញសរុប' : 'Total Price'; ?>:
                    </span>
                    <span id="cart-grand-total" class="text-xl font-black text-gray-900">$0.00</span>
                </div>

                <button onclick="sendCartOrderViaTelegram()" 
                        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-white font-bold text-sm shadow-lg shadow-orange-500/30 active:scale-98 transition-all flex items-center justify-center gap-2">
                    <i class="fab fa-telegram-plane text-base"></i>
                    <span><?php echo $currentLanguage === 'km' ? 'ផ្ញើការកុម្ម៉ង់ទៅ Telegram' : 'Send Order to Telegram'; ?></span>
                </button>
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
        const TELEGRAM_USER = '<?php echo htmlspecialchars($telegramUser, ENT_QUOTES, 'UTF-8'); ?>';

        // State
        let selectedCategory = 'all';
        let searchQuery = '';
        let currentModalProduct = null;
        let modalQuantity = 1;
        let cart = [];

        // Telegram WebApp Setup
        const tg = window.Telegram?.WebApp;
        if (tg) {
            tg.ready();
            tg.expand();
            // Set header color to match app
            try {
                tg.setHeaderColor('#ffffff');
                tg.setBackgroundColor('#f8fafc');
            } catch (e) {}
        }

        // Haptic feedback helper
        function hapticFeedback(type = 'light') {
            try {
                if (tg?.HapticFeedback) {
                    if (type === 'selection') tg.HapticFeedback.selectionChanged();
                    else if (type === 'medium') tg.HapticFeedback.impactOccurred('medium');
                    else tg.HapticFeedback.impactOccurred('light');
                }
            } catch (e) {}
        }

        // Load cart from localStorage
        function loadCart() {
            try {
                const saved = localStorage.getItem('kouprey_tg_cart');
                if (saved) {
                    cart = JSON.parse(saved);
                }
            } catch (e) {
                cart = [];
            }
            updateCartUI();
        }

        // Save cart to localStorage
        function saveCart() {
            try {
                localStorage.setItem('kouprey_tg_cart', JSON.stringify(cart));
            } catch (e) {}
            updateCartUI();
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
                // Category match
                const matchCat = (selectedCategory === 'all') || 
                                 (p.category_id == selectedCategory) ||
                                 (selectedCategory === '19' && /syrup|ស៊ីរ៉ូ|សុីរ៉ូ/i.test(p.name)) ||
                                 (selectedCategory === '13' && /powder|matcha|ម្សៅ/i.test(p.name));

                // Search query match
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
                html += `
                    <div class="bg-white rounded-2xl p-2.5 shadow-xs hover:shadow-md border border-gray-100 flex flex-col justify-between cursor-pointer active:scale-[0.98] transition-all group"
                         onclick="openProductModal(${p.id})">
                        
                        <!-- Image Frame -->
                        <div class="relative w-full aspect-square bg-gray-50 rounded-xl overflow-hidden p-2 flex items-center justify-center mb-2">
                            <img src="${p.image}" 
                                 alt="${safeName}" 
                                 loading="lazy"
                                 onerror="this.src='/kouprey/public/assets/images/product-medium.png'"
                                 class="w-full h-full object-contain filter drop-shadow-sm group-hover:scale-105 transition-transform duration-300">
                            
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
                            <h3 class="text-xs sm:text-sm font-bold text-gray-900 line-clamp-2 leading-tight mb-1 group-hover:text-orange-600 transition-colors">
                                ${safeName}
                            </h3>

                            <div class="pt-1.5 flex items-center justify-between border-t border-gray-50 mt-1">
                                <span class="text-sm font-extrabold text-gray-900">${priceFormatted}</span>
                                <div class="w-6 h-6 rounded-lg bg-orange-50 text-orange-600 group-hover:bg-orange-500 group-hover:text-white flex items-center justify-center text-[10px] transition-colors shadow-2xs">
                                    <i class="fas fa-plus"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        // Category Selection
        function selectCategory(catId, btnEl = null) {
            hapticFeedback('selection');
            selectedCategory = catId;

            // Update pill styling
            document.querySelectorAll('.category-pill').forEach(el => {
                if (el.getAttribute('data-cat-id') === catId) {
                    el.className = 'category-pill flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-bold transition-all bg-orange-500 text-white shadow-xs';
                } else {
                    el.className = 'category-pill flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200';
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
            modalQuantity = 1;

            document.getElementById('modal-img').src = product.image;
            document.getElementById('modal-title').innerText = product.name;
            document.getElementById('modal-price').innerText = '$' + product.price.toFixed(2);
            document.getElementById('modal-category').innerText = product.category_name || (CURRENT_LANG === 'km' ? 'ប្រភេទផលិតផល' : 'Product');
            document.getElementById('modal-desc').innerText = product.description || product.detailed_description || (CURRENT_LANG === 'km' ? 'គុណភាពខ្ពស់ រសជាតិឈ្ងុយឆ្ងាញ់' : 'High quality beverage ingredient');
            document.getElementById('modal-qty').innerText = modalQuantity;

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
            modal.classList.remove('sheet-hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeProductModal() {
            hapticFeedback('light');
            const modal = document.getElementById('product-modal');
            modal.classList.add('sheet-hidden');
            document.body.style.overflow = '';
            currentModalProduct = null;
        }

        function adjustModalQty(delta) {
            hapticFeedback('selection');
            modalQuantity = Math.max(1, modalQuantity + delta);
            document.getElementById('modal-qty').innerText = modalQuantity;
        }

        // Add to Cart
        function addModalProductToCart() {
            if (!currentModalProduct) return;
            hapticFeedback('medium');

            const existingIndex = cart.findIndex(item => item.id === currentModalProduct.id);
            if (existingIndex > -1) {
                cart[existingIndex].qty += modalQuantity;
            } else {
                cart.push({
                    id: currentModalProduct.id,
                    name: currentModalProduct.name,
                    price: currentModalProduct.price,
                    image: currentModalProduct.image,
                    qty: modalQuantity
                });
            }

            saveCart();
            closeProductModal();

            // Notify briefly via Telegram popup if supported
            if (tg?.showPopup) {
                // optional
            }
        }

        // Direct Order via Telegram (Single Product)
        function orderCurrentProductViaTelegram() {
            if (!currentModalProduct) return;
            hapticFeedback('medium');

            const totalPrice = (currentModalProduct.price * modalQuantity).toFixed(2);
            const msg = CURRENT_LANG === 'km'
                ? `សួស្តី KouPrey Coffee! ☕\nខ្ញុំចង់កុម្ម៉ង់ទិញ៖\n• ${currentModalProduct.name}\n• ចំនួន៖ ${modalQuantity}\n• តម្លៃសរុប៖ $${totalPrice}\n\nសូមជួយពិនិត្យ និងផ្តល់ព័ត៌មានបន្ថែម!`
                : `Hello KouPrey Coffee! ☕\nI would like to order:\n• ${currentModalProduct.name}\n• Quantity: ${modalQuantity}\n• Total Price: $${totalPrice}\n\nPlease confirm my order!`;

            const link = `https://t.me/${TELEGRAM_USER}?text=${encodeURIComponent(msg)}`;
            
            if (tg?.openTelegramLink) {
                tg.openTelegramLink(link);
            } else {
                window.open(link, '_blank');
            }
        }

        // Cart Modal Handlers
        function openCart() {
            hapticFeedback('light');
            renderCartItems();
            const modal = document.getElementById('cart-modal');
            modal.classList.remove('sheet-hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeCart() {
            hapticFeedback('light');
            const modal = document.getElementById('cart-modal');
            modal.classList.add('sheet-hidden');
            document.body.style.overflow = '';
        }

        function renderCartItems() {
            const container = document.getElementById('cart-items-container');
            const grandTotalEl = document.getElementById('cart-grand-total');
            const footerEl = document.getElementById('cart-footer');

            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="py-12 text-center text-gray-400">
                        <i class="fas fa-shopping-bag text-3xl mb-2"></i>
                        <p class="text-xs">${CURRENT_LANG === 'km' ? 'មិនទាន់មានទំនិញក្នុងកន្ត្រកទេ' : 'Your cart is empty'}</p>
                    </div>
                `;
                footerEl.classList.add('hidden');
                return;
            }

            footerEl.classList.remove('hidden');

            let total = 0;
            let html = '';

            cart.forEach((item, index) => {
                const subtotal = item.price * item.qty;
                total += subtotal;

                const safeItemName = escapeHtml(item.name);
                html += `
                    <div class="flex items-center gap-3 p-2.5 rounded-xl bg-gray-50 border border-gray-100">
                        <img src="${item.image}" alt="${safeItemName}" class="w-12 h-12 rounded-lg object-contain bg-white p-1 border border-gray-100">
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-900 truncate">${safeItemName}</h4>
                            <span class="text-xs text-orange-600 font-bold">$${item.price.toFixed(2)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="changeCartQty(${index}, -1)" class="w-6 h-6 rounded bg-white text-gray-700 font-bold text-xs shadow-2xs flex items-center justify-center active:scale-90">-</button>
                            <span class="text-xs font-bold text-gray-900 min-w-[14px] text-center">${item.qty}</span>
                            <button onclick="changeCartQty(${index}, 1)" class="w-6 h-6 rounded bg-white text-gray-700 font-bold text-xs shadow-2xs flex items-center justify-center active:scale-90">+</button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            grandTotalEl.innerText = '$' + total.toFixed(2);
        }

        function changeCartQty(index, delta) {
            hapticFeedback('selection');
            if (!cart[index]) return;
            cart[index].qty += delta;
            if (cart[index].qty <= 0) {
                cart.splice(index, 1);
            }
            saveCart();
            renderCartItems();
        }

        function updateCartUI() {
            const badge = document.getElementById('cart-badge');
            const floatingBar = document.getElementById('floating-cart-bar');
            const floatingCount = document.getElementById('floating-cart-count');
            const floatingTotal = document.getElementById('floating-cart-total');

            const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
            const totalSum = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

            if (totalItems > 0) {
                badge.innerText = totalItems;
                badge.classList.remove('hidden');

                floatingCount.innerText = totalItems;
                floatingTotal.innerText = '$' + totalSum.toFixed(2);
                floatingBar.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
                floatingBar.classList.add('hidden');
            }
        }

        // Send Entire Cart to Telegram
        function sendCartOrderViaTelegram() {
            if (cart.length === 0) return;
            hapticFeedback('medium');

            let total = 0;
            let itemsText = '';

            cart.forEach((item, i) => {
                const sub = item.price * item.qty;
                total += sub;
                itemsText += `${i + 1}. ${item.name} x ${item.qty} = $${sub.toFixed(2)}\n`;
            });

            const msg = CURRENT_LANG === 'km'
                ? `សួស្តី KouPrey Coffee! ☕\nខ្ញុំចង់កុម្ម៉ង់ទិញទំនិញដូចខាងក្រោម៖\n---------------------------\n${itemsText}---------------------------\n💵 តម្លៃសរុប៖ $${total.toFixed(2)}\n\nសូមជួយពិនិត្យ និងបញ្ជាក់ការកុម្ម៉ង់!`
                : `Hello KouPrey Coffee! ☕\nI would like to order the following items:\n---------------------------\n${itemsText}---------------------------\n💵 Total Amount: $${total.toFixed(2)}\n\nPlease confirm my order!`;

            const link = `https://t.me/${TELEGRAM_USER}?text=${encodeURIComponent(msg)}`;

            if (tg?.openTelegramLink) {
                tg.openTelegramLink(link);
            } else {
                window.open(link, '_blank');
            }
        }

        // Initial Load
        document.addEventListener('DOMContentLoaded', () => {
            loadCart();
            filterAndRenderProducts();
        });
    </script>
</body>
</html>
