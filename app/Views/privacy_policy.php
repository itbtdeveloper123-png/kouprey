<?php
session_start();
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/settings.php';
require_once __DIR__ . '/../Config/visitor_tracker.php';

// Handle language change via GET parameter
if (isset($_GET['set_lang'])) {
    setCurrentLanguage($_GET['set_lang']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle language change via POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_language'])) {
    setCurrentLanguage($_POST['set_language']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$currentLanguage = getCurrentLanguage();

// Fetch products for search functionality
$productStmt = $pdo->prepare("SELECT * FROM products ORDER BY featured DESC, best_seller DESC, id DESC");
$productStmt->execute();
$allProducts = $productStmt->fetchAll();

$productsByBaseId = [];
foreach ($allProducts as $product) {
    $baseId = $product['base_product_id'];
    if (!isset($productsByBaseId[$baseId])) {
        $productsByBaseId[$baseId] = [];
    }
    $productsByBaseId[$baseId][$product['language']] = $product;
}

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

// Get privacy policy content from settings
$privacyContent = getSetting('privacy_policy', '');
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(getCurrentLanguage()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars(getSetting('privacy_policy_title', 'Privacy Policy')); ?> - <?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?></title>
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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @font-face {
            font-family: 'Superspace Bold';
            src: url('/kouprey/public/fonts/Superspace Bold ver 1.00.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        .font-freeman { font-family: 'Superspace Bold', 'Freeman', serif; }

        :lang(km) :where(h1,h2,h3,h4,h5,h6,p,span,div,li,button,a,label,input,textarea,strong,b,em),
        [lang="km"] :where(h1,h2,h3,h4,h5,h6,p,span,div,li,button,a,label,input,textarea,strong,b,em),
        .kh {
            font-family: 'Hanuman', serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .kh-700, :lang(km) strong, :lang(km) b { font-weight: 700; }

        body {
            background: #ffffff !important;
            background-attachment: fixed;
        }

        header {
            background: rgba(255, 255, 255, 0.4) !important;
            backdrop-filter: blur(30px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(30px) saturate(180%) !important;
            border-bottom: 0.5px solid rgba(255, 255, 255, 0.3) !important;
        }
        header.scrolled {
            background: rgba(255, 255, 255, 0.7) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04) !important;
        }

        .footer-dark-coffee {
            background-color: #000000ff !important;
            color: #F4E4BC !important;
        }

        /* Policy content styling */
        .policy-section {
            border-left: 4px solid #3B82F6;
            padding-left: 1.5rem;
            margin-bottom: 2rem;
            transition: border-color 0.3s ease;
        }
        .policy-section:hover {
            border-left-color: #8B5CF6;
        }
        .policy-section h2 {
            color: #1E3A5F;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .policy-section h2 .icon-circle {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .policy-section p, .policy-section li {
            color: #4B5563;
            line-height: 1.9;
            font-size: 1.05rem;
        }
        .policy-section ul {
            list-style: none;
            padding-left: 0;
        }
        .policy-section ul li {
            padding: 0.5rem 0;
            padding-left: 1.75rem;
            position: relative;
        }
        .policy-section ul li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #3B82F6;
            font-weight: bold;
        }

        /* Rich Text Editor Content Styles */
        .content-section h1 { font-size: 1.8rem; color: #1E3A5F; margin: 1.5rem 0 1rem; font-weight: 700; }
        .content-section h2 { font-size: 1.5rem; color: #1E3A5F; margin: 1.5rem 0 0.8rem; font-weight: 700; }
        .content-section h3 { font-size: 1.25rem; color: #374151; margin: 1.2rem 0 0.6rem; font-weight: 600; }
        .content-section h4 { font-size: 1.1rem; color: #374151; margin: 1rem 0 0.5rem; font-weight: 600; }
        .content-section p { color: #4B5563; line-height: 1.9; margin-bottom: 1rem; }
        .content-section ul, .content-section ol { margin: 0.5rem 0 1rem 1.5rem; }
        .content-section li { padding: 0.25rem 0; color: #4B5563; line-height: 1.8; }
        .content-section img { max-width: 100%; height: auto; border-radius: 8px; margin: 1rem 0; }
        .content-section table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        .content-section table td, .content-section table th { border: 1px solid #D1D5DB; padding: 8px 12px; }
        .content-section table th { background: #F3F4F6; font-weight: 600; }
        .content-section blockquote { border-left: 4px solid #3B82F6; padding-left: 1rem; margin: 1rem 0; color: #6B7280; font-style: italic; }
        .content-section a { color: #3B82F6; text-decoration: underline; }
        .content-section strong { font-weight: 700; }
        .content-section em { font-style: italic; }
        .content-section .fa, .content-section .fas, .content-section .far, .content-section .fab { margin-right: 0.3rem; }

        .highlight-box {
            background: linear-gradient(135deg, #EFF6FF 0%, #F0F9FF 100%);
            border: 1px solid #BFDBFE;
            border-radius: 1rem;
            padding: 1.5rem 2rem;
            margin: 1.5rem 0;
        }
        .highlight-box.warning {
            background: linear-gradient(135deg, #FEF2F2 0%, #FFF5F5 100%);
            border-color: #FECACA;
        }

        #searchModal { transition: opacity 0.3s ease; backdrop-filter: blur(4px); }
        #searchModal.hidden { opacity: 0; pointer-events: none; }
        #searchModal:not(.hidden) { animation: modalFadeIn 0.3s ease-out; }
        #searchModal .modal-content {
            transform: scale(0.95);
            transition: transform 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        #searchModal:not(.hidden) .modal-content { transform: scale(1); animation: modalSlideIn 0.3s ease-out; }

        @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalSlideIn { from { opacity: 0; transform: scale(0.95) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        .pb-safe {
            padding-bottom: 20px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }

        @media (max-width: 768px) {
            .policy-section { border-left-width: 3px; padding-left: 1rem; }
            .policy-section h2 { font-size: 1.25rem; }
            .policy-section p, .policy-section li { font-size: 0.95rem; }
            body { padding-bottom: 80px; }
        }
    </style>
    <script>
        function changeLanguage(lang) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'set_language=' + encodeURIComponent(lang)
            }).then(() => { window.location.reload(); });
        }
    </script>
</head>
<body class="bg-white text-gray-800 font-freeman min-h-screen pb-20 flex flex-col">

    <!-- Header -->
    <header class="px-4 py-4 md:px-6 md:py-3 sticky top-0 z-50 transition-all duration-500">
        <div class="flex items-center justify-between max-w-6xl mx-auto h-full">
            <div class="flex items-center">
                <a href="product.php" class="flex items-center transform active:scale-95 transition-transform">
                    <?php 
                    $logoUrl = getSetting('company_logo'); 
                    if (empty($logoUrl)) $logoUrl = getSetting('company_logo', '', 'en');
                    ?>
                    <?php if (!empty($logoUrl)): ?>
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?>" class="h-14 w-auto object-contain" style="height: 56px;">
                    <?php endif; ?>
                </a>
            </div>
            <nav class="hidden md:flex items-center space-x-4">
                <a href="product.php#products" class="<?php echo ($current_page == 'product.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_product', 'Product')); ?></a>
                <a href="features.php" class="<?php echo ($current_page == 'features.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></a>
                <a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></a>
                <a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'text-[#92adc5] font-bold bg-[#92adc5]/10 px-4 py-2 rounded-xl' : 'text-gray-600 hover:text-gray-900 font-bold px-4 py-2 hover:bg-gray-50/50 rounded-xl'; ?> transition-all flex items-center h-10"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></a>
            </nav>
            <div class="flex items-center gap-3">
                <button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 rounded-full px-4 py-2 transition-all active:scale-95 border border-gray-100 shadow-sm" title="Switch Language">
                    <img src="<?php echo getCurrentLanguage() === 'en' ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80' : 'https://cdn-icons-png.flaticon.com/512/16022/16022033.png'; ?>" alt="<?php echo getCurrentLanguage() === 'en' ? 'English' : 'Khmer'; ?>" class="w-6 h-6 object-cover rounded-full shadow-sm">
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

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 md:px-6 py-8 md:py-12">
        <!-- Page Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-6">
                <i class="fas fa-shield-halved text-blue-600 text-3xl"></i>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-3">
                <?php echo htmlspecialchars(getSetting('privacy_policy_title', 'Privacy Policy')); ?>
            </h1>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                <?php echo htmlspecialchars(getSetting('privacy_policy_desc', 'We respect your privacy and are committed to protecting your personal information.')); ?>
            </p>
            <div class="w-20 h-1 bg-gradient-to-r from-blue-400 to-purple-500 mx-auto mt-4 rounded-full"></div>
        </div>

        <?php if (!empty($privacyContent)): ?>
            <!-- Dynamic content from admin settings -->
            <div class="prose max-w-none policy-section content-section">
                <?php echo $privacyContent; ?>
            </div>
        <?php else: ?>
            <!-- Default structured Khmer privacy policy -->
            <div class="space-y-6">
                <!-- Section: Effective Date -->
                <div class="text-center mb-8">
                    <span class="inline-block bg-blue-50 text-blue-700 px-5 py-2 rounded-full text-sm font-semibold">
                        <i class="far fa-calendar-check mr-2"></i>
                        <?php echo getCurrentLanguage() === 'km' ? 'ចូលជាធរមាន៖ ថ្ងៃទី ០១ ខែមករា ឆ្នាំ២០២៥' : 'Effective Date: January 01, 2025'; ?>
                    </span>
                </div>

                <!-- Section 1: Introduction -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-info-circle"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '១. សេចក្តីផ្តើម' : '1. Introduction'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'សូមស្វាគមន៍មកកាន់ KouPrey។ គោលការណ៍ឯកជនភាពនេះពន្យល់ពីរបៀបដែលយើងប្រមូល ប្រើប្រាស់ រក្សាទុក និងការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក នៅពេលដែលអ្នកចូលមើលគេហទំព័ររបស់យើង ឬប្រើប្រាស់សេវាកម្មរបស់យើង។ តាមរយៈការប្រើប្រាស់គេហទំព័រនេះ អ្នកយល់ព្រមចំពោះការប្រម�' : 'Welcome to KouPrey. This Privacy Policy explains how we collect, use, store, and protect your personal information when you visit our website or use our services. By using this website, you agree to the collection and use of information in accordance with this policy.'; ?>
                    </p>
                </div>

                <!-- Section 2: Information We Collect -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-indigo-100 text-indigo-600"><i class="fas fa-database"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '២. ព័ត៌មានដែលយើងប្រមូល' : '2. Information We Collect'; ?>
                    </h2>
                    <p><?php echo getCurrentLanguage() === 'km' ? 'យើងអាចប្រមូលព័ត៌មានដូចខាងក្រោម៖' : 'We may collect the following types of information:'; ?></p>
                    <ul>
                        <li><strong><?php echo getCurrentLanguage() === 'km' ? 'ព័ត៌មានផ្ទាល់ខ្លួន៖' : 'Personal Information:'; ?></strong> <?php echo getCurrentLanguage() === 'km' ? 'ឈ្មោះ អាសយដ្ឋានអ៊ីមែល លេខទូរស័ព្ទ និងអាសយដ្ឋានដឹកជញ្ជូន នៅពេលអ្នកធ្វើការបញ្ជាទិញ ឬទាក់ទងមកយើង។' : 'Name, email address, phone number, and shipping address when you place an order or contact us.'; ?></li>
                        <li><strong><?php echo getCurrentLanguage() === 'km' ? 'ទិន្នន័យប្រើប្រាស់៖' : 'Usage Data:'; ?></strong> <?php echo getCurrentLanguage() === 'km' ? 'ព័ត៌មានអំពីរបៀបដែលអ្នកចូលប្រើគេហទំព័ររបស់យើង រួមមាន អាសយដ្ឋាន IP ប្រភេទកម្មវិធីរុករក ទំព័រដែលបានចូលមើល និងពេលវេលាចូលមើល។' : 'Information about how you use our website, including IP address, browser type, pages visited, and time of visit.'; ?></li>
                        <li><strong><?php echo getCurrentLanguage() === 'km' ? 'ខូគី និងបច្ចេកវិទ្យាតាមដាន៖' : 'Cookies & Tracking:'; ?></strong> <?php echo getCurrentLanguage() === 'km' ? 'យើងប្រើខូគីដើម្បីកែលម្អបទពិសោធន៍អ្នកប្រើប្រាស់ និងវិភាគចរាចរណ៍គេហទំព័រ។' : 'We use cookies to improve user experience and analyze website traffic.'; ?></li>
                    </ul>
                </div>

                <!-- Section 3: How We Use Information -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-green-100 text-green-600"><i class="fas fa-gear"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៣. របៀបដែលយើងប្រើព័ត៌មាន' : '3. How We Use Your Information'; ?>
                    </h2>
                    <p><?php echo getCurrentLanguage() === 'km' ? 'យើងប្រើព័ត៌មានរបស់អ្នកសម្រាប់គោលបំណងដូចខាងក្រោម៖' : 'We use your information for the following purposes:'; ?></p>
                    <ul>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'ដំណើរការ និងបំពេញការបញ្ជាទិញរបស់អ្នក' : 'To process and fulfill your orders'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'ទំនាក់ទំនងជាមួយអ្នកអំពីការបញ្ជាទិញ ផលិតផល និងសេវាកម្ម' : 'To communicate with you about orders, products, and services'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'កែលម្អគេហទំព័រ និងបទពិសោធន៍អ្នកប្រើប្រាស់របស់យើង' : 'To improve our website and user experience'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'ផ្ញើព្រឹត្តិបត្រ និងសារផ្សព្វផ្សាយ (ដោយមានការយល់ព្រមពីអ្នក)' : 'To send newsletters and promotional messages (with your consent)'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'អនុលោមតាមកាតព្វកិច្ចផ្លូវច្បាប់' : 'To comply with legal obligations'; ?></li>
                    </ul>
                </div>

                <!-- Section 4: Data Protection -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-purple-100 text-purple-600"><i class="fas fa-lock"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៤. ការការពារទិន្នន័យ' : '4. Data Protection'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'យើងអនុវត្តវិធានការសុវត្ថិភាពត្រឹមត្រូវដើម្បីការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នកពីការចូលប្រើ ការផ្លាស់ប្តូរ ការបង្ហាញ ឬការបំផ្លាញដោយគ្មានការអនុញ្ញាត។ វិធានការទាំងនេះរួមមានការអ៊ិនគ្រីបទិន្នន័យ ការគ្រប់គ្រងការចូលប្រើ និងការត្រួតពិនិត្យសុវត្ថិភាពជាប្រចាំ។' : 'We implement appropriate security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction. These measures include data encryption, access controls, and regular security audits.'; ?>
                    </p>
                </div>

                <!-- Section 5: Data Sharing -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-orange-100 text-orange-600"><i class="fas fa-share-nodes"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៥. ការចែករំលែកទិន្នន័យ' : '5. Data Sharing'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'យើងមិនលក់ ជួញដូរ ឬជួលព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នកទៅឱ្យភាគីទីបីឡើយ។ យើងអាចចែករំលែកព័ត៌មានជាមួយដៃគូដែលទុកចិត្តបាន ដើម្បីជួយយើងក្នុងប្រតិបត្តិការគេហទំព័រ និងអាជីវកម្ម ដោយភាគីទាំងនោះបានយល់ព្រមរក្សាការសម្ងាត់នៃព័ត៌មាននេះ។' : 'We do not sell, trade, or rent your personal information to third parties. We may share information with trusted partners who assist us in operating our website and business, provided those parties agree to keep this information confidential.'; ?>
                    </p>
                </div>

                <!-- Section 6: Your Rights -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-red-100 text-red-600"><i class="fas fa-user-check"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៦. សិទ្ធិរបស់អ្នក' : '6. Your Rights'; ?>
                    </h2>
                    <p><?php echo getCurrentLanguage() === 'km' ? 'អ្នកមានសិទ្ធិដូចខាងក្រោមទាក់ទងនឹងព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក៖' : 'You have the following rights regarding your personal information:'; ?></p>
                    <ul>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'សិទ្ធិចូលប្រើ៖ អ្នកអាចស្នើសុំច្បាប់ចម្លងនៃទិន្នន័យផ្ទាល់ខ្លួនរបស់អ្នក' : 'Right to Access: You can request a copy of your personal data'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'សិទ្ធិកែតម្រូវ៖ អ្នកអាចស្នើសុំកែតម្រូវព័ត៌មានមិនត្រឹមត្រូវ' : 'Right to Rectification: You can request correction of inaccurate information'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'សិទ្ធិលុប៖ អ្នកអាចស្នើសុំលុបទិន្នន័យរបស់អ្នក' : 'Right to Erasure: You can request deletion of your data'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'សិទ្ធិដកការយល់ព្រម៖ អ្នកអាចដកការយល់ព្រមរបស់អ្នកបានគ្រប់ពេល' : 'Right to Withdraw Consent: You can withdraw your consent at any time'; ?></li>
                    </ul>
                </div>

                <!-- Section 7: Third-Party Links -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-teal-100 text-teal-600"><i class="fas fa-link"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៧. តំណភ្ជាប់ទៅភាគីទីបី' : '7. Third-Party Links'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'គេហទំព័ររបស់យើងអាចមានតំណភ្ជាប់ទៅកាន់គេហទំព័រផ្សេងទៀត។ យើងមិនទទួលខុសត្រូវចំពោះគោលការណ៍ឯកជនភាព ឬខ្លឹមសារនៃគេហទំព័រទាំងនោះឡើយ។ យើងសូមណែនាំឱ្យអ្នកអានគោលការណ៍ឯកជនភាពនៃគេហទំព័រនីមួយៗដែលអ្នកចូលមើល។' : 'Our website may contain links to other websites. We are not responsible for the privacy policies or content of those websites. We recommend that you read the privacy policy of each website you visit.'; ?>
                    </p>
                </div>

                <!-- Section 8: Cookies -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-yellow-100 text-yellow-600"><i class="fas fa-cookie-bite"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៨. ខូគី (Cookies)' : '8. Cookies'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'យើងប្រើខូគី និងបច្ចេកវិទ្យាស្រដៀងគ្នាដើម្បីតាមដានសកម្មភាពនៅលើគេហទំព័ររបស់យើង និងរក្សាទុកព័ត៌មានជាក់លាក់។ អ្នកអាចកំណត់កម្មវិធីរុករករបស់អ្នកឱ្យបដិសេធខូគីទាំងអស់ ឬជូនដំណឹងនៅពេលដែលខូគីកំពុងត្រូវបានផ្ញើ។ ទោះយ៉ាងណា ប្រសិនបើអ្នកមិនទទួលយកខូគី អ្នកអាចនឹងមិនអាចប្រើផ្នែកខ្លះនៃគេហទំព័ររបស់យើងបាន។' : 'We use cookies and similar tracking technologies to track activity on our website and store certain information. You can set your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our website.'; ?>
                    </p>
                </div>

                <!-- Section 9: Changes to Policy -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-pink-100 text-pink-600"><i class="fas fa-rotate"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៩. ការផ្លាស់ប្តូរគោលការណ៍' : '9. Changes to This Policy'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'យើងរក្សាសិទ្ធិក្នុងការធ្វើបច្ចុប្បន្នភាព ឬផ្លាស់ប្តូរគោលការណ៍ឯកជនភាពនេះនៅពេលណាមួយ។ ការផ្លាស់ប្តូរនឹងត្រូវបានបង្ហោះនៅលើទំព័រនេះ ហើយកាលបរិច្ឆេទចូលជាធរមាននឹងត្រូវបានធ្វើបច្ចុប្បន្នភាព។ យើងសូមណែនាំឱ្យអ្នកពិនិត្យមើលគោលការណ៍នេះជាប្រចាំសម្រាប់ការផ្លាស់ប្តូរណាមួយ។' : 'We reserve the right to update or change this Privacy Policy at any time. Changes will be posted on this page and the effective date will be updated. We recommend that you periodically review this policy for any changes.'; ?>
                    </p>
                </div>

                <!-- Section 10: Contact -->
                <div class="policy-section">
                    <h2>
                        <span class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-envelope"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '១០. ទំនាក់ទំនង' : '10. Contact Us'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'ប្រសិនបើអ្នកមានសំណួរ ឬកង្វល់អំពីគោលការណ៍ឯកជនភាពនេះ សូមទាក់ទងមកយើងតាមរយៈ៖' : 'If you have any questions or concerns about this Privacy Policy, please contact us at:'; ?>
                    </p>
                    <div class="highlight-box mt-4">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-building text-blue-600 w-6 text-center"></i>
                                <span class="text-gray-700"><strong><?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?></strong></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-map-marker-alt text-blue-600 w-6 text-center"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars(getSetting('company_address', 'Phnom Penh, Cambodia')); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-envelope text-blue-600 w-6 text-center"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars(getSetting('company_email', 'info@kouprey.com')); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-phone text-blue-600 w-6 text-center"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars(getSetting('company_phone', '+855 96 999 999')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="mt-auto footer-dark-coffee py-12">
        <div class="max-w-6xl mx-auto px-4 md:px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center mb-4">
                        <img src="https://i.ibb.co/gLZY6fQr/Untitled-1-Recovered.png" alt="KouPrey Logo" class="h-8 w-auto mr-3">
                        <span class="text-xl font-bold text-yellow-400"><?php echo htmlspecialchars(getSetting('company_name', 'KouPrey Coffee')); ?></span>
                    </div>
                    <p class="text-gray-300 mb-4 leading-relaxed"><?php echo htmlspecialchars(getSetting('site_description', 'Premium coffee beans and sustainable brewing solutions')); ?></p>
                    <div class="space-y-2">
                        <div class="flex items-start"><i class="fas fa-map-marker-alt text-yellow-400 mt-1 mr-3"></i><span class="text-gray-300"><?php echo nl2br(htmlspecialchars(getSetting('company_address', 'Phnom Penh, Cambodia'))); ?></span></div>
                        <div class="flex items-center"><i class="fas fa-phone text-yellow-400 mr-3"></i><span class="text-gray-300"><?php echo htmlspecialchars(getSetting('company_phone', '+855 12 345 678')); ?></span></div>
                        <div class="flex items-center"><i class="fas fa-envelope text-yellow-400 mr-3"></i><span class="text-gray-300"><?php echo htmlspecialchars(getSetting('company_email', 'info@kouprey.com')); ?></span></div>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-white"><?php echo htmlspecialchars(getSetting('footer_quick_links', 'Quick Links')); ?></h3>
                    <ul class="space-y-2">
                        <li><a href="product.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_home', 'Home')); ?></a></li>
                        <li><a href="product.php#products" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_products', 'Products')); ?></a></li>
                        <li><a href="about.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_about_us', 'About Us')); ?></a></li>
                        <li><a href="reviews.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_reviews', 'Reviews')); ?></a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-white"><?php echo getSetting('social_banner_text', 'Connect With Us'); ?></h3>
                    <div class="flex space-x-4 mb-6">
                        <?php if (getSetting('enable_social_links', '1') === '1'): ?>
                            <?php if (getSetting('social_facebook')): ?><a href="<?php echo htmlspecialchars(getSetting('social_facebook')); ?>" target="_blank" class="text-gray-300 hover:text-blue-400 transition-colors"><i class="fab fa-facebook-f text-xl"></i></a><?php endif; ?>
                            <?php if (getSetting('social_instagram')): ?><a href="<?php echo htmlspecialchars(getSetting('social_instagram')); ?>" target="_blank" class="text-gray-300 hover:text-pink-400 transition-colors"><i class="fab fa-instagram text-xl"></i></a><?php endif; ?>
                            <?php if (getSetting('social_tiktok')): ?><a href="<?php echo htmlspecialchars(getSetting('social_tiktok')); ?>" target="_blank" class="text-gray-300 hover:text-pink-400 transition-colors"><i class="fab fa-tiktok text-xl"></i></a><?php endif; ?>
                            <?php if (getSetting('social_telegram')): ?><a href="<?php echo htmlspecialchars(getSetting('social_telegram')); ?>" target="_blank" class="text-gray-300 hover:text-blue-500 transition-colors"><i class="fab fa-telegram-plane text-xl"></i></a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (getSetting('enable_newsletter', '1') === '1'): ?>
                        <div>
                            <p class="text-gray-300 text-sm mb-2"><?php echo htmlspecialchars(getSetting('newsletter_title', 'Stay Updated')); ?></p>
                            <div class="flex">
                                <input type="email" placeholder="<?php echo htmlspecialchars(getSetting('footer_enter_email', 'Enter your email')); ?>" class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:border-yellow-400">
                                <button class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-black font-medium rounded-r-lg transition-colors"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-6 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 text-sm"><?php echo htmlspecialchars(getSetting('footer_text', '© ' . date('Y') . ' KouPrey. All rights reserved.')); ?></p>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="privacy_policy.php" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_privacy_policy', 'Privacy Policy')); ?></a>
                    <a href="terms_of_service.php" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_terms_of_service', 'Terms of Service')); ?></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Search Modal -->
    <div id="searchModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="modal-content bg-white max-w-2xl w-full max-h-[90vh] overflow-hidden relative rounded-2xl shadow-2xl">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center"><i class="fas fa-search text-blue-500 mr-2"></i>Search Products</h3>
                    <button id="closeSearchModal" class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="border-b border-gray-100 p-2">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="<?php echo htmlspecialchars(getSetting('search_placeholder', 'Search for products...')); ?>" class="w-full px-4 py-3 pl-12 border-0 focus:outline-none focus:ring-0 text-lg">
                        <svg class="w-5 h-5 absolute left-3 top-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>
                <div class="overflow-y-auto max-h-[calc(90vh-200px)] p-2">
                    <div id="searchResults" class="space-y-2"></div>
                    <div id="noResults" class="text-center text-gray-500 hidden py-12">
                        <i class="fas fa-search-minus text-4xl mb-4 block opacity-20"></i>
                        <p class="text-lg"><?php echo htmlspecialchars(getSetting('no_results', 'No products found matching your search.')); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const searchProductsData = <?php echo json_encode($searchProducts); ?>;
    </script>
    <script>
        const searchModal = document.getElementById('searchModal');
        const searchButton = document.getElementById('searchButton');
        const closeSearchModalBtn = document.getElementById('closeSearchModal');
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const noResults = document.getElementById('noResults');

        function showSearchModal() { searchModal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; searchInput.focus(); }
        function hideSearchModal() { searchModal.classList.add('hidden'); document.body.style.overflow = 'auto'; searchInput.value = ''; searchResults.innerHTML = ''; noResults.classList.add('hidden'); }
        function containsKhmer(text) { return /[\u1780-\u17FF\u19E0-\u19FF\u1A00-\u1A1F]/.test(text); }

        function getDisplayProduct(searchProduct, query) {
            const isKhmerQuery = containsKhmer(query);
            const preferredLang = isKhmerQuery ? 'km' : 'en';
            if (searchProduct.languages && searchProduct.languages[preferredLang]) return searchProduct.languages[preferredLang];
            const currentLang = '<?php echo $currentLanguage; ?>';
            if (searchProduct.languages && searchProduct.languages[currentLang]) return searchProduct.languages[currentLang];
            if (searchProduct.languages && searchProduct.languages['en']) return searchProduct.languages['en'];
            if (searchProduct.languages) return Object.values(searchProduct.languages)[0];
            return searchProduct;
        }

        function performSearch(query) {
            const filtered = searchProductsData.filter(p => p.all_names.toLowerCase().includes(query.toLowerCase()) || p.all_descriptions.toLowerCase().includes(query.toLowerCase()));
            const display = filtered.map(p => getDisplayProduct(p, query));
            displaySearchResults(display);
        }

        function displaySearchResults(products) {
            searchResults.innerHTML = '';
            if (products.length === 0) { noResults.classList.remove('hidden'); return; }
            noResults.classList.add('hidden');
            products.forEach(product => {
                const div = document.createElement('div');
                div.className = 'flex items-center p-4 border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors';
                div.onclick = () => { hideSearchModal(); window.location.href = 'product_detail.php?base_id=' + product.base_product_id; };
                div.innerHTML = `<img src="${product.image || '/kouprey/public/assets/images/product-medium.png'}" alt="${product.name}" class="w-12 h-12 object-contain mr-4 rounded"><div class="flex-1"><h4 class="font-semibold text-gray-800">${product.name}</h4><p class="text-sm text-gray-600">$${parseFloat(product.price).toFixed(2)}</p></div><svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>`;
                searchResults.appendChild(div);
            });
        }

        searchButton.addEventListener('click', showSearchModal);
        closeSearchModalBtn.addEventListener('click', hideSearchModal);
        searchModal.addEventListener('click', e => { if (e.target === searchModal) hideSearchModal(); });
        searchInput.addEventListener('input', e => { const q = e.target.value.trim(); if (q.length > 0) performSearch(q); else { searchResults.innerHTML = ''; noResults.classList.add('hidden'); } });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !searchModal.classList.contains('hidden')) hideSearchModal(); });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 10) header.classList.add('scrolled'); else header.classList.remove('scrolled');
        });
    </script>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-6 left-6 right-6 bg-white/40 backdrop-blur-[30px] border border-white/40 shadow-[0_20px_50px_rgba(0,0,0,0.1)] rounded-[2.5rem] z-40 pb-safe px-2 overflow-hidden">
        <div class="flex items-center justify-around py-1">
            <a href="product.php#products" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
                <i class="fas fa-mug-hot text-xl mb-1 <?php echo ($current_page == 'product.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
                <span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'product.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_product', 'Products')); ?></span>
            </a>
            <a href="features.php" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
                <i class="fas fa-bolt text-xl mb-1 <?php echo ($current_page == 'features.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
                <span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'features.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></span>
            </a>
            <a href="reviews.php" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
                <i class="fas fa-star text-xl mb-1 <?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
                <span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'reviews.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></span>
            </a>
            <a href="about.php" class="relative group flex flex-col items-center justify-center py-4 px-2 min-w-0 flex-1 transition-all">
                <i class="fas fa-user text-xl mb-1 <?php echo ($current_page == 'about.php') ? 'text-[#92adc5] scale-110' : 'text-gray-400 group-hover:text-gray-600'; ?> transition-all duration-300"></i>
                <span class="text-[10px] font-bold tracking-widest <?php echo ($current_page == 'about.php') ? 'text-[#92adc5]' : 'text-gray-400'; ?> text-center uppercase"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></span>
            </a>
        </div>
    </nav>
</body>
</html>
