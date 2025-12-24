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

// Fetch about content from database
$stmt = $pdo->query("SELECT * FROM about ORDER BY id DESC LIMIT 1");
$about = $stmt->fetch();

// Fetch products for search functionality
$productStmt = $pdo->prepare("SELECT * FROM products WHERE language = ? ORDER BY featured DESC, best_seller DESC, id DESC");
$productStmt->execute([getCurrentLanguage()]);
$products = $productStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>KouPrey Coffee</title>
	<link rel="icon" type="image/png" href="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Freeman&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
	.font-freeman {
		font-family: 'Freeman', serif;
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

	/* Mobile optimizations */
	@media (max-width: 768px) {
		#searchModal .modal-content {
			margin: 1rem;
			max-width: calc(100vw - 2rem);
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
		button {
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

	/* iOS 18 Modal Styles */
	.modal-content {
		background: rgba(255, 255, 255, 0.8);
		backdrop-filter: blur(40px);
		-webkit-backdrop-filter: blur(40px);
		border-radius: 28px;
		border: 1px solid rgba(255, 255, 255, 0.2);
		box-shadow:
			0 8px 32px rgba(0, 0, 0, 0.12),
			0 2px 8px rgba(0, 0, 0, 0.08),
			inset 0 1px 0 rgba(255, 255, 255, 0.4);
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

	/* Modal backdrop with iOS 18 blur */
	#searchModal:not(.hidden) {
		background: rgba(0, 0, 0, 0.3);
		backdrop-filter: blur(20px);
		-webkit-backdrop-filter: blur(20px);
		animation: backdropFadeIn 0.3s ease-out;
	}

	@keyframes backdropFadeIn {
		from {
			opacity: 0;
		}
		to {
			opacity: 1;
		}
	}

	/* Enhanced modal content styling */
	.modal-content h3 {
		font-weight: 700;
		letter-spacing: -0.02em;
	}

	/* iOS-style button styling */
	.modal-content button {
		transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	}

	.modal-content button:hover {
		transform: scale(1.02);
	}

	/* Enhanced input styling for iOS */
	.modal-content input {
		border-radius: 12px;
		border: 1px solid rgba(0, 0, 0, 0.1);
		background: rgba(255, 255, 255, 0.8);
		backdrop-filter: blur(10px);
		transition: all 0.2s ease;
	}

	.modal-content input:focus {
		border-color: rgba(255, 193, 7, 0.5);
		box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
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
<body class="bg-gray-50 text-gray-800 font-freeman min-h-screen pb-20 flex flex-col">
	<header class="bg-white shadow-sm border-b border-gray-200 px-4 py-3 md:px-6 md:py-4 sticky top-0 z-50">
		<div class="flex items-center justify-between max-w-6xl mx-auto">
			<!-- Logo -->
			<div class="flex items-center">
				<a href="/" class="flex items-center">
					<img src="https://i.ibb.co/KJNYks2/Logo-Koprey-Photoroom.png" alt="<?php echo htmlspecialchars(getSetting('company_name', 'KouPrey')); ?>" class="h-8 w-auto md:h-10">
					<span class="text-lg md:text-xl font-bold ml-2 text-gray-800">KouPrey</span>
				</a>
			</div>
			
			<!-- Desktop Navigation -->
			<nav class="hidden md:flex space-x-8">
				<a href="product.php" class="<?php echo ($current_page == 'product.php') ? 'text-yellow-600 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> transition-colors"><?php echo htmlspecialchars(getSetting('nav_product', 'Product')); ?></a>
				<a href="features.php" class="<?php echo ($current_page == 'features.php') ? 'text-yellow-600 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> transition-colors"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></a>
				<a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'text-yellow-600 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> transition-colors"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></a>
				<a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'text-yellow-600 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> transition-colors"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></a>
			</nav>
			
			<!-- Mobile Actions -->
			<div class="flex items-center space-x-3">
				<!-- Language Switcher -->
				<button onclick="changeLanguage('<?php echo getCurrentLanguage() === 'en' ? 'km' : 'en'; ?>')" class="flex items-center space-x-1 text-sm bg-transparent border-none outline-none cursor-pointer hover:bg-gray-100 rounded px-2 py-1 transition-colors" title="Switch Language">
					<img src="<?php echo getCurrentLanguage() === 'en' ? 'https://img.freepik.com/premium-photo/flag-great-britain_406939-4606.jpg?semt=ais_hybrid&w=740&q=80' : 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/83/Flag_of_Cambodia.svg/2560px-Flag_of_Cambodia.svg.png'; ?>" 
						 alt="<?php echo getCurrentLanguage() === 'en' ? 'English' : 'Khmer'; ?>" 
						 class="w-6 h-4 object-cover rounded">
					<span class="font-medium"><?php echo getCurrentLanguage() === 'en' ? 'EN' : 'KM'; ?></span>
				</button>
				<button id="searchButton" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full transition-colors" title="Search">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
					</svg>
				</button>
			</div>
		</div>
	</header>

	<main class="max-w-6xl mx-auto px-4 md:px-6 py-8 md:py-12">
		<section class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
			<div>
				<h1 class="text-3xl md:text-4xl font-extrabold"><?php echo htmlspecialchars($about['title'] ?? 'About Us'); ?></h1>
				<p class="mt-4 text-gray-600"><?php echo nl2br(htmlspecialchars($about['content'] ?? 'This is how KouPrey was born. Having experienced the cleanest, purest coffee on a mountainside in Peru, we struggled to find something like it after coming home — so we made it ourselves.')); ?></p>
				<a href="#purpose" class="inline-block mt-6 bg-yellow-400 text-white px-5 py-3 rounded"><?php echo htmlspecialchars(getSetting('about_explore_button', 'Explore')); ?></a>
			</div>
			<div class="flex justify-center">
				<?php if (!empty($about['hero_image'])): ?>
					<img src="<?php echo htmlspecialchars($about['hero_image']); ?>" alt="About hero" class="w-full max-w-md rounded-lg shadow">
				<?php else: ?>
					<img src="/kouprey/public/assets/images/about-hero.jpg" alt="About hero" class="w-full max-w-md rounded-lg shadow">
				<?php endif; ?>
			</div>
		</section>

		<section id="purpose" class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
			<div>
				<?php if (!empty($about['person_image'])): ?>
					<img src="<?php echo htmlspecialchars($about['person_image']); ?>" alt="Person 1" class="w-full h-72 object-cover rounded-lg shadow">
				<?php else: ?>
					<img src="/kouprey/public/assets/images/person-1.jpg" alt="Person 1" class="w-full h-72 object-cover rounded-lg shadow">
				<?php endif; ?>
			</div>
			<div>
				<h3 class="text-2xl font-bold"><?php echo htmlspecialchars(getSetting('about_purpose_title', 'Our Purpose')); ?></h3>
				<p class="mt-4 text-gray-600"><?php echo nl2br(htmlspecialchars(getSetting('about_purpose_content', 'At KouPrey we do things differently — with purpose. Our goal is simple: make 100% organic, healthy and delicious coffee accessible to as many people as possible. We\'re committed to delivering coffee that is better for you, the community, and our planet.'))); ?></p>
				<a class="inline-block mt-6 bg-yellow-400 text-white px-5 py-3 rounded"><?php echo htmlspecialchars(getSetting('about_explore_button', 'Explore')); ?></a>
			</div>
		</section>

		<section class="mt-12">
			<h3 class="text-2xl font-bold"><?php echo htmlspecialchars(getSetting('about_story_title', 'Our Story')); ?></h3>
			<p class="mt-4 text-gray-600"><?php echo nl2br(htmlspecialchars(getSetting('about_story_content', 'We began with a love for clean coffee and a desire to share it. Over the years we\'ve partnered with growers, refined our roasting, and expanded our blends — all while keeping quality and sustainability at the center of everything we do.'))); ?></p>
		</section>
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
						<li><a href="/kouprey/public/features.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_products', 'Products')); ?></a></li>
						<li><a href="/kouprey/public/about.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_about', 'About Us')); ?></a></li>
						<li><a href="/kouprey/public/reviews.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_reviews', 'Reviews')); ?></a></li>
						<li><a href="/kouprey/admin/login.php" class="text-gray-300 hover:text-yellow-400 transition-colors"><?php echo htmlspecialchars(getSetting('footer_admin', 'Admin')); ?></a></li>
					</ul>
				</div>

				<!-- Social & Newsletter -->
				<div>
					<h3 class="text-lg font-semibold mb-4 text-white">Connect With Us</h3>
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
							<?php if (getSetting('social_twitter')): ?>
								<a href="<?php echo htmlspecialchars(getSetting('social_twitter')); ?>" target="_blank" class="text-gray-300 hover:text-blue-400 transition-colors">
									<i class="fab fa-twitter text-xl"></i>
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
							<p class="text-gray-300 text-sm mb-2"><?php echo htmlspecialchars(getSetting('newsletter_title', 'Stay Updated')); ?></p>
							<div class="flex">
								<input type="email" placeholder="Enter your email" class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:border-yellow-400">
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
					<a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors">Privacy Policy</a>
					<a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors">Terms of Service</a>
					<a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors">Contact Us</a>
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
						<i class="fas fa-search text-green-500 mr-2"></i>Search Products
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
						<input type="text" id="searchInput" placeholder="Search for products..." class="w-full px-4 py-3 pl-12 border-0 focus:outline-none focus:ring-0">
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
					<div id="noResults" class="text-center text-gray-500 hidden">
						<p class="text-lg">No products found matching your search.</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Embed product data for JavaScript -->
	<script>
		const productsData = <?php echo json_encode($products); ?>;
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

		// Function to perform search
		function performSearch(query) {
			const filteredProducts = productsData.filter(product => {
				const nameMatch = product.name.toLowerCase().includes(query.toLowerCase());
				const descMatch = product.description.toLowerCase().includes(query.toLowerCase());
				return nameMatch || descMatch;
			});

			displaySearchResults(filteredProducts);
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
		let lastScrollTop = 0;
		const header = document.querySelector('header');

		window.addEventListener('scroll', function() {
			const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

			if (scrollTop > 50) {
				// Scrolled down more than 50px - make background transparent
				header.classList.add('bg-transparent', 'backdrop-blur-md');
				header.classList.remove('bg-white');
			} else {
				// At top - restore solid background
				header.classList.remove('bg-transparent', 'backdrop-blur-md');
				header.classList.add('bg-white');
			}

			lastScrollTop = scrollTop;
		});

	</script>

	<!-- Mobile Bottom Navigation -->
	<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-40">
		<div class="flex items-center justify-around py-2">
			<a href="product.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 <?php echo ($current_page == 'product.php') ? 'text-yellow-600' : 'text-gray-600'; ?>">
				<svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
				</svg>
				<span class="text-xs font-medium"><?php echo htmlspecialchars(getSetting('nav_product', 'Products')); ?></span>
			</a>
			<a href="features.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 <?php echo ($current_page == 'features.php') ? 'text-yellow-600' : 'text-gray-600'; ?>">
				<svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
				</svg>
				<span class="text-xs font-medium"><?php echo htmlspecialchars(getSetting('nav_features', 'Features')); ?></span>
			</a>
			<a href="reviews.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 <?php echo ($current_page == 'reviews.php') ? 'text-yellow-600' : 'text-gray-600'; ?>">
				<svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
				</svg>
				<span class="text-xs font-medium"><?php echo htmlspecialchars(getSetting('nav_reviews', 'Reviews')); ?></span>
			</a>
			<a href="about.php" class="flex flex-col items-center justify-center py-2 px-3 min-w-0 flex-1 <?php echo ($current_page == 'about.php') ? 'text-yellow-600' : 'text-gray-600'; ?>">
				<svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
				</svg>
				<span class="text-xs font-medium"><?php echo htmlspecialchars(getSetting('nav_about', 'About')); ?></span>
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
</body>
</html>
