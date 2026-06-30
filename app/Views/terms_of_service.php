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

// Get terms of service content from settings
$termsContent = getSetting('terms_of_service', '');
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars(getCurrentLanguage()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars(getSetting('terms_of_service_title', 'Terms of Service')); ?> - <?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?></title>
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

        /* Terms content styling */
        .terms-section {
            border-left: 4px solid #10B981;
            padding-left: 1.5rem;
            margin-bottom: 2rem;
            transition: border-color 0.3s ease;
        }
        .terms-section:hover {
            border-left-color: #059669;
        }
        .terms-section h2 {
            color: #064E3B;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .terms-section h2 .icon-circle {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .terms-section p, .terms-section li {
            color: #4B5563;
            line-height: 1.9;
            font-size: 1.05rem;
        }
        .terms-section ul {
            list-style: none;
            padding-left: 0;
        }
        .terms-section ul li {
            padding: 0.5rem 0;
            padding-left: 1.75rem;
            position: relative;
        }
        .terms-section ul li::before {
            content: '›';
            position: absolute;
            left: 0;
            color: #10B981;
            font-weight: bold;
            font-size: 1.3rem;
            line-height: 1.4;
        }

        /* Rich Text Editor Content Styles */
        .content-section h1 { font-size: 1.8rem; color: #064E3B; margin: 1.5rem 0 1rem; font-weight: 700; }
        .content-section h2 { font-size: 1.5rem; color: #064E3B; margin: 1.5rem 0 0.8rem; font-weight: 700; }
        .content-section h3 { font-size: 1.25rem; color: #374151; margin: 1.2rem 0 0.6rem; font-weight: 600; }
        .content-section h4 { font-size: 1.1rem; color: #374151; margin: 1rem 0 0.5rem; font-weight: 600; }
        .content-section p { color: #4B5563; line-height: 1.9; margin-bottom: 1rem; }
        .content-section ul, .content-section ol { margin: 0.5rem 0 1rem 1.5rem; }
        .content-section li { padding: 0.25rem 0; color: #4B5563; line-height: 1.8; }
        .content-section img { max-width: 100%; height: auto; border-radius: 8px; margin: 1rem 0; }
        .content-section table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
        .content-section table td, .content-section table th { border: 1px solid #D1D5DB; padding: 8px 12px; }
        .content-section table th { background: #F3F4F6; font-weight: 600; }
        .content-section blockquote { border-left: 4px solid #10B981; padding-left: 1rem; margin: 1rem 0; color: #6B7280; font-style: italic; }
        .content-section a { color: #10B981; text-decoration: underline; }
        .content-section strong { font-weight: 700; }
        .content-section em { font-style: italic; }
        .content-section .fa, .content-section .fas, .content-section .far, .content-section .fab { margin-right: 0.3rem; }

        .highlight-box {
            background: linear-gradient(135deg, #ECFDF5 0%, #F0FDF4 100%);
            border: 1px solid #A7F3D0;
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
            .terms-section { border-left-width: 3px; padding-left: 1rem; }
            .terms-section h2 { font-size: 1.25rem; }
            .terms-section p, .terms-section li { font-size: 0.95rem; }
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
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-6">
                <i class="fas fa-file-contract text-green-600 text-3xl"></i>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-3">
                <?php echo htmlspecialchars(getSetting('terms_of_service_title', 'Terms of Service')); ?>
            </h1>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                <?php echo htmlspecialchars(getSetting('terms_of_service_desc', 'Please read these Terms of Service carefully before using our services.')); ?>
            </p>
            <div class="w-20 h-1 bg-gradient-to-r from-green-400 to-teal-500 mx-auto mt-4 rounded-full"></div>
        </div>

        <?php if (!empty($termsContent)): ?>
            <!-- Dynamic content from admin settings -->
            <div class="prose max-w-none terms-section content-section">
                <?php echo $termsContent; ?>
            </div>
        <?php else: ?>
            <!-- Default structured Khmer terms of service -->
            <div class="space-y-6">
                <!-- Section: Effective Date -->
                <div class="text-center mb-8">
                    <span class="inline-block bg-green-50 text-green-700 px-5 py-2 rounded-full text-sm font-semibold">
                        <i class="far fa-calendar-check mr-2"></i>
                        <?php echo getCurrentLanguage() === 'km' ? 'ចូលជាធរមាន៖ ថ្ងៃទី ០១ ខែមករា ឆ្នាំ២០២៥' : 'Effective Date: January 01, 2025'; ?>
                    </span>
                </div>

                <!-- Section 1: Acceptance -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-green-100 text-green-600"><i class="fas fa-check-circle"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '១. ការយល់ព្រមចំពោះលក្ខខណ្ឌ' : '1. Acceptance of Terms'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'តាមរយៈការចូលប្រើប្រាស់គេហទំព័រ KouPrey អ្នកយល់ព្រមទទួលយក និងអនុវត្តតាមលក្ខខណ្ឌប្រើប្រាស់ទាំងនេះ។ ប្រសិនបើអ្នកមិនយល់ព្រមចំពោះលក្ខខណ្ឌណាមួយ សូមកុំប្រើប្រាស់គេហទំព័រនេះ។ យើងរក្សាសិទ្ធិក្នុងការផ្លាស់ប្តូរលក្ខខណ្ឌទាំងនេះនៅពេលណាមួយ ហើយការបន្តប្រើប្រាស់គេហទំព័របន្ទាប់ពីការផ្លាស់ប្តូរត្រូវបានចាត់ទុកថាជាការយល់ព្រមចំពោះលក្ខខណ្ឌថ្មី។' : 'By accessing and using the KouPrey website, you agree to accept and comply with these Terms of Service. If you do not agree to any of these terms, please do not use this website. We reserve the right to change these terms at any time, and continued use of the website after changes constitutes acceptance of the new terms.'; ?>
                    </p>
                </div>

                <!-- Section 2: Use of Website -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-emerald-100 text-emerald-600"><i class="fas fa-globe"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '២. ការប្រើប្រាស់គេហទំព័រ' : '2. Use of Website'; ?>
                    </h2>
                    <p><?php echo getCurrentLanguage() === 'km' ? 'នៅពេលប្រើប្រាស់គេហទំព័រនេះ អ្នកយល់ព្រមថា៖' : 'When using this website, you agree that:'; ?></p>
                    <ul>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'អ្នកនឹងមិនប្រើប្រាស់គេហទំព័រនេះសម្រាប់គោលបំណងខុសច្បាប់ ឬគ្មានការអនុញ្ញាតឡើយ' : 'You will not use this website for any unlawful or unauthorized purpose'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'អ្នកនឹងមិនរំខាន ឬបង្កការខូចខាតដល់គេហទំព័រ ឬម៉ាស៊ីនមេរបស់យើងឡើយ' : 'You will not disrupt or damage the website or our servers'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'អ្នកនឹងមិនព្យាយាមចូលប្រើផ្នែកដែលមានការរឹតបន្តឹងនៃគេហទំព័រដោយគ្មានការអនុញ្ញាតឡើយ' : 'You will not attempt to access restricted areas of the website without authorization'; ?></li>
                        <li><?php echo getCurrentLanguage() === 'km' ? 'អ្នកទទួលខុសត្រូវចំពោះការរក្សាការសម្ងាត់នៃព័ត៌មានគណនីរបស់អ្នក' : 'You are responsible for maintaining the confidentiality of your account information'; ?></li>
                    </ul>
                </div>

                <!-- Section 3: Products & Orders -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-teal-100 text-teal-600"><i class="fas fa-box"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៣. ផលិតផល និងការបញ្ជាទិញ' : '3. Products & Orders'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'យើងខិតខំប្រឹងប្រែងដើម្បីបង្ហាញព័ត៌មានផលិតផលឱ្យបានត្រឹមត្រូវ រួមទាំងរូបភាព ការពិពណ៌នា និងតម្លៃ។ ទោះយ៉ាងណា យើងមិនធានាថាព័ត៌មានទាំងអស់ត្រឹមត្រូវ ១០០% ឡើយ។ យើងរក្សាសិទ្ធិក្នុងការកែតម្រូវកំហុស ផ្លាស់ប្តូរតម្លៃ ឬបញ្ឈប់ផលិតផលដោយមិនចាំបាច់ជូនដំណឹងជាមុន។ ការបញ្ជាទិញទាំងអស់ត្រូវស្ថិតនៅក្រោមការទទួលយករបស់យើង ហើយយើងរក្សាសិទ្ធិក្នុងការបដិសេធ ឬលុបចោលការបញ្ជាទិញណាមួយ។' : 'We strive to display product information accurately, including images, descriptions, and prices. However, we do not guarantee that all information is 100% accurate. We reserve the right to correct errors, change prices, or discontinue products without prior notice. All orders are subject to our acceptance, and we reserve the right to refuse or cancel any order.'; ?>
                    </p>
                </div>

                <!-- Section 4: Payment -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-blue-100 text-blue-600"><i class="fas fa-credit-card"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៤. ការទូទាត់' : '4. Payment'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'រាល់ការទូទាត់ត្រូវធ្វើឡើងតាមរយៈវិធីសាស្រ្តដែលបានកំណត់នៅលើគេហទំព័ររបស់យើង។ អ្នកយល់ព្រមផ្តល់ព័ត៌មានទូទាត់ត្រឹមត្រូវ និងពេញលេញ។ យើងមិនរក្សាទុកព័ត៌មានកាតឥណទានពេញលេញនៅលើម៉ាស៊ីនមេរបស់យើងឡើយ។ រាល់ប្រតិបត្តិការទូទាត់ត្រូវបានដំណើរការតាមរយៈសេវាកម្មទូទាត់ដែលមានសុវត្ថិភាពរបស់ភាគីទីបី។' : 'All payments must be made through the methods specified on our website. You agree to provide accurate and complete payment information. We do not store full credit card information on our servers. All payment transactions are processed through secure third-party payment services.'; ?>
                    </p>
                </div>

                <!-- Section 5: Intellectual Property -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-purple-100 text-purple-600"><i class="fas fa-copyright"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៥. កម្មសិទ្ធិបញ្ញា' : '5. Intellectual Property'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'ខ្លឹមសារទាំងអស់នៅលើគេហទំព័រនេះ រួមទាំងអត្ថបទ ក្រាហ្វិក ឡូហ្គោ រូបភាព និងកម្មវិធី គឺជាកម្មសិទ្ធិរបស់ KouPrey ឬអ្នកផ្គត់ផ្គង់មាតិការបស់យើង ហើយត្រូវបានការពារដោយច្បាប់កម្មសិទ្ធិបញ្ញា។ អ្នកមិនត្រូវបានអនុញ្ញាតឱ្យថតចម្លង ផលិតឡើងវិញ ចែកចាយ ឬបង្កើតស្នាដៃដេរីវេពីខ្លឹមសារណាមួយដោយគ្មានការអនុញ្ញាតជាលាយលក្ខណ៍អក្សរពីយើងឡើយ។' : 'All content on this website, including text, graphics, logos, images, and software, is the property of KouPrey or our content suppliers and is protected by intellectual property laws. You are not permitted to copy, reproduce, distribute, or create derivative works from any content without our express written permission.'; ?>
                    </p>
                </div>

                <!-- Section 6: Limitation of Liability -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-orange-100 text-orange-600"><i class="fas fa-scale-balanced"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៦. ដែនកំណត់នៃការទទួលខុសត្រូវ' : '6. Limitation of Liability'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'KouPrey មិនទទួលខុសត្រូវចំពោះការខូចខាតដោយផ្ទាល់ ដោយប្រយោល ដោយចៃដន្យ ឬជាលទ្ធផលដែលកើតឡើងពីការប្រើប្រាស់ ឬអសមត្ថភាពក្នុងការប្រើប្រាស់គេហទំព័រ ឬផលិតផលរបស់យើងឡើយ។ យើងផ្តល់គេហទំព័រនេះ "ដូចដែលមាន" ដោយគ្មានការធានាណាមួយ ទោះបញ្ជាក់ច្បាស់លាស់ ឬដោយប្រយោលក៏ដោយ។' : 'KouPrey shall not be liable for any direct, indirect, incidental, or consequential damages arising from the use or inability to use our website or products. We provide this website "as is" without any warranties, whether express or implied.'; ?>
                    </p>
                </div>

                <!-- Section 7: User Accounts -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-pink-100 text-pink-600"><i class="fas fa-user-lock"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៧. គណនីអ្នកប្រើប្រាស់' : '7. User Accounts'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'នៅពេលអ្នកបង្កើតគណនីជាមួយយើង អ្នកត្រូវតែផ្តល់ព័ត៌មានត្រឹមត្រូវ ពេញលេញ និងទាន់សម័យ។ អ្នកទទួលខុសត្រូវចំពោះសកម្មភាពទាំងអស់ដែលកើតឡើងក្រោមគណនីរបស់អ្នក។ យើងរក្សាសិទ្ធិក្នុងការបិទ ឬផ្អាកគណនីណាមួយដែលបំពានលក្ខខណ្ឌទាំងនេះ។' : 'When you create an account with us, you must provide accurate, complete, and up-to-date information. You are responsible for all activities that occur under your account. We reserve the right to close or suspend any account that violates these terms.'; ?>
                    </p>
                </div>

                <!-- Section 8: Privacy -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-indigo-100 text-indigo-600"><i class="fas fa-shield-halved"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៨. ឯកជនភាព' : '8. Privacy'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'ការប្រើប្រាស់គេហទំព័ររបស់យើងក៏ស្ថិតនៅក្រោមគោលការណ៍ឯកជនភាពរបស់យើងផងដែរ។ សូមអានគោលការណ៍ឯកជនភាពរបស់យើងសម្រាប់ព័ត៌មានបន្ថែមអំពីរបៀបដែលយើងប្រមូល ប្រើប្រាស់ និងការពារព័ត៌មានផ្ទាល់ខ្លួនរបស់អ្នក។' : 'Your use of our website is also subject to our Privacy Policy. Please read our Privacy Policy for more information about how we collect, use, and protect your personal information.'; ?>
                    </p>
                    <div class="highlight-box mt-4">
                        <p class="text-gray-700 mb-2">
                            <i class="fas fa-info-circle text-green-600 mr-2"></i>
                            <?php echo getCurrentLanguage() === 'km' ? 'សូមអានគោលការណ៍ឯកជនភាពរបស់យើងសម្រាប់ព័ត៌មានលម្អិត៖' : 'Please review our Privacy Policy for detailed information:'; ?>
                        </p>
                        <a href="privacy_policy.php" class="inline-flex items-center text-green-600 hover:text-green-700 font-semibold transition-colors">
                            <i class="fas fa-arrow-right mr-2"></i>
                            <?php echo htmlspecialchars(getSetting('footer_privacy_policy', 'Privacy Policy')); ?>
                        </a>
                    </div>
                </div>

                <!-- Section 9: Termination -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-red-100 text-red-600"><i class="fas fa-ban"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '៩. ការបញ្ចប់' : '9. Termination'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'យើងរក្សាសិទ្ធិក្នុងការបញ្ចប់ ឬផ្អាកការចូលប្រើគេហទំព័ររបស់អ្នកដោយគ្មានការជូនដំណឹងជាមុន ប្រសិនបើអ្នកបំពានលក្ខខណ្ឌប្រើប្រាស់ទាំងនេះ។ នៅពេលបញ្ចប់ សិទ្ធិរបស់អ្នកក្នុងការប្រើប្រាស់គេហទំព័រនឹងត្រូវបញ្ចប់ភ្លាមៗ។' : 'We reserve the right to terminate or suspend your access to the website without prior notice if you violate these Terms of Service. Upon termination, your right to use the website will cease immediately.'; ?>
                    </p>
                </div>

                <!-- Section 10: Governing Law -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-gray-100 text-gray-600"><i class="fas fa-gavel"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '១០. ច្បាប់គ្រប់គ្រង' : '10. Governing Law'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់ទាំងនេះត្រូវបានគ្រប់គ្រង និងបកស្រាយដោយអនុលោមតាមច្បាប់នៃព្រះរាជាណាចក្រកម្ពុជា។ រាល់វិវាទដែលកើតចេញពីលក្ខខណ្ឌទាំងនេះនឹងត្រូវដោះស្រាយនៅក្នុងតុលាការនៃព្រះរាជាណាចក្រកម្ពុជា។' : 'These Terms of Service shall be governed and construed in accordance with the laws of the Kingdom of Cambodia. Any disputes arising from these terms shall be resolved in the courts of the Kingdom of Cambodia.'; ?>
                    </p>
                </div>

                <!-- Section 11: Contact -->
                <div class="terms-section">
                    <h2>
                        <span class="icon-circle bg-green-100 text-green-600"><i class="fas fa-envelope"></i></span>
                        <?php echo getCurrentLanguage() === 'km' ? '១១. ទំនាក់ទំនង' : '11. Contact Us'; ?>
                    </h2>
                    <p>
                        <?php echo getCurrentLanguage() === 'km' ? 'ប្រសិនបើអ្នកមានសំណួរ ឬកង្វល់អំពីលក្ខខណ្ឌប្រើប្រាស់ទាំងនេះ សូមទាក់ទងមកយើងតាមរយៈ៖' : 'If you have any questions or concerns about these Terms of Service, please contact us at:'; ?>
                    </p>
                    <div class="highlight-box mt-4">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-building text-green-600 w-6 text-center"></i>
                                <span class="text-gray-700"><strong><?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?></strong></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-map-marker-alt text-green-600 w-6 text-center"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars(getSetting('company_address', 'Phnom Penh, Cambodia')); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-envelope text-green-600 w-6 text-center"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars(getSetting('company_email', 'info@kouprey.com')); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-phone text-green-600 w-6 text-center"></i>
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
                    <h3 class="text-lg font-semibold mb-4 text-white"><?php echo htmlspecialchars(getSetting('footer_connect_with_us', 'Connect With Us')); ?></h3>
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
                    <h3 class="text-xl font-bold text-gray-800 flex items-center"><i class="fas fa-search text-green-500 mr-2"></i>Search Products</h3>
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
