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

// Fetch products for search functionality
$productStmt = $pdo->prepare("SELECT * FROM products WHERE language = ? ORDER BY sort_order ASC, featured DESC, best_seller DESC, id DESC");
$productStmt->execute([getCurrentLanguage()]);
$products = $productStmt->fetchAll();

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
		
		<!-- Mobile navigation -->
		<nav class="mt-4 space-x-4 md:hidden flex justify-center">
			<a href="product.php" class="<?php echo ($current_page == 'product.php') ? 'text-yellow-600 font-bold' : 'text-gray-700'; ?>">Product</a>
			<a href="features.php" class="<?php echo ($current_page == 'features.php') ? 'text-yellow-600 font-bold' : 'text-gray-600'; ?>">Features</a>
			<a href="reviews.php" class="<?php echo ($current_page == 'reviews.php') ? 'text-yellow-600 font-bold' : 'text-gray-600'; ?>">Reviews</a>
			<a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'text-yellow-600 font-bold' : 'text-gray-600'; ?>">About us</a>
		</nav>
	</header>

	<main class="max-w-6xl mx-auto px-4 md:px-6 py-8 md:py-12">
		<section class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
			<div>
				<h1 class="text-3xl md:text-4xl font-extrabold"><?php echo htmlspecialchars(getSetting('features_title', 'Features')); ?></h1>
				<p class="mt-4 text-gray-600"><?php echo nl2br(htmlspecialchars(getSetting('features_description', 'Discover what makes our coffee exceptional — from sourcing to roasting and packaging.'))); ?></p>
			</div>
			<div class="flex justify-center">
				<img src="/kouprey/public/assets/images/feature-hero.png" alt="Features" class="w-full max-w-md rounded-lg shadow">
			</div>
		</section>

		<section class="mt-10">
			<!-- New infographic layout (desktop) -->
			<div class="relative mx-auto w-full max-w-4xl flex items-center justify-center hidden md:flex">
				<div class="w-full grid grid-cols-12 gap-6 items-center">
					<!-- Left: circular icon/illustration -->
					<div class="col-span-5 flex justify-center">
						<div class="rounded-full bg-white shadow-lg p-8 flex items-center justify-center" style="width:220px; height:220px;">
							<svg width="110" height="110" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<rect x="2" y="3" width="20" height="14" rx="1.5" stroke="#374151" stroke-width="1.2" fill="#F8FAFC" />
								<rect x="8" y="18" width="8" height="1.6" rx="0.8" fill="#374151" />
								<g transform="translate(2,2)">
									<path d="M7 3l5 5" stroke="#111827" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
								</g>
							</svg>
						</div>
					</div>

					<!-- Right: numbered list -->
					<div class="col-span-7">
						<div class="space-y-6">
							<?php $__inf_colors = ['#ef4444','#8b5cf6','#06b6d4','#f59e0b','#10b981']; $__i=0; foreach ($features as $baseId => $feature): ?>
								<div class="flex items-start">
									<div class="shrink-0">
										<div class="inf-number flex items-center justify-center text-white font-bold" style="width:56px;height:56px;border-radius:9999px;background:<?php echo $__inf_colors[$__i % count($__inf_colors)]; ?>;">0<?php echo $__i+1; ?></div>
									</div>
									<div class="ml-4 bg-white rounded-full shadow p-4 flex-1" style="border-left:4px solid <?php echo $__inf_colors[$__i % count($__inf_colors)]; ?>; padding-left:1rem;">
										<div class="font-semibold text-gray-800"><?php echo htmlspecialchars($feature['title']); ?></div>
										<div class="text-sm text-gray-500 mt-1"><?php $d = $feature['description'] ?? ''; if (strlen($d) > 90) $d = substr($d,0,87) . '...'; echo htmlspecialchars($d); ?></div>
									</div>
								</div>
							<?php $__i++; endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<style>
				.monitor-box { width: 140px; height: 120px; }
				.feature-node { width: 56px; height: 56px; border-radius: 9999px; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 6px 18px rgba(15,23,42,0.06); }
				.node-icon { width:36px; height:36px; border-radius:9999px; display:flex; align-items:center; justify-content:center; }
				.node-icon.teal { background:#0ea5a0; color:white; }
				.node-icon.brown { background:#8b5e34; color:white; }
				.feature-label { position:absolute; width:140px; pointer-events:auto; transform:translate(-50%,0); background:rgba(255,255,255,0.98); padding:6px 8px; border-radius:8px; box-shadow:0 6px 18px rgba(15,23,42,0.06); }
				.feature-label .title { font-weight:600; color:#111827; text-align:center; }
				.feature-label .desc { font-size:12px; color:#6b7280; margin-top:6px; text-align:center; }
			</style>

			<script>
				const featuresData = <?php echo json_encode($features); ?>;
				function renderFeatureNodes() {
					const center = document.getElementById('featureCircle');
					if (!center) return; // skip if not present (mobile)
					const container = document.getElementById('featureNodes');
					const labels = document.getElementById('featureLabels');
					container.innerHTML = '';
					labels.innerHTML = '';
					const rect = center.getBoundingClientRect();
					const cx = rect.width / 2;
					const cy = rect.height / 2;
					const radius = Math.min(rect.width, rect.height) / 2 - 80;
					const svg = document.getElementById('featureLines');
					const svgNS = 'http://www.w3.org/2000/svg';
					svg.innerHTML = '';
					const keys = Object.keys(featuresData);
					keys.forEach((baseId, i) => {
						const feature = featuresData[baseId];
						const angle = (i / keys.length) * Math.PI * 2 - Math.PI / 2;
						const x = cx + radius * Math.cos(angle);
						const y = cy + radius * Math.sin(angle);

						// node (small colored icon circle)
						const node = document.createElement('button');
						node.className = 'feature-node absolute transform -translate-x-1/2 -translate-y-1/2 bg-white';
						node.style.left = cx + 'px';
						node.style.top = cy + 'px';
						node.style.opacity = '0';
						node.style.transform = 'translate(-50%,-50%) scale(0.7)';
						node.dataset.featureId = baseId;
						node.dataset.featureTitle = feature.title;
						node.dataset.featureDescription = feature.description;

						const iconWrap = document.createElement('div');
						iconWrap.className = 'node-icon ' + (i % 2 === 0 ? 'teal' : 'brown');
						iconWrap.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:inherit"><circle cx="10" cy="13" r="3"></circle><path d="M21 21l-4.35-4.35"></path></svg>';
						node.appendChild(iconWrap);
						node.onclick = () => showFeatureModal({id: baseId, title: feature.title, description: feature.description});

						// label outside the circle
						const label = document.createElement('div');
						label.className = 'feature-label';
						label.style.pointerEvents = 'auto';
						let desc = feature.description || '';
						if (desc.length > 70) desc = desc.substring(0, 67) + '...';
						label.innerHTML = `<div class="title">${feature.title}</div><div class="desc">${desc}</div>`;
						label.onclick = node.onclick;

						// svg line
						const line = document.createElementNS(svgNS, 'line');
						line.setAttribute('x1', cx);
						line.setAttribute('y1', cy);
						line.setAttribute('x2', x);
						line.setAttribute('y2', y);
						line.setAttribute('stroke', '#E5E7EB');
						line.setAttribute('stroke-width', '1.5');
						line.setAttribute('stroke-linecap', 'round');
						line.setAttribute('opacity', '0.95');
						svg.appendChild(line);

						const len = line.getTotalLength();
						line.style.strokeDasharray = len;
						line.style.strokeDashoffset = len;

						container.appendChild(node);
						labels.appendChild(label);

						// position label centered below the node and clamp it inside the circle
						const tempTop = y + 36;
						let topPx = tempTop;
						const labelRect = label.getBoundingClientRect();
						const labelH = labelRect.height || 44;
						const maxTop = rect.height - labelH - 8;
						if (topPx > maxTop) topPx = Math.max(8, maxTop);
						if (topPx < 8) topPx = 8;
						label.style.left = x + 'px';
						label.style.top = topPx + 'px';
						label.style.transform = 'translate(-50%,0)';
						label.style.textAlign = 'center';

						// hover effects
						node.addEventListener('mouseover', () => {
							line.setAttribute('stroke', '#F59E0B');
							line.setAttribute('stroke-width', '3');
						});
						node.addEventListener('mouseout', () => {
							line.setAttribute('stroke', '#E5E7EB');
							line.setAttribute('stroke-width', '1.5');
						});
						label.addEventListener('mouseover', () => node.dispatchEvent(new Event('mouseover')));
						label.addEventListener('mouseout', () => node.dispatchEvent(new Event('mouseout')));

						// stagger animation
						setTimeout(() => {
							node.style.left = x + 'px';
							node.style.top = y + 'px';
							node.style.opacity = '1';
							node.style.transform = 'translate(-50%,-50%) scale(1)';
							line.style.strokeDashoffset = '0';
						}, 120 + i * 110);
					});
				}
				window.addEventListener('load', renderFeatureNodes);
				window.addEventListener('resize', renderFeatureNodes);
			</script>

			<!-- Mobile App-style Feature List -->
			<div class="md:hidden">
				<?php $__inf_colors = ['#ef4444','#8b5cf6','#06b6d4','#f59e0b','#10b981']; $__mi = 0; ?>
				<div class="space-y-3 px-2">
					<?php foreach ($features as $baseId => $feature): ?>
						<button type="button" class="w-full flex items-start bg-white rounded-lg shadow p-3 hover:shadow-lg transition-shadow feature-mobile-card" onclick="showFeatureModal({id: '<?php echo $baseId; ?>', title: '<?php echo addslashes($feature['title']); ?>', description: '<?php echo addslashes($feature['description']); ?>'})">
							<div class="flex-shrink-0">
								<div class="inf-number flex items-center justify-center text-white font-bold" style="width:48px;height:48px;border-radius:9999px;background:<?php echo $__inf_colors[$__mi % count($__inf_colors)]; ?>;">0<?php echo $__mi+1; ?></div>
							</div>
							<div class="ml-4 flex-1" style="border-left:4px solid <?php echo $__inf_colors[$__mi % count($__inf_colors)]; ?>; padding-left:1rem;">
								<div class="font-semibold text-gray-800 text-left"><?php echo htmlspecialchars($feature['title']); ?></div>
								<div class="text-sm text-gray-500 mt-1 text-left"><?php $d = $feature['description'] ?? ''; if (strlen($d) > 80) $d = substr($d,0,77) . '...'; echo htmlspecialchars($d); ?></div>
							</div>
						</button>
						<?php $__mi++; endforeach; ?>
				</div>
			</div>
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
					<a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_privacy_policy', 'Privacy Policy')); ?></a>
					<a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_terms_of_service', 'Terms of Service')); ?></a>
					<a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors"><?php echo htmlspecialchars(getSetting('footer_contact_us', 'Contact Us')); ?></a>
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

								<!-- Origin -->
								<div id="productDetailOrigin" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-globe-americas text-blue-500"></i> Origin
									</h5>
									<p class="text-gray-600 leading-relaxed" id="productDetailOriginText"></p>
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
