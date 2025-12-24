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

// Fetch products from database with average ratings for current language
$currentLanguage = getCurrentLanguage();
$stmt = $pdo->prepare("
    SELECT
        p.*,
        COALESCE(review_stats.avg_rating, 0) as avg_rating,
        COALESCE(review_stats.review_count, 0) as review_count
    FROM products p
    LEFT JOIN (
        SELECT 
            base_product_id,
            AVG(r.rating) as avg_rating,
            COUNT(r.id) as review_count
        FROM reviews r
        JOIN products pr ON r.product_id = pr.id
        GROUP BY pr.base_product_id
    ) review_stats ON p.base_product_id = review_stats.base_product_id
    WHERE p.language = ?
    ORDER BY p.sort_order ASC, p.featured DESC, p.id DESC
");
$stmt->execute([$currentLanguage]);
$products = $stmt->fetchAll();

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

// Separate featured products and regular products
$featuredProducts = array_filter($products, function($product) {
    return $product['featured'] == 1;
});
$regularProducts = array_filter($products, function($product) {
    return $product['featured'] == 0;
});
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
	<style>
	.font-freeman {
		font-family: 'Freeman', serif;
	}

	/* Swiper custom styles */
	.featured-swiper {
		padding-bottom: 25px;
	}

	.featured-swiper .swiper-slide {
		width: auto; /* allow Swiper to size slides for multi-column desktops */
		height: auto;
		transition: transform 0.3s ease;
	}

	/* Preserve fixed width on small screens for consistent card sizing */
	@media (max-width: 1023px) {
		.featured-swiper .swiper-slide {
			width: 280px;
		}
	}

	/* Desktop layout: make room so left/right slides are visible and size slides to fit 3 per view */
	@media (min-width: 900px) {
		.featured-swiper {
			padding-left: 48px;
			padding-right: 48px;
		}
		.featured-swiper .swiper-wrapper {
			align-items: center;
		}
		.featured-swiper .swiper-slide {
			width: calc((100% - 60px) / 3); /* 3 slides with total spaceBetween of ~60px */
		}
	}

	.featured-swiper .swiper-slide-active {
		transform: scale(1.1);
		z-index: 2;
	}

	.featured-swiper .swiper-slide-next,
	.featured-swiper .swiper-slide-prev {
		transform: scale(0.9);
		opacity: 0.7;
		z-index: 1;
	}

	.featured-swiper .swiper-slide article {
		width: 100%;
		height: 420px;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		transition: transform 0.3s ease, box-shadow 0.3s ease;
		overflow: hidden;
		cursor: pointer;
		border-radius: 1rem;
	}

	.featured-swiper .swiper-pagination-bullet {
		background: rgba(255, 255, 255, 0.7);
		border: 2px solid rgba(245, 158, 11, 0.3);
		width: 12px;
		height: 12px;
		margin: 0 4px;
		opacity: 1;
		transition: all 0.3s ease;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}

	.featured-swiper .swiper-pagination-bullet-active {
		background: #f59e0b;
		border-color: #f59e0b;
		transform: scale(1.1);
		box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
	}

	.featured-swiper .swiper-pagination {
		bottom: 8px !important;
	}

	.featured-swiper .swiper-slide-active article {
		box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
		transform: translateY(-5px);
	}

	/* Mobile Featured Swiper Pagination */
	.mobile-featured-swiper .swiper-pagination {
		margin-top: 24px !important;
		padding-bottom: 8px;
	}

	.mobile-featured-swiper .swiper-pagination-bullet {
		background: rgba(255, 255, 255, 0.8);
		border: 2px solid rgba(249, 115, 22, 0.4);
		width: 10px;
		height: 10px;
		margin: 0 6px;
		opacity: 1;
		transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		border-radius: 50%;
	}

	.mobile-featured-swiper .swiper-pagination-bullet-active {
		background: linear-gradient(135deg, #ea580c, #f97316);
		border-color: #ea580c;
		transform: scale(1.2);
		box-shadow: 0 2px 8px rgba(234, 88, 12, 0.4);
	}

	/* Ensure pagination doesn't interfere with cards */
	.mobile-featured-swiper {
		padding-bottom: 40px;
	}

	.featured-swiper .swiper-slide-next article,
	.featured-swiper .swiper-slide-prev article {
		box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
		opacity: 0.9;
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

	/* Featured Products Modal Styles */
	#featuredModal {
		transition: opacity 0.3s ease;
	}

	#featuredModal.hidden {
		opacity: 0;
		pointer-events: none;
	}

	#featuredModal:not(.hidden) {
		opacity: 1;
		pointer-events: auto;
	}

	.featured-modal-content {
		transform: scale(0.95);
		transition: transform 0.3s ease-out;
	}

	#featuredModal:not(.hidden) .featured-modal-content {
		transform: scale(1);
	}

	#featuredFloatingBtn {
		transition: all 0.3s ease;
		position: fixed;
		aspect-ratio: 1; /* Ensure perfect square/circle */
	}

	#featuredFloatingBtn.visible {
		opacity: 1;
		pointer-events: auto;
		transform: translateX(0);
	}

	@media (max-width: 768px) {
		.featured-modal-content {
			width: 98% !important;
			max-width: none !important;
			max-height: 98vh !important;
		}
		
		#featuredFloatingBtn {
			/* Ensure button stays within viewport on mobile */
			max-width: calc(100vw - 16px);
			right: 16px !important;
			left: auto !important;
			aspect-ratio: 1 !important; /* Force square aspect ratio */
			width: 40px !important;
			height: 40px !important;
		}
	}

	@media (max-width: 480px) {
		#featuredFloatingBtn {
			/* Extra small screens - ensure button is fully visible */
			right: 12px !important;
			width: 40px !important;
			height: 40px !important;
			max-width: calc(100vw - 24px) !important;
			aspect-ratio: 1 !important;
		}
		
		/* Move search button left on mobile to avoid collision */
		#searchButton {
			margin-right: 40px !important;
		}
	}

	@media (max-width: 360px) {
		#featuredFloatingBtn {
			/* Very small screens */
			right: 8px !important;
			width: 36px !important;
			height: 36px !important;
			aspect-ratio: 1 !important;
		}
		
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
		width: 200px !important;
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
		transition: transform 0.3s ease, box-shadow 0.3s ease;
		overflow: hidden;
		cursor: pointer;
		border-radius: 0.75rem;
		margin: 0;
	}

	.mobile-featured-swiper .swiper-slide-active article {
		box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
		transform: translateY(-3px);
	}

	.mobile-featured-swiper .swiper-slide-next article,
	.mobile-featured-swiper .swiper-slide-prev article {
		box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
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

	.product-price {
		font-size: 1.25rem;
		font-weight: 700;
		color: #d97706;
		margin-bottom: 0.75rem;
		text-align: center;
	}

	.product-button {
		background: #f59e0b;
		color: white;
		padding: 0.75rem 2rem;
		border-radius: 0.75rem;
		font-weight: 600;
		transition: all 0.3s ease;
		border: none;
		cursor: pointer;
		width: 100%;
		text-align: center;
		box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
	}

	.product-button:hover {
		background: #d97706;
		box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
	}

	/* Mobile optimizations */
	@media (max-width: 640px) {

		.featured-swiper .swiper-pagination {
			bottom: 5px !important;
		}

		.featured-swiper .swiper-slide article {
			height: 300px;
		}

		.product-title {
			font-size: 1rem;
			height: 2.4rem;
		}

		.product-description {
			font-size: 0.8rem;
			height: 2.4rem;
		}
	}

	/* Clean section styling */
	.section-header {
		border-bottom: 1px solid #e5e7eb;
		padding-bottom: 0.5rem;
		margin-bottom: 1rem;
	}

	.section-subtitle {
		color: #6b7280;
		font-size: 0.95rem;
	}
	@media (hover: none) and (pointer: coarse) {
		.featured-swiper .swiper-slide article:hover {
			transform: none;
		}

		.product-button:hover {
			transform: none;
		}
	}

	/* Enhanced Modal Styles */
	#productModal {
		transition: opacity 0.3s ease;
		backdrop-filter: blur(8px);
	}

	#productModal.hidden {
		opacity: 0;
		pointer-events: none;
	}

	#productModal:not(.hidden) {
		animation: modalFadeIn 0.4s ease-out;
	}

	@keyframes modalFadeIn {
		from {
			opacity: 0;
		}
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

.banner-swiper .swiper-pagination-bullet {
	width: 10px;
	height: 10px;
	background: rgba(255, 255, 255, 0.6);
	opacity: 1;
	transition: all 0.3s ease;
	border: 1px solid rgba(0, 0, 0, 0.1);
}

.banner-swiper .swiper-pagination-bullet-active {
	background: #ea580c;
	border-color: #ea580c;
	transform: scale(1.1);
	box-shadow: 0 2px 6px rgba(234, 88, 12, 0.3);
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

	/* Section Dividers */
	.section-divider {
		height: 2px;
		background: linear-gradient(90deg, transparent 0%, rgba(156, 163, 175, 0.3) 20%, rgba(156, 163, 175, 0.5) 50%, rgba(156, 163, 175, 0.3) 80%, transparent 100%);
		margin: 3rem 0;
		position: relative;
	}

	.section-divider::before {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 60px;
		height: 6px;
		background: linear-gradient(90deg, #f59e0b, #d97706);
		border-radius: 3px;
		box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
	}

	.section-divider-thick {
		height: 4px;
		background: linear-gradient(90deg, transparent 0%, rgba(31, 41, 55, 0.2) 15%, rgba(31, 41, 55, 0.4) 50%, rgba(31, 41, 55, 0.2) 85%, transparent 100%);
		margin: 4rem 0;
		position: relative;
		box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
	}

	.section-divider-thick::before {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 80px;
		height: 8px;
		background: linear-gradient(135deg, #1f2937, #374151);
		border-radius: 4px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
	}

	/* Mobile responsive dividers */
	@media (max-width: 768px) {
		.section-divider {
			margin: 2rem 0;
		}

		.section-divider::before {
			width: 40px;
			height: 4px;
		}

		.section-divider-thick {
			margin: 3rem 0;
		}

		.section-divider-thick::before {
			width: 60px;
			height: 6px;
		}
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
	</style>
	<!-- Swiper CSS -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
	<?php
	$cssFile = realpath(__DIR__ . '/../../public/css/output.css');
	$useOutput = false; // $cssFile && file_exists($cssFile) && filesize($cssFile) > 50;
	if ($useOutput) {
		echo '<link rel="stylesheet" href="css/output.css">';
	} else {
		echo '<script src="https://cdn.tailwindcss.com"></script>';
	}
	?>
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

	<section class="bg-linear-to-br from-yellow-50 to-orange-50 px-4 py-8 md:px-6 md:py-12">
	<section class="bg-gradient-to-br from-yellow-50 to-orange-50 px-4 py-8 md:px-6 md:py-12">
		<div class="max-w-6xl mx-auto">
			<div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
				<div class="order-2 md:order-1">
					<h2 class="text-2xl md:text-4xl font-bold text-gray-800 leading-tight"><?php echo htmlspecialchars(getSetting('hero_title', 'Discover The Finest Coffee')); ?> <span class="text-yellow-600"><?php echo htmlspecialchars(getSetting('hero_highlight', 'Finest Coffee')); ?></span></h2>
					<p class="mt-4 text-gray-600 text-sm md:text-base leading-relaxed"><?php echo nl2br(htmlspecialchars(getSetting('hero_subtitle', 'At KouPrey Coffee, we believe that every cup of coffee should be a journey. We source the finest beans and craft exceptional blends.'))); ?></p>
					<div class="mt-6 flex flex-col sm:flex-row items-start sm:items-center gap-3">
						<a href="<?php echo htmlspecialchars(getSetting('hero_cta_link', '#products')); ?>" class="inline-flex items-center justify-center bg-yellow-500 text-white px-6 py-3 rounded-lg font-medium shadow-sm hover:bg-yellow-600 transition-colors w-full sm:w-auto">
							<?php echo htmlspecialchars(getSetting('hero_cta_text', 'Shop Now')); ?>
						</a>
						<a href="features.php" class="text-gray-600 hover:text-gray-800 font-medium transition-colors"><?php echo htmlspecialchars(getSetting('explore_more', 'Explore More →')); ?></a>
					</div>

					<div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
						<div class="flex items-center space-x-3">
							<div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
								<i class="fas fa-check text-yellow-600 text-sm"></i>
							</div>
							<div>
								<p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars(getSetting('organic_label', '100% Organic')); ?></p>
								<p class="text-xs text-gray-600"><?php echo htmlspecialchars(getSetting('organic_desc', 'Premium quality beans')); ?></p>
							</div>
						</div>
						<div class="flex items-center space-x-3">
							<div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
								<i class="fas fa-check text-yellow-600 text-sm"></i>
							</div>
							<div>
								<p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars(getSetting('fresh_label', 'Fresh Roasted')); ?></p>
								<p class="text-xs text-gray-600"><?php echo htmlspecialchars(getSetting('fresh_desc', 'Daily roasted for freshness')); ?></p>
							</div>
						</div>
					</div>
				</div>

				<div class="order-1 md:order-2 relative">
					<?php if (!empty($heroImages)): ?>
						<div class="swiper hero-swiper">
							<div class="swiper-wrapper">
								<?php foreach ($heroImages as $image): $imageUrl = '/kouprey/public/uploads/' . basename($image); ?>
									<div class="swiper-slide">
										<div class="mx-auto w-64 h-80 md:w-80 md:h-96 rounded-2xl flex items-center justify-center float-animation">
											<img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Hero Image" class="max-h-72 md:max-h-80 object-contain">
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="swiper-pagination mt-4"></div>
						</div>
					<?php else: ?>
						<div class="mx-auto w-64 h-80 md:w-80 md:h-96 rounded-2xl flex items-center justify-center float-animation">
							<img src="<?php echo htmlspecialchars(getSetting('hero_background_image', '/kouprey/public/uploads/hero-bg-1765508631.png')); ?>" alt="Hero Image" class="max-h-72 md:max-h-80 object-contain">
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- Website Statistics & Reviews Section -->
	<section class="bg-white px-4 py-8 md:px-6 md:py-12">
		<div class="max-w-6xl mx-auto">
			<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
				<!-- Total Visitors -->
				<div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-8 text-center">
					<div class="text-4xl md:text-6xl font-bold text-blue-600 mb-2">
						<?php
						// Get total visitors from database
						try {
							$totalVisitors = VisitorTracker::getTotalVisitors();
							echo number_format($totalVisitors) . '+';
						} catch (Exception $e) {
							echo '2000+';
						}
						?>
					</div>
					<p class="text-xl font-semibold text-gray-700">Total Visitors</p>
					<div class="mt-4 flex items-center justify-center">
						<i class="fas fa-users text-blue-500 text-2xl mr-2"></i>
						<span class="text-sm text-green-600 font-medium">Growing daily</span>
					</div>
				</div>

				<!-- Total Reviews -->
				<div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-2xl p-8 text-center">
					<div class="text-4xl md:text-6xl font-bold text-yellow-600 mb-2">
						<?php
						// Get total reviews from database
						try {
							require_once __DIR__ . '/../Config/database.php';
							$totalReviewsStmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
							$totalReviews = $totalReviewsStmt->fetch()['total'];
							echo number_format($totalReviews) . '+';
						} catch (Exception $e) {
							echo '2000+';
						}
						?>
					</div>
					<p class="text-xl font-semibold text-gray-700">Reviews</p>
					<div class="mt-4 flex items-center justify-center">
						<i class="fas fa-star text-yellow-500 text-2xl mr-2"></i>
						<span class="text-sm text-green-600 font-medium">Customer satisfaction</span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Clean divider -->
	<div class="section-divider-thick"></div>

	<!-- Featured Products Modal -->
	<div id="featuredModal" class="fixed inset-0 z-50 hidden items-center justify-center">
		<div class="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm" onclick="closeFeaturedModal()"></div>
		<div class="relative w-full max-w-5xl max-h-[95vh] bg-white shadow-2xl transform scale-95 transition-transform duration-300 ease-out featured-modal-content rounded-lg overflow-hidden">
			<!-- Advertisement-style Header -->
			<div class="relative bg-gradient-to-r from-red-500 via-yellow-500 to-orange-500 p-6 text-white overflow-hidden">
				<!-- Animated background elements -->
				<div class="absolute inset-0 opacity-20">
					<div class="absolute top-0 left-0 w-32 h-32 bg-white rounded-full -translate-x-16 -translate-y-16 animate-pulse"></div>
					<div class="absolute top-0 right-0 w-24 h-24 bg-white rounded-full translate-x-12 -translate-y-12 animate-pulse delay-1000"></div>
					<div class="absolute bottom-0 left-1/4 w-20 h-20 bg-white rounded-full -translate-x-10 translate-y-10 animate-pulse delay-500"></div>
					<div class="absolute bottom-0 right-1/3 w-16 h-16 bg-white rounded-full translate-x-8 translate-y-8 animate-pulse delay-1500"></div>
				</div>

				<div class="relative z-10 flex justify-between items-center">
					<div class="flex-1">
						<div class="flex items-center mb-2">
							<div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm font-bold mr-3 animate-bounce">
								<i class="fas fa-fire text-red-300 mr-1"></i> HOT DEAL
							</div>
							<div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm font-bold animate-pulse">
								<i class="fas fa-clock text-blue-300 mr-1"></i> LIMITED TIME
							</div>
						</div>
						<h3 class="text-3xl font-extrabold mb-1 flex items-center">
							<i class="fas fa-star text-yellow-300 mr-3 animate-spin"></i>
							<?php echo htmlspecialchars(getSetting('featured_products', 'Featured Products')); ?>
						</h3>
						<p class="text-lg opacity-90 font-medium">
							<i class="fas fa-gift text-green-300 mr-2"></i>
							Exclusive Premium Collection - Up to 30% OFF!
						</p>
					</div>
					<button onclick="closeFeaturedModal()" class="p-3 hover:bg-white hover:bg-opacity-20 rounded-full transition-all duration-300 transform hover:scale-110">
						<i class="fas fa-times text-white text-xl"></i>
					</button>
				</div>

				<!-- Ribbon effect -->
				<div class="absolute top-4 right-4 bg-red-600 text-white px-4 py-2 rounded-lg transform rotate-12 shadow-lg animate-pulse">
					<span class="text-sm font-bold">SALE!</span>
				</div>
			</div>
			<div class="p-6 overflow-y-auto max-h-[calc(95vh-100px)]">
				<!-- Advertisement Content -->
				<div class="text-center py-12">
					<div class="max-w-2xl mx-auto">
						<h3 class="text-2xl font-bold text-gray-800 mb-4">🎉 Special Promotion! 🎉</h3>
						<p class="text-lg text-gray-600 mb-6">Discover our premium coffee collection with exclusive discounts up to 30% OFF!</p>
						<div class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white p-6 rounded-lg shadow-lg">
							<h4 class="text-xl font-bold mb-2">Limited Time Offer</h4>
							<p class="text-lg mb-4">Get your favorite coffee at unbeatable prices</p>
							<button onclick="closeModalAndScrollToProducts()" class="bg-white text-orange-600 px-8 py-3 rounded-full font-bold hover:bg-gray-100 transition-colors">
								Shop Now <i class="fas fa-arrow-right ml-2"></i>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Featured Products Floating Button -->
	<button id="featuredFloatingBtn" onclick="openFeaturedModal()" class="fixed bg-gradient-to-r from-red-500 to-yellow-500 hover:from-red-600 hover:to-yellow-600 text-white px-4 py-3 rounded-full shadow-2xl transition-all duration-300 transform translate-x-20 hover:scale-110 animate-pulse visible" style="z-index:99999;">
		<div class="flex items-center gap-2 relative">
			<i class="fas fa-star text-sm animate-spin"></i>
			<span class="text-sm font-bold hidden sm:inline-block truncate max-w-[6rem]">HOT DEALS</span>
	
		</div>
	</button>

	<!-- Clean divider -->
	<div class="section-divider"></div>

	<!-- Banner Slider -->
	<section class="w-full py-6 md:py-8 bg-gradient-to-r from-yellow-50 to-orange-50">
		<div class="w-full md:max-w-[1347px] md:mx-auto">
			<div class="swiper banner-swiper">
				<div class="swiper-wrapper">
					<?php
					// Get all banner settings dynamically - images only
					$bannerCount = 0;
					for ($i = 1; $i <= 20; $i++) { // Support up to 20 banners
						$bannerImage = getSetting('banner_' . $i . '_image');
						if (!empty($bannerImage)) {
							$bannerCount++;
							$bannerTitle = getSetting('banner_' . $i . '_title', 'Banner ' . $i);
							// Construct full path from stored filename
							$bannerImagePath = '/kouprey/public/uploads/banners/' . $bannerImage;
							?>
							<div class="swiper-slide">
								<div class="w-full h-48 md:h-[446px] overflow-hidden rounded-xl md:rounded-2xl" style="background-image: url('<?php echo htmlspecialchars($bannerImagePath); ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;">
								</div>
							</div>
							<?php
						}
					}

					// Default banner if no custom banners
					if ($bannerCount === 0):
					?>
					<div class="swiper-slide">
						<div class="w-full h-64 md:h-[446px] overflow-hidden rounded-2xl bg-gradient-to-r from-yellow-400 to-orange-500 flex items-center justify-center">
							<div class="text-center text-white">
								<i class="fas fa-coffee text-6xl md:text-8xl mb-4 opacity-80"></i>
								<h2 class="text-2xl md:text-4xl font-bold mb-2">Welcome to KouPrey Coffee</h2>
								<p class="text-lg md:text-xl opacity-90">Premium Coffee Collection</p>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div>

				<!-- Pagination -->
				<div class="swiper-pagination !bottom-4"></div>

				<!-- Navigation -->
				<div class="swiper-button-next !right-4"></div>
				<div class="swiper-button-prev !left-4"></div>
			</div>
		</div>
	</section>

	<!-- Featured Products Section -->
	<section class="px-4 py-8 md:px-6 md:py-12 bg-gradient-to-br from-yellow-50 to-orange-50">
		<div class="max-w-6xl mx-auto">
			<div class="text-center mb-8">
				<h3 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2 flex items-center justify-center">
					<i class="fas fa-star text-yellow-500 mr-3"></i><?php echo htmlspecialchars(getSetting('featured_products', 'Featured Products')); ?>
				</h3>
				<p class="text-gray-600"><?php echo htmlspecialchars(getSetting('featured_products_description', 'Discover our premium featured coffee collection')); ?></p>
			</div>

			<?php if (!empty($featuredProducts)): ?>
				<!-- Desktop Featured Swiper -->
				<div class="hidden md:block relative">
					<div class="swiper featured-swiper">
						<div class="swiper-wrapper">
							<?php foreach ($featuredProducts as $product): ?>
								<div class="swiper-slide" data-product-id="<?php echo $product['id']; ?>" data-detailed-description="<?php echo htmlspecialchars($product['detailed_description'] ?? ''); ?>" data-weight="<?php echo htmlspecialchars($product['weight'] ?? ''); ?>">
									<article class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow duration-300 border border-gray-100 cursor-pointer" onclick="showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
										<div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-semibold z-10 flex items-center gap-1">
											<i class="fas fa-fire"></i> Featured
										</div>
										<div class="flex flex-col items-center text-center h-full">
											<div class="w-32 h-32 mb-4 flex items-center justify-center">
												<img src="<?php echo $product['image'] ?: '/kouprey/public/assets/images/product-medium.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-w-full max-h-full object-contain">
											</div>
											<div class="product-card-content">
												<h4 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h4>
												<div class="flex items-center justify-center mb-2">
													<div class="flex items-center">
														<?php echo generateStars(round($product['avg_rating'])); ?>
														<span class="ml-2 text-sm text-gray-600">(<?php echo $product['review_count']; ?>)</span>
													</div>
												</div>
												<p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''); ?></p>
												<div class="text-center">
													<p class="product-price">$<?php echo number_format($product['price'], 2); ?></p>
													<button class="product-button" onclick="event.stopPropagation(); showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">View Details</button>
												</div>
											</div>
										</div>
									</article>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="swiper-pagination mt-6"></div>
						<div class="swiper-button-next"></div>
						<div class="swiper-button-prev"></div>
					</div>

					<!-- Desktop Product Info Label -->
					<div id="main-product-info-label" class="absolute bottom-20 left-1/2 transform -translate-x-1/2 bg-black bg-opacity-60 text-white px-6 py-3 text-sm font-medium opacity-0 transition-opacity duration-300 z-10 max-w-sm text-center shadow-lg">
						<div id="main-product-info-content">
							<div class="font-bold text-lg mb-1" id="main-product-name"></div>
							<div class="text-yellow-400 font-semibold text-lg mb-1" id="main-product-price"></div>
							<div class="text-sm text-gray-400 mb-1" id="main-product-weight"></div>
							<div class="text-sm text-gray-200 mb-1 font-medium" id="main-product-category"></div>
							<div class="text-sm text-gray-300 leading-relaxed" id="main-product-description"></div>
						</div>
					</div>
				</div>

				<!-- Mobile Featured Swiper -->
				<div class="md:hidden relative">
					<div class="swiper mobile-featured-swiper">
						<div class="swiper-wrapper">
							<?php 
							$mobileFeatured = array_slice($featuredProducts, 0, 6);
							if (!empty($mobileFeatured)): 
							?>
								<?php foreach ($mobileFeatured as $product): ?>
									<div class="swiper-slide" data-product-id="<?php echo $product['id']; ?>" data-detailed-description="<?php echo htmlspecialchars($product['detailed_description'] ?? ''); ?>" data-weight="<?php echo htmlspecialchars($product['weight'] ?? ''); ?>">
										<div class="px-2">
											<article class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-lg" onclick="showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
												<!-- Featured Badge -->
												<div class="relative">
													<div class="absolute top-3 right-3 bg-gradient-to-r from-orange-400 to-red-500 text-white px-2 py-1 rounded-full text-xs font-bold z-10 shadow-sm">
														<i class="fas fa-star mr-1"></i>Featured
													</div>
													<!-- Product Image -->
													<div class="w-full h-40 bg-gray-50 flex items-center justify-center p-4">
														<img src="<?php echo $product['image'] ?: '/kouprey/public/assets/images/product-medium.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-w-full max-h-full object-contain">
													</div>
												</div>

												<!-- Product Details -->
												<div class="p-4">
													<!-- Product Name -->
													<h4 class="text-sm font-bold text-gray-900 mb-2 line-clamp-2 leading-tight"><?php echo htmlspecialchars($product['name']); ?></h4>

													<!-- Rating -->
													<div class="flex items-center mb-3">
														<div class="flex items-center mr-2">
															<?php echo generateStars(round($product['avg_rating'])); ?>
														</div>
														<span class="text-xs text-gray-500">(<?php echo $product['review_count']; ?>)</span>
													</div>

													<!-- Description -->
													<p class="text-xs text-gray-600 mb-4 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars(substr($product['description'], 0, 60)) . (strlen($product['description']) > 60 ? '...' : ''); ?></p>

													<!-- Price and Action -->
													<div class="flex items-center justify-between">
														<div class="flex flex-col">
															<span class="text-lg font-bold text-gray-900">$<?php echo number_format($product['price'], 2); ?></span>
														</div>
														<button class="bg-gradient-to-r from-orange-500 to-orange-600 text-white px-4 py-2 rounded-xl text-xs font-semibold hover:from-orange-600 hover:to-orange-700 transition-all duration-200 shadow-sm hover:shadow-md transform hover:scale-105" onclick="event.stopPropagation(); showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
															<i class="fas fa-eye mr-1"></i>View
														</button>
													</div>
												</div>
											</article>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<div class="swiper-pagination mt-6"></div>
						<div class="swiper-button-next"></div>
						<div class="swiper-button-prev"></div>
					</div>

					<!-- Mobile Product Info Label -->
					<div id="main-mobile-product-info-label" class="absolute bottom-16 left-1/2 transform -translate-x-1/2 bg-black bg-opacity-70 text-white px-4 py-3 text-xs font-medium opacity-0 transition-opacity duration-300 z-10 max-w-xs text-center shadow-xl rounded-xl">
						<div id="main-mobile-product-info-content">
							<div class="font-bold text-sm mb-1" id="main-mobile-product-name"></div>
							<div class="text-orange-400 font-semibold text-sm mb-1" id="main-mobile-product-price"></div>
							<div class="text-xs text-gray-300 mb-1" id="main-mobile-product-weight"></div>
							<div class="text-xs text-gray-200 mb-1 font-medium" id="main-mobile-product-category"></div>
							<div class="text-xs text-gray-300 leading-relaxed" id="main-mobile-product-description"></div>
						</div>
					</div>
				</div>
			<?php else: ?>
				<div class="text-center py-16 px-4">
					<div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100 max-w-sm mx-auto">
						<div class="w-20 h-20 bg-gradient-to-br from-orange-100 to-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
							<i class="fas fa-coffee text-orange-400 text-3xl"></i>
						</div>
						<h4 class="text-lg font-bold text-gray-900 mb-2">No Featured Products</h4>
						<p class="text-gray-600 text-sm leading-relaxed">We're working on bringing you our best coffee selections. Check back soon!</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- Product List -->
	<section id="products" class="px-4 py-6 md:px-6 md:py-12 bg-white">
		<div class="max-w-6xl mx-auto">
			<div class="text-center mb-8">
				<h3 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2 flex items-center justify-center">
					<i class="fas fa-coffee text-yellow-500 mr-3"></i><?php echo htmlspecialchars(getSetting('our_products', 'Our Products')); ?>
				</h3>
				<p class="text-gray-600"><?php echo htmlspecialchars(getSetting('our_products_description', 'Discover our complete collection of premium coffee products')); ?></p>
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
										<span class="font-medium"><?php echo htmlspecialchars(getSetting('all_products', 'All Products')); ?></span>
									</span>
									<span class="bg-black bg-opacity-20 text-black px-3 py-1 rounded-full text-xs font-semibold"><?php echo count($products); ?></span>
								</button>

								<!-- Database Categories -->
								<?php if (!empty($categories)): ?>
									<?php foreach ($categories as $category): ?>
										<?php
										// Count products in this category
										$categoryCount = 0;
										foreach ($products as $product) {
											if ($product['category_id'] == $category['id']) {
												$categoryCount++;
											}
										}
										?>
										<button onclick="filterProducts('category-<?php echo $category['id']; ?>')" class="w-full text-left px-5 py-4 rounded-xl hover:bg-indigo-50 hover:shadow-md transition-all duration-300 flex items-center justify-between category-btn group" data-category="category-<?php echo $category['id']; ?>">
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

						<!-- Price Range Filter -->
						<div class="mt-8 pt-6 border-t border-gray-200">
							<h5 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
								<i class="fas fa-dollar-sign text-yellow-500 mr-2"></i><?php echo htmlspecialchars(getSetting('price_range', 'Price Range')); ?>
							</h5>
							<div class="space-y-3">
								<label class="flex items-center cursor-pointer">
									<input type="radio" name="price-range" value="all" class="text-indigo-600 focus:ring-indigo-500" checked>
									<span class="ml-3 text-sm font-medium text-gray-700"><?php echo htmlspecialchars(getSetting('all_prices', 'All Prices')); ?></span>
								</label>
								<label class="flex items-center cursor-pointer">
									<input type="radio" name="price-range" value="0-25" class="text-indigo-600 focus:ring-indigo-500">
									<span class="ml-3 text-sm font-medium text-gray-700">$0 - $25</span>
								</label>
								<label class="flex items-center cursor-pointer">
									<input type="radio" name="price-range" value="25-50" class="text-indigo-600 focus:ring-indigo-500">
									<span class="ml-3 text-sm font-medium text-gray-700">$25 - $50</span>
								</label>
								<label class="flex items-center cursor-pointer">
									<input type="radio" name="price-range" value="50+" class="text-indigo-600 focus:ring-indigo-500">
									<span class="ml-3 text-sm font-medium text-gray-700">$50+</span>
								</label>
							</div>
						</div>
					</div>
				</div>

				<!-- Products Grid -->
				<div class="w-full lg:w-3/4">
					<div id="products-container" class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
						<?php
						if (!empty($products)):
							foreach ($products as $product):
								$categoryClass = '';
								if ($product['featured']) {
									$categoryClass = 'product-featured';
								} else {
									$categoryClass = 'product-regular';
								}
						?>
							<article class="bg-white rounded-xl p-3 md:p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 group product-item <?php echo $categoryClass; ?> cursor-pointer" onclick="showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" data-category="<?php echo $product['featured'] ? 'featured' : 'regular'; ?>" data-category-id="<?php echo $product['category_id'] ?: ''; ?>" data-price="<?php echo $product['price']; ?>">
								<div class="relative mb-3 md:mb-4">
									<?php if ($product['featured']): ?>
										<div class="absolute top-1 left-1 md:top-2 md:left-2 bg-blue-500 text-white px-1 py-0.5 md:px-2 md:py-1 rounded-full text-xs font-semibold z-10 flex items-center gap-1">
											<i class="fas fa-star text-xs"></i> <span class="hidden sm:inline">Featured</span>
										</div>
									<?php elseif ($product['best_seller']): ?>
										<div class="absolute top-1 left-1 md:top-2 md:left-2 bg-green-500 text-white px-1 py-0.5 md:px-2 md:py-1 rounded-full text-xs font-semibold z-10 flex items-center gap-1">
											<i class="fas fa-fire text-xs"></i> <span class="hidden sm:inline">Best Seller</span>
										</div>
									<?php endif; ?>
									<div class="w-full h-32 md:h-48 flex items-center justify-center bg-gray-50 rounded-lg group-hover:bg-gray-100 transition-colors">
										<img src="<?php echo $product['image'] ?: '/kouprey/public/assets/images/product-medium.png'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="max-w-full max-h-full object-contain">
									</div>
								</div>
								<div class="text-center">
									<h4 class="text-sm md:text-lg font-semibold mb-1 md:mb-2 text-gray-800 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h4>
									<div class="flex items-center justify-center mb-1 md:mb-2">
										<div class="flex items-center">
											<?php echo generateStars(round($product['avg_rating'])); ?>
											<span class="ml-1 text-xs md:text-sm text-gray-600">(<?php echo $product['review_count']; ?>)</span>
										</div>
									</div>
									<p class="text-xs md:text-sm text-gray-600 mb-2 md:mb-3 line-clamp-2 hidden sm:block"><?php echo htmlspecialchars(substr($product['description'], 0, 80)) . (strlen($product['description']) > 80 ? '...' : ''); ?></p>
									
									<!-- Custom Fields Display -->
									<?php if (!empty($product['custom_fields']) && $product['custom_fields'] !== '{}' && $product['custom_fields'] !== 'null'): ?>
										<?php
										$customFieldsData = json_decode($product['custom_fields'], true);
										if ($customFieldsData && is_array($customFieldsData)):
											// Prefer fields positioned for 'end' (summary area), then fall back to others
											$endFields = [];
											$otherFields = [];
											foreach ($customFieldsData as $cfId => $cfData) {
												$pos = 'end';
												if (is_array($cfData) && isset($cfData['position_after'])) {
													$pos = $cfData['position_after'];
												}
												if ($pos === 'end') {
													$endFields[$cfId] = $cfData;
												} else {
													$otherFields[$cfId] = $cfData;
												}
											}
											$combined = $endFields + $otherFields; // end fields first
											$displayFields = array_slice($combined, 0, 2, true);
										?>
											<div class="mb-2 md:mb-3">
												<?php foreach ($displayFields as $fieldId => $fieldData): ?>
													<?php
													// Handle both old format (string value) and new format (object with translations)
													$fieldName = '';
													$fieldValue = '';
													
													if (is_string($fieldData)) {
														// Old format
														$fieldName = $fieldId;
														$fieldValue = $fieldData;
													} elseif (is_array($fieldData) && isset($fieldData['name']) && isset($fieldData['value'])) {
														// New format with translations
														$fieldName = $fieldData['name'][$currentLanguage] ?? $fieldData['name']['en'] ?? $fieldId;
														$fieldValue = $fieldData['value'][$currentLanguage] ?? $fieldData['value']['en'] ?? '';
													}
													
													if ($fieldName && $fieldValue && strlen($fieldValue) <= 50): // Only show short values
													?>
														<div class="text-xs text-gray-500 mb-1">
															<span class="font-medium"><?php echo htmlspecialchars($fieldName); ?>:</span> 
															<span><?php echo htmlspecialchars(substr($fieldValue, 0, 30)) . (strlen($fieldValue) > 30 ? '...' : ''); ?></span>
														</div>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									<?php endif; ?>
									
									<div class="flex items-center justify-between">
										<p class="text-lg md:text-xl font-bold text-gray-800">$<?php echo number_format($product['price'], 2); ?></p>
										<button class="bg-yellow-500 text-white px-2 py-1 md:px-4 md:py-2 rounded-lg text-xs md:text-sm font-semibold hover:bg-yellow-600 transition-colors" onclick="event.stopPropagation(); showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">View</button>
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
				</div>
			</div>
		</div>
	</section>

	<!-- Product Details Modal -->
	<div id="productModal" class="fixed inset-0 z-[100] hidden">
		<div class="absolute inset-0 bg-black bg-opacity-60 backdrop-blur-sm" onclick="hideProductModal()"></div>
		<div class="flex items-center justify-center min-h-screen p-4">
			<div class="modal-content bg-white max-w-4xl w-full max-h-[95vh] overflow-hidden relative" onclick="event.stopPropagation()">
				<!-- Modal Header -->
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<div id="modalBadgeHeader" class="px-4 py-2 text-sm font-bold rounded-full bg-blue-500 text-white">
							Featured
						</div>
						<h3 class="text-2xl font-bold text-gray-800 font-freeman" id="modalTitle"><?php echo htmlspecialchars(getSetting('modal_product_details', 'Product Details')); ?></h3>
					</div>
					<button id="closeModal" onclick="hideProductModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-full duration-200 ease-in-out">
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
									<img id="modalImage" src="" alt="Product Image" class="w-full max-w-md h-80 object-contain rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
								</div>
								<!-- Floating badge -->
								<div class="absolute -top-3 -right-3 bg-yellow-400 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg animate-pulse flex items-center">
									<i class="fas fa-check-circle text-white mr-1"></i> Premium
								</div>
							</div>

							<!-- Quick Info Cards -->
							<div class="grid grid-cols-2 gap-3">
								<div class="bg-blue-50 rounded-xl p-4 text-center">
									<div class="text-2xl font-bold text-blue-600 mb-1" id="modalPrice">$0.00</div>
									<div class="text-sm text-blue-600 font-medium flex items-center justify-center gap-1">
										<i class="fas fa-dollar-sign"></i> <span id="modalPriceLabel"><?php echo htmlspecialchars(getSetting('modal_price', 'Price')); ?></span>
									</div>
								</div>
								<div class="bg-green-50 rounded-xl p-4 text-center">
									<div class="text-2xl font-bold text-green-600 mb-1" id="modalWeight">250g</div>
									<div class="text-sm text-green-600 font-medium flex items-center justify-center gap-1">
										<i class="fas fa-weight-hanging"></i> <span id="modalWeightLabel"><?php echo htmlspecialchars(getSetting('modal_weight', 'Weight')); ?></span>
									</div>
								</div>
							</div>

							<!-- Related Products Section -->
							<div class="bg-green-50 rounded-xl p-4" id="relatedProductsSection" style="display: none;">
								<h5 class="font-bold text-green-800 mb-4 flex items-center gap-2">
									<i class="fas fa-link text-green-600"></i> Related Products
								</h5>
								<div id="relatedProductsContainer" class="flex flex-wrap gap-1 md:gap-2 justify-start">
									<!-- Related products will be loaded here -->
								</div>
							</div>
						</div>

						<!-- Product Details Section -->
						<div class="space-y-6">
							<!-- Product Title and Description -->
							<div>
								<h4 class="text-3xl font-bold text-gray-800 mb-3 font-freeman" id="modalProductName">Product Name</h4>
								<p class="text-gray-600 leading-relaxed text-lg" id="modalDescription">Product description goes here.</p>
							</div>

							<!-- Detailed Information Accordion -->
							<div class="space-y-3">
								<!-- Detailed Description -->
								<div id="modalDetailedDescription" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-info-circle text-blue-500"></i> <span id="modalDetailedDescLabel"><?php echo htmlspecialchars(getSetting('modal_detailed_description', 'Detailed Description')); ?></span>
									</h5>
									<p class="text-gray-600 leading-relaxed" id="modalDetailedDesc"></p>
								</div>

								<!-- Ingredients -->
								<div id="modalIngredients" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-seedling text-green-500"></i> <span id="modalIngredientsLabel"><?php echo htmlspecialchars(getSetting('modal_ingredients', 'Ingredients')); ?></span>
									</h5>
									<p class="text-gray-600 leading-relaxed" id="modalIngredientsText"></p>
								</div>

								<!-- Origin -->
								<div id="modalOrigin" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-globe-americas text-blue-500"></i> <span id="modalOriginLabel"><?php echo htmlspecialchars(getSetting('modal_origin', 'Origin')); ?></span>
									</h5>
									<p class="text-gray-600 leading-relaxed" id="modalOriginText"></p>
								</div>

								<!-- Brewing Instructions -->
								<div id="modalBrewing" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-coffee text-brown-500"></i> <span id="modalBrewingLabel"><?php echo htmlspecialchars(getSetting('modal_brewing_instructions', 'Brewing Instructions')); ?></span>
									</h5>
									<p class="text-gray-600 leading-relaxed" id="modalBrewingText"></p>
								</div>

								<!-- Tasting Notes -->
								<div id="modalTasting" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-tongue text-red-500"></i> <span id="modalTastingLabel"><?php echo htmlspecialchars(getSetting('modal_tasting_notes', 'Tasting Notes')); ?></span>
									</h5>
									<p class="text-gray-600 leading-relaxed" id="modalTastingText"></p>
								</div>

								<!-- Roast Level -->
								<div id="modalRoastInfo" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<h5 class="font-bold text-gray-800 mb-2 flex items-center gap-2">
										<i class="fas fa-burn text-orange-500"></i> <span id="modalRoastLabel"><?php echo htmlspecialchars(getSetting('modal_roast_level', 'Roast Level')); ?></span>
									</h5>
									<p class="text-gray-600 leading-relaxed" id="modalRoastLevelText"></p>
								</div>

								<!-- Custom Fields -->
								<div id="modalCustomFields" class="bg-gray-50 rounded-xl p-4" style="display: none;">
									<div id="modalCustomFieldsContent" class="space-y-2"></div>
								</div>
							</div>

							<!-- Action Buttons -->
							<div class="flex gap-4 pt-6 border-t border-gray-200">
								<button id="shareBtn" class="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 font-medium flex items-center justify-center gap-2">
									<i class="fas fa-share-alt"></i> <span id="modalShareLabel"><?php echo htmlspecialchars(getSetting('modal_share', 'Share')); ?></span>
								</button>
							</div>

							<!-- Additional Info -->
							<div class="bg-blue-50 rounded-xl p-4">
								<h5 class="font-bold text-blue-800 mb-2 flex items-center gap-2">
									<i class="fas fa-question-circle text-blue-600"></i> <span id="modalWhyChooseLabel"><?php echo htmlspecialchars(getSetting('modal_why_choose', 'Why Choose This Product?')); ?></span>
								</h5>
								<ul class="text-sm text-blue-700 space-y-1" id="modalDetails">
									<li><i class="fas fa-check text-green-500 mr-2"></i> <span id="modalQualityText"><?php echo htmlspecialchars(getSetting('modal_quality_ingredients', 'Premium quality ingredients')); ?></span></li>
									<li><i class="fas fa-check text-green-500 mr-2"></i> <span id="modalSourcedText"><?php echo htmlspecialchars(getSetting('modal_carefully_sourced', 'Carefully sourced and roasted')); ?></span></li>
									<li><i class="fas fa-check text-green-500 mr-2"></i> <span id="modalOccasionText"><?php echo htmlspecialchars(getSetting('modal_perfect_occasion', 'Perfect for any occasion')); ?></span></li>
									<li><i class="fas fa-check text-green-500 mr-2"></i> <span id="modalFlavorText"><?php echo htmlspecialchars(getSetting('modal_rich_flavor', 'Rich in flavor and aroma')); ?></span></li>
								</ul>
							</div>

							<!-- Reviews Section -->
							<div class="bg-gray-50 rounded-xl p-4">
								<h5 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
									<i class="fas fa-star text-yellow-500"></i> <span id="modalCustomerReviewsLabel"><?php echo htmlspecialchars(getSetting('modal_customer_reviews', 'Customer Reviews')); ?></span>
								</h5>
								
								<!-- Average Rating Display -->
								<div class="flex items-center mb-4" id="modalAverageRating">
									<div class="flex items-center mr-4">
										<span class="text-2xl font-bold text-gray-800 mr-2" id="avgRating">4.5</span>
										<div class="flex">
											<i class="fas fa-star text-yellow-400"></i>
											<i class="fas fa-star text-yellow-400"></i>
											<i class="fas fa-star text-yellow-400"></i>
											<i class="fas fa-star text-yellow-400"></i>
											<i class="fas fa-star-half-alt text-yellow-400"></i>
										</div>
									</div>
									<span class="text-sm text-gray-600" id="totalReviews">(12 reviews)</span>
								</div>

								<!-- Review Form -->
								<div class="border-t border-gray-200 pt-4">
									<h6 class="font-semibold text-gray-800 mb-3" id="modalWriteReviewLabel"><?php echo htmlspecialchars(getSetting('modal_write_review', 'Write a Review')); ?></h6>
									<form id="reviewForm" class="space-y-3">
										<input type="hidden" id="reviewProductId" name="product_id">
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1" id="modalYourNameLabel"><?php echo htmlspecialchars(getSetting('modal_your_name', 'Your Name')); ?></label>
											<input type="text" id="reviewerName" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
										</div>
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1" id="modalRatingLabel"><?php echo htmlspecialchars(getSetting('modal_rating', 'Rating')); ?></label>
											<div class="flex space-x-1">
												<input type="radio" id="star5" name="rating" value="5" class="hidden">
												<label for="star5" class="cursor-pointer text-gray-300 hover:text-yellow-400"><i class="fas fa-star text-xl"></i></label>
												<input type="radio" id="star4" name="rating" value="4" class="hidden">
												<label for="star4" class="cursor-pointer text-gray-300 hover:text-yellow-400"><i class="fas fa-star text-xl"></i></label>
												<input type="radio" id="star3" name="rating" value="3" class="hidden">
												<label for="star3" class="cursor-pointer text-gray-300 hover:text-yellow-400"><i class="fas fa-star text-xl"></i></label>
												<input type="radio" id="star2" name="rating" value="2" class="hidden">
												<label for="star2" class="cursor-pointer text-gray-300 hover:text-yellow-400"><i class="fas fa-star text-xl"></i></label>
												<input type="radio" id="star1" name="rating" value="1" class="hidden" checked>
												<label for="star1" class="cursor-pointer text-gray-300 hover:text-yellow-400"><i class="fas fa-star text-xl"></i></label>
											</div>
										</div>
										<div>
											<label class="block text-sm font-medium text-gray-700 mb-1" id="modalYourReviewLabel"><?php echo htmlspecialchars(getSetting('modal_your_review', 'Your Review')); ?></label>
											<textarea id="reviewText" name="review" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
										</div>
										<button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-colors" id="modalSubmitReviewBtn">
											<?php echo htmlspecialchars(getSetting('modal_submit_review', 'Submit Review')); ?>
										</button>
									</form>
								</div>

								<!-- Reviews List -->
								<div id="reviewsList" class="border-t border-gray-200 pt-4 space-y-3 max-h-60 overflow-y-auto">
									<!-- Reviews will be loaded here -->
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

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

		// Review functionality
		function loadProductReviews(productId) {
			fetch(`api.php?action=get_reviews&product_id=${productId}`)
				.then(response => response.json())
				.then(data => {
					displayReviews(data.reviews || []);
					updateAverageRating(data.avg_rating || 0, data.total_reviews || 0);
				})
				.catch(error => {
					console.error('Error loading reviews:', error);
					displayReviews([]);
					updateAverageRating(0, 0);
				});
		}

		// Related products functionality
		function loadRelatedProducts(baseProductId) {
			fetch(`api.php?action=get_related_products&base_product_id=${baseProductId}`)
				.then(response => response.json())
				.then(data => {
					displayRelatedProducts(data.related_products || []);
				})
				.catch(error => {
					console.error('Error loading related products:', error);
					displayRelatedProducts([]);
				});
		}

		function displayRelatedProducts(relatedProducts) {
			const section = document.getElementById('relatedProductsSection');
			const container = document.getElementById('relatedProductsContainer');

			if (!section || !container) return;

			if (relatedProducts.length === 0) {
				section.style.display = 'none';
				return;
			}

			container.innerHTML = relatedProducts.map(product => `
				<div class="related-product-item bg-white rounded-lg p-1 md:p-2 border border-gray-200 hover:shadow-md transition-shadow cursor-pointer flex-shrink-0 w-24 md:w-28" onclick="zoomRelatedProduct('${product.custom_image_url || product.image || '/kouprey/public/assets/images/placeholder.png'}', '${product.name.replace(/'/g, "\\'")}')">
					<img src="${product.custom_image_url || product.image || '/kouprey/public/assets/images/placeholder.png'}" 
						 alt="${product.name}" class="w-full h-20 md:h-24 rounded-lg object-cover">
					<div class="related-product-magnify">
						<i class="fas fa-search-plus"></i>
					</div>
				</div>
			`).join('');

			section.style.display = 'block';
		}

		// Related Products Zoom Functions
		function zoomRelatedProduct(imageSrc, productName) {
			const overlay = document.getElementById('relatedProductZoomOverlay');
			const zoomImage = document.getElementById('relatedProductZoomImage');
			
			if (overlay && zoomImage) {
				zoomImage.src = imageSrc;
				zoomImage.alt = productName;
				overlay.classList.add('active');
				document.body.style.overflow = 'hidden'; // Prevent background scrolling
			}
		}

		function closeRelatedProductZoom() {
			const overlay = document.getElementById('relatedProductZoomOverlay');
			if (overlay) {
				overlay.classList.remove('active');
				document.body.style.overflow = ''; // Restore scrolling
			}
		}

		function displayReviews(reviews) {
			const reviewsList = document.getElementById('reviewsList');
			if (!reviewsList) return;

			if (reviews.length === 0) {
			reviewsList.innerHTML = '<p class="text-gray-500 text-sm">' + translations.noReviews + '</p>';
			}

			reviewsList.innerHTML = reviews.map(review => `
				<div class="bg-white rounded-lg p-3 border border-gray-200">
					<div class="flex items-center justify-between mb-2">
						<div class="flex items-center">
							<span class="font-semibold text-gray-800 mr-2">${review.name}</span>
							<div class="flex">
								${generateStarsHTML(review.rating)}
							</div>
						</div>
						<span class="text-xs text-gray-500">${new Date(review.created_at).toLocaleDateString()}</span>
					</div>
					<p class="text-gray-700 text-sm">${review.review}</p>
				</div>
			`).join('');
		}

		function updateAverageRating(avgRating, totalReviews) {
			const avgRatingElement = document.getElementById('avgRating');
			const totalReviewsElement = document.getElementById('totalReviews');
			const modalAverageRating = document.getElementById('modalAverageRating');
			const avgStarsElement = modalAverageRating ? modalAverageRating.querySelector('.flex')?.children[1] : null;

			if (avgRatingElement) {
				avgRatingElement.textContent = avgRating.toFixed(1);
			}
			if (totalReviewsElement) {
				totalReviewsElement.textContent = `(${totalReviews} ${translations.reviewsText})`;
			}
			if (avgStarsElement) {
				avgStarsElement.innerHTML = generateStarsHTML(Math.round(avgRating));
			}
		}

		function generateStarsHTML(rating) {
			let stars = '';
			for (let i = 1; i <= 5; i++) {
				stars += i <= rating ? '<i class="fas fa-star text-yellow-400"></i>' : '<i class="far fa-star text-gray-300"></i>';
			}
			return stars;
		}

		// Star rating interaction
		document.addEventListener('DOMContentLoaded', function() {
			const starLabels = document.querySelectorAll('#productModal label[for^="star"]');
			starLabels.forEach((label, index) => {
				label.addEventListener('click', function() {
					const rating = 5 - index;
					updateStarDisplay(rating);
				});
			});

			function updateStarDisplay(rating) {
				starLabels.forEach((label, index) => {
					const star = label.querySelector('i');
					if (5 - index <= rating) {
						star.className = 'fas fa-star text-yellow-400 text-xl';
					} else {
						star.className = 'far fa-star text-gray-300 text-xl';
					}
				});
			}
		});

		// Review form submission
		document.addEventListener('submit', function(e) {
			if (e.target.id === 'reviewForm') {
				e.preventDefault();
				
				const formData = new FormData(e.target);
				const data = Object.fromEntries(formData);
				
				fetch('api.php?action=add_review', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					body: JSON.stringify(data)
				})
				.then(response => response.json())
				.then(result => {
					if (result.success) {
						// Reset form
						e.target.reset();
						// Reload reviews
						loadProductReviews(data.product_id);
						alert(translations.reviewSubmitted);
					} else {
						alert(translations.reviewError + ': ' + (result.error || 'Unknown error'));
					}
				})
				.catch(error => {
					console.error('Error:', error);
					alert(translations.reviewError);
				});
			}
		});

		// Product filtering functionality
		function filterProducts(category) {
			const products = document.querySelectorAll('.product-item');
			const categoryBtns = document.querySelectorAll('.category-btn');

			// Update desktop active button
			categoryBtns.forEach(btn => {
				btn.classList.remove('active', 'bg-yellow-50', 'text-yellow-700');
				if (btn.getAttribute('data-category') === category) {
					btn.classList.add('active', 'bg-yellow-50', 'text-black');
				}
			});

			// Update mobile active button
			updateMobileCategoryButtons(category);

			// Filter products
			let visibleCount = 0;
			products.forEach(product => {
				let showProduct = false;

				if (category === 'all') {
					showProduct = true;
				} else if (category.startsWith('category-')) {
					// Database category filter
					const categoryId = category.replace('category-', '');
					showProduct = product.getAttribute('data-category-id') === categoryId;
				} else {
					// Existing category filter (featured, regular)
					showProduct = product.getAttribute('data-category') === category;
				}

				if (showProduct) {
					product.style.display = 'block';
					visibleCount++;
				} else {
					product.style.display = 'none';
				}
			});

			// Update grid layout based on visible products
			const container = document.getElementById('products-container');
			const noProductsMsg = container.querySelector('.no-products-message');
			
			if (visibleCount === 0) {
				// Show no products message
				if (!noProductsMsg) {
					const msgDiv = document.createElement('div');
					msgDiv.className = 'no-products-message col-span-full text-center py-12';
					msgDiv.innerHTML = `
						<i class="fas fa-coffee text-gray-300 text-6xl mb-4"></i>
						<p class="text-gray-500 text-lg"><?php echo htmlspecialchars(getSetting('no_category_results', 'No products found in this category.')); ?></p>
					`;
					container.appendChild(msgDiv);
				} else {
					noProductsMsg.style.display = 'block';
				}
			} else {
				// Hide no products message if it exists
				if (noProductsMsg) {
					noProductsMsg.style.display = 'none';
				}
			}
		}

		// Price range filtering
		document.addEventListener('change', function(e) {
			if (e.target.name === 'price-range') {
				const priceRange = e.target.value;
				const products = document.querySelectorAll('.product-item');

				products.forEach(product => {
					const price = parseFloat(product.dataset.price);
					let show = true;

					switch(priceRange) {
						case '0-25':
							show = price >= 0 && price <= 25;
							break;
						case '25-50':
							show = price > 25 && price <= 50;
							break;
						case '50+':
							show = price > 50;
							break;
						default:
							show = true;
					}

					if (show) {
						product.style.display = 'block';
					} else {
						product.style.display = 'none';
					}
				});
			}
		});

		// Initialize with 'all' category active
		document.addEventListener('DOMContentLoaded', function() {
			filterProducts('all');
		});

		// Clear all filters function
		function clearAllFilters() {
			// Reset category to 'all'
			filterProducts('all');

			// Reset price range filters
			const priceRadios = document.querySelectorAll('input[name="price-range"], input[name="price-range-mobile"]');
			priceRadios.forEach(radio => {
				if (radio.value === 'all') {
					radio.checked = true;
				} else {
					radio.checked = false;
				}
			});

			// Reset price filtering
			filterByPrice('all');
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

		// Price filtering for mobile
		function filterByPrice(priceRange) {
			const products = document.querySelectorAll('.product-item');

			products.forEach(product => {
				const price = parseFloat(product.dataset.price);
				let show = true;

				switch(priceRange) {
					case '0-25':
						show = price >= 0 && price <= 25;
						break;
					case '25-50':
						show = price > 25 && price <= 50;
						break;
					case '50+':
						show = price > 50;
						break;
					default:
						show = true;
				}

				if (show) {
					product.style.display = 'block';
				} else {
					product.style.display = 'none';
				}
			});
		}

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

	<!-- Clean divider -->
	<div class="section-divider-thick"></div>

	<!-- Subscribe -->
	<?php if (isSettingEnabled('enable_newsletter')): ?>
	<section class="max-w-6xl mx-auto px-4 md:px-6 py-8 md:py-12">
		<div class="bg-white rounded-xl shadow p-8 flex flex-col md:flex-row items-center gap-6">
			<div class="flex-1">
				<h4 class="text-2xl font-bold"><?php echo htmlspecialchars(getSetting('newsletter_title', 'Get 10% Discount')); ?></h4>
				<p class="text-gray-500 mt-2"><?php echo htmlspecialchars(getSetting('newsletter_description', 'Subscribe to our newsletter to receive discounts and latest product news.')); ?></p>
			</div>
			<form class="flex w-full md:w-auto gap-2">
				<input aria-label="email" placeholder="Your email address" class="px-4 py-3 border rounded w-full md:w-80">
				<button class="bg-yellow-400 text-white px-5 py-3 rounded"><?php echo htmlspecialchars(getSetting('newsletter_button_text', 'Subscribe')); ?></button>
			</form>
		</div>
	</section>
	<?php endif; ?>

	<!-- Map Section -->
	<section class="max-w-4xl mx-auto px-4 md:px-6 py-6 md:py-8">
		<div class="bg-white rounded-lg shadow p-6">
			<div class="text-center mb-4">
				<h4 class="text-xl font-bold mb-2">ទីតាំងរបស់យើង</h4>
				<p class="text-gray-600 text-sm">ស្វែងរកទីតាំងហាងនៃក្រុមហ៊ុនយើងនៅលើផែនទី</p>
			</div>
			<div class="aspect-video w-full max-w-md mx-auto rounded-lg overflow-hidden shadow-md">
				<iframe
					src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3908.764!2d104.888!3d11.568!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTEuNTY4LDEwNC44ODg!5e0!3m2!1sen!2skh!4v1638360000000!5m2!1sen!2skh"
					width="100%"
					height="100%"
					style="border:0;"
					allowfullscreen=""
					loading="lazy"
					referrerpolicy="no-referrer-when-downgrade"
					title="KouPrey Coffee Location">
				</iframe>
			</div>
			<div class="text-center mt-3">
				<a href="https://maps.google.com/?q=KouPrey+Coffee+Phnom+Penh+Cambodia" target="_blank" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 text-sm font-medium">
					<i class="fas fa-external-link-alt"></i>
					បើកនៅលើ Google Maps
				</a>
			</div>
		</div>
	</section>

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

                                <!-- Quick Links - Desktop: Original List, Mobile: Flex Cards -->
                                <div>
                                        <h3 class="text-lg font-semibold mb-4 text-white">Quick Links</h3>

                                        <!-- Desktop Version - Original List -->
                                        <ul class="hidden md:block space-y-2">
                                                <li><a href="/kouprey/public/" class="text-gray-300 hover:text-yellow-400 transition-colors">Home</a></li>
                                                <li><a href="/kouprey/public/features.php" class="text-gray-300 hover:text-yellow-400 transition-colors">Products</a></li>
                                                <li><a href="/kouprey/public/about.php" class="text-gray-300 hover:text-yellow-400 transition-colors">About Us</a></li>
                                                <li><a href="/kouprey/public/reviews.php" class="text-gray-300 hover:text-yellow-400 transition-colors">Reviews</a></li>
                                                <li><a href="/kouprey/admin/login.php" class="text-gray-300 hover:text-yellow-400 transition-colors">Admin</a></li>
                                        </ul>

                                        <!-- Mobile Version - Flex Cards -->
                                        <div class="md:hidden flex flex-wrap gap-3">
                                                <a href="/kouprey/public/" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-home text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight">Home</span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/public/features.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-coffee text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight">Products</span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/public/about.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-info-circle text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight">About</span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/public/reviews.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-star text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight">Reviews</span>
                                                        </div>
                                                </a>
                                                <a href="/kouprey/admin/login.php" class="flex-1 min-w-0 bg-white bg-opacity-10 hover:bg-opacity-20 rounded-lg px-3 py-4 text-center transition-all duration-200 border border-white border-opacity-20 hover:border-opacity-40 group">
                                                        <div class="flex flex-col items-center justify-center space-y-2">
                                                                <i class="fas fa-cog text-yellow-400 text-lg group-hover:scale-110 transition-transform duration-200"></i>
                                                                <span class="text-sm font-medium text-white leading-tight">Admin</span>
                                                        </div>
                                                </a>
                                        </div>
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
                                <p class="text-gray-400 text-sm"><?php echo htmlspecialchars(getSetting('footer_text', 'ï¿½ ' . date('Y') . ' KouPrey. All rights reserved.')); ?></p>
                                <div class="flex space-x-6 mt-4 md:mt-0">
                                        <a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors">Privacy Policy</a>
                                        <a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors">Terms of Service</a>
                                        <a href="#" class="text-gray-400 hover:text-gray-300 text-sm transition-colors">Contact Us</a>
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

			// Function to show modal with product data
			window.showProductModal = function(productData) {
				// helper to read current language from cookie
				function getCurrentLangFromCookie() {
					const cookies = document.cookie.split(';');
					for (let cookie of cookies) {
						const [name, value] = cookie.trim().split('=');
						if (name === 'language') return value;
					}
					return 'en';
				}

				const currentLang = getCurrentLangFromCookie();
				const baseId = productData.base_product_id || productData.baseProductId || productData.baseProduct_id;

				function render(prod) {
					const modalTitle = document.getElementById('modalTitle');
					const modalProductName = document.getElementById('modalProductName');
					const modalImage = document.getElementById('modalImage');
					const modalPrice = document.getElementById('modalPrice');
					const modalDescription = document.getElementById('modalDescription');

					if (modalTitle) modalTitle.textContent = prod.name || '';
					if (modalProductName) modalProductName.textContent = prod.name || '';
					if (modalImage) {
						modalImage.src = prod.image || '';
						modalImage.alt = prod.name || '';
					}
					if (modalPrice) modalPrice.textContent = prod.price ? ('$' + parseFloat(prod.price).toFixed(2)) : '';
					if (modalDescription) modalDescription.textContent = prod.description || '';

					const badge = document.getElementById('modalBadgeHeader');
					if (badge) {
						if (prod.featured == 1) {
							badge.innerHTML = '<i class="fas fa-star text-yellow-300 mr-1"></i> ' + translations.modalFeatured;
							badge.className = 'px-4 py-2 text-sm font-bold rounded-full bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg flex items-center';
						} else {
							badge.innerHTML = '<i class="fas fa-gem text-purple-300 mr-1"></i> ' + translations.modalPremium;
							badge.className = 'px-4 py-2 text-sm font-bold rounded-full bg-gradient-to-r from-green-500 to-green-600 text-white shadow-lg flex items-center';
						}
					}

					const detailedDesc = document.getElementById('modalDetailedDescription');
					const detailedDescText = document.getElementById('modalDetailedDesc');
					if (detailedDesc && detailedDescText) {
						if (prod.detailed_description) {
							detailedDescText.textContent = prod.detailed_description;
							detailedDesc.style.display = 'block';
						} else {
							detailedDesc.style.display = 'none';
						}
					}

					const ingredients = document.getElementById('modalIngredients');
					const ingredientsText = document.getElementById('modalIngredientsText');
					if (ingredients && ingredientsText) {
						if (prod.ingredients) {
							ingredientsText.textContent = prod.ingredients;
							ingredients.style.display = 'block';
						} else {
							ingredients.style.display = 'none';
						}
					}

					const origin = document.getElementById('modalOrigin');
					const originText = document.getElementById('modalOriginText');
					if (origin && originText) {
						if (prod.origin) {
							originText.textContent = prod.origin;
							origin.style.display = 'block';
						} else {
							origin.style.display = 'none';
						}
					}

					const brewing = document.getElementById('modalBrewing');
					const brewingText = document.getElementById('modalBrewingText');
					if (brewing && brewingText) {
						if (prod.brewing_instructions) {
							brewingText.textContent = prod.brewing_instructions;
							brewing.style.display = 'block';
						} else {
							brewing.style.display = 'none';
						}
					}

					const tasting = document.getElementById('modalTasting');
					const tastingText = document.getElementById('modalTastingText');
					if (tasting && tastingText) {
						if (prod.tasting_notes) {
							tastingText.textContent = prod.tasting_notes;
							tasting.style.display = 'block';
						} else {
							tasting.style.display = 'none';
						}
					}

					const specs = document.getElementById('modalSpecs');
					const weight = document.getElementById('modalWeight');
					const roastLevel = document.getElementById('modalRoastLevelText');

					if (weight) {
						weight.textContent = prod.weight || 'Not specified';
					}
					if (roastLevel) {
						roastLevel.textContent = prod.roast_level || 'Not specified';
					}
					if (specs) specs.style.display = 'grid';

					const customFields = document.getElementById('modalCustomFields');
					const customFieldsContent = document.getElementById('modalCustomFieldsContent');
					const lang = currentLang || getCurrentLangFromCookie();
					if (customFields && customFieldsContent) {
						// Clear previous custom placements
						customFieldsContent.innerHTML = '';

						if (prod.custom_fields && prod.custom_fields !== '{}' && prod.custom_fields !== 'null') {
							try {
								const customFieldsData = JSON.parse(prod.custom_fields);

								// Render all custom fields as separate cards inside the dedicated custom fields container
								const groups = {};
								Object.entries(customFieldsData).forEach(([fieldId, fieldData]) => {
									const pos = (fieldData && fieldData.position_after) ? fieldData.position_after : 'end';
									if (!groups[pos]) groups[pos] = [];
									groups[pos].push({ id: fieldId, data: fieldData });
								});

								function renderCustomCard(fieldObj) {
									const fieldData = fieldObj.data;
									let fieldName = '';
									let fieldValue = '';
									if (typeof fieldData === 'string') {
										fieldName = fieldObj.id.replace(':', '').trim();
										fieldValue = fieldData;
									} else if (fieldData && fieldData.name && fieldData.value) {
										fieldName = fieldData.name[lang] || fieldData.name.en || fieldObj.id;
										fieldValue = fieldData.value[lang] || fieldData.value.en || '';
									}
									if (fieldName && fieldValue) {
										return `
											<div class="bg-white rounded-xl p-4 mb-3 border">
												<h6 class="font-semibold text-gray-800 mb-2">${fieldName}</h6>
												<p class="text-gray-600 leading-relaxed">${fieldValue}</p>
											</div>
										`;
									}
									return '';
								}

								const positionsOrder = [
									'detailed_description',
									'ingredients',
									'origin',
									'brewing_instructions',
									'tasting_notes',
									'weight',
									'roast_level',
									'end'
								];

								// Append fields in the defined order into the single custom fields container
								positionsOrder.forEach(pos => {
									if (groups[pos]) {
										groups[pos].forEach(f => customFieldsContent.insertAdjacentHTML('beforeend', renderCustomCard(f)));
									}
								});

								// Show or hide container based on content
								if (customFieldsContent.children.length > 0) {
									customFields.style.display = 'block';
								} else {
									customFields.style.display = 'none';
								}
							} catch (e) {
								console.error('Error parsing custom fields:', e);
								customFields.style.display = 'none';
							}
						} else {
							customFields.style.display = 'none';
						}
					}

					// Load reviews and related products
					loadProductReviews(prod.id || 0);
					loadRelatedProducts(prod.base_product_id || prod.id);

					const reviewProductId = document.getElementById('reviewProductId');
					if (reviewProductId) reviewProductId.value = prod.id || 0;

					if (modal) {
						modal.classList.remove('hidden');
						document.body.style.overflow = 'hidden';
					}
				}

				// If baseId exists, fetch the product for the current language first, else render from embedded data
				if (baseId) {
					const apiUrl = 'api.php?action=get_product&base_product_id=' + encodeURIComponent(baseId) + '&language=' + encodeURIComponent(currentLang);
					fetch(apiUrl)
						.then(r => r.json())
						.then(json => {
							if (json && json.success && json.product) render(json.product);
							else render(productData);
						})
						.catch(() => render(productData));
				} else {
					render(productData);
				}
			}

            // Function to hide modal
            window.hideProductModal = function() {
                if (!modal) return;
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }

			function openFeaturedModal() {
				const modal = document.getElementById('featuredModal');
				if (!modal) return;
				const floatingBtn = document.getElementById('featuredFloatingBtn');
				
				// show modal: remove hidden and ensure flex layout for centering
				modal.classList.remove('hidden');
				modal.classList.add('flex');
				floatingBtn.classList.remove('visible');
				// Initialize modal swiper after modal is visible
				setTimeout(() => {
					if (typeof Swiper !== 'undefined') {
						// Desktop swiper
						const modalSwiper = new Swiper('.modal-featured-swiper', {
							slidesPerView: 3,
							spaceBetween: 30,
							centeredSlides: true,
							loop: true,
							pagination: {
								el: '.modal-featured-swiper .swiper-pagination',
								clickable: true,
							},
							navigation: {
								nextEl: '.modal-featured-swiper .swiper-button-next',
								prevEl: '.modal-featured-swiper .swiper-button-prev',
							},
							autoplay: {
								delay: 1000,
								disableOnInteraction: false,
							},
							breakpoints: {
								900: {
									slidesPerView: 3,
								},
								600: {
									slidesPerView: 2,
								},
								0: {
									slidesPerView: 1,
								}
							}
						});
						// Mobile swiper
						const mobileModalSwiper = new Swiper('.mobile-featured-swiper', {
							slidesPerView: 'auto',
							spaceBetween: 20,
							centeredSlides: true,
							loop: true,
							pagination: {
								el: '.mobile-featured-swiper .swiper-pagination',
								clickable: true,
							},
							navigation: {
								nextEl: '.mobile-featured-swiper .swiper-button-next',
								prevEl: '.mobile-featured-swiper .swiper-button-prev',
							},
							autoplay: {
								delay: 1000,
								disableOnInteraction: false,
							}
						});
						// Add event listeners for mobile swiper
						mobileModalSwiper.on('slideChangeTransitionStart', updateMobileProductInfoLabel);
						mobileModalSwiper.on('slideChange', updateMobileProductInfoLabel);
					}
				}, 100);
			}

			function closeFeaturedModal() {
				const modal = document.getElementById('featuredModal');
				const floatingBtn = document.getElementById('featuredFloatingBtn');
				
				// hide modal: add hidden and remove flex
				modal.classList.add('hidden');
				modal.classList.remove('flex');
				floatingBtn.classList.add('visible');
				positionFloatingButton();
				
				// Mark as visited
				localStorage.setItem('featuredModalVisited', 'true');
			}

            function closeModalAndScrollToProducts() {
                closeFeaturedModal();
                // Wait for modal close animation, then scroll to products
                setTimeout(() => {
                    const productsSection = document.getElementById('products');
                    if (productsSection) {
                        productsSection.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 300);
            }

            // Reposition button on window resize and scroll
            window.addEventListener('resize', positionFloatingButton);
            window.addEventListener('scroll', function() {
                const floatingBtn = document.getElementById('featuredFloatingBtn');
                if (floatingBtn && floatingBtn.classList.contains('visible')) {
                    positionFloatingButton();
                }
            });

            // Product info label functionality for mobile
            function updateMobileProductInfoLabel() {
                const activeSlide = document.querySelector('.mobile-featured-swiper .swiper-slide-active');
                const label = document.getElementById('main-mobile-product-info-label');
                
                if (activeSlide && label) {
                    const productName = activeSlide.querySelector('h4')?.textContent;
                    const productPrice = activeSlide.querySelector('p.font-bold')?.textContent;
                    const productDesc = activeSlide.dataset.detailedDescription || activeSlide.querySelector('p.text-xs.text-gray-600')?.textContent;
                    const productWeight = activeSlide.dataset.weight;
                    
                    if (productName && productPrice) {
                        const nameEl = document.getElementById('main-mobile-product-name');
                        const priceEl = document.getElementById('main-mobile-product-price');
                        const weightEl = document.getElementById('main-mobile-product-weight');
                        const descEl = document.getElementById('main-mobile-product-description');
                        const catEl = document.getElementById('main-mobile-product-category');
                        
                        if (nameEl) nameEl.textContent = productName;
                        if (priceEl) priceEl.textContent = productPrice;
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

            function positionFloatingButton() {
                const searchButton = document.getElementById('searchButton');
                const floatingBtn = document.getElementById('featuredFloatingBtn');
                const isMobile = window.innerWidth <= 768;
                
                if (searchButton && floatingBtn) {
                    const searchRect = searchButton.getBoundingClientRect();
                    const header = document.querySelector('header');
                    const headerRect = header.getBoundingClientRect();
                    
                    const buttonSize = window.innerWidth <= 360 ? 36 : 40; // Smaller button for very small screens
                    
                    if (isMobile) {
                        // On mobile, position relative to viewport to prevent off-screen
                        const viewportWidth = window.innerWidth;
                        const buttonRightEdge = searchRect.right + 8 + buttonSize;
                        
                        if (buttonRightEdge > viewportWidth - 16) {
                            // Button would go off-screen, position it to the left instead
                            floatingBtn.style.left = (searchRect.left - buttonSize - 8) + 'px';
                        } else {
                            // Safe to position to the right
                            floatingBtn.style.left = (searchRect.right + 8) + 'px';
                        }
                        
                        floatingBtn.style.top = (headerRect.top + (headerRect.height / 2) - (buttonSize / 2)) + 'px';
                        floatingBtn.style.right = 'auto';
                    } else {
                        // Desktop positioning
                        floatingBtn.style.top = (headerRect.top + (headerRect.height / 2) - (buttonSize / 2)) + 'px';
                        floatingBtn.style.left = (searchRect.right + 8) + 'px'; // 8px gap to the right
                        floatingBtn.style.right = 'auto';
                    }
                    
                    floatingBtn.style.width = buttonSize + 'px';
                    floatingBtn.style.height = buttonSize + 'px';
                    floatingBtn.style.aspectRatio = '1'; // Ensure square aspect ratio
                } else {
                    // Fallback positioning if search button not found
                    if (floatingBtn) {
                        const buttonSize = window.innerWidth <= 360 ? 36 : 40;
                        if (isMobile) {
                            floatingBtn.style.top = '80px';
                            floatingBtn.style.right = '16px';
                            floatingBtn.style.left = 'auto';
                        } else {
                            floatingBtn.style.top = '80px';
                            floatingBtn.style.right = '16px';
                            floatingBtn.style.left = 'auto';
                        }
                        floatingBtn.style.width = buttonSize + 'px';
                        floatingBtn.style.height = buttonSize + 'px';
                        floatingBtn.style.aspectRatio = '1'; // Ensure square aspect ratio
                    }
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
                    const productPrice = activeSlide.querySelector('.product-price')?.textContent;
                    const productDesc = activeSlide.dataset.detailedDescription || activeSlide.querySelector('.product-description')?.textContent;
                    const productWeight = activeSlide.dataset.weight;
                    
                    if (productName && productPrice) {
                        const nameEl = document.getElementById('main-product-name');
                        const priceEl = document.getElementById('main-product-price');
                        const weightEl = document.getElementById('main-product-weight');
                        const descEl = document.getElementById('main-product-description');
                        const catEl = document.getElementById('main-product-category');
                        
                        if (nameEl) nameEl.textContent = productName;
                        if (priceEl) priceEl.textContent = productPrice;
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
                const activeSlide = document.querySelector('.mobile-featured-swiper .swiper-slide-active');
                const label = document.getElementById('main-mobile-product-info-label');
                
                if (activeSlide && label) {
                    const productName = activeSlide.querySelector('h4')?.textContent;
                    const productPrice = activeSlide.querySelector('p.font-bold')?.textContent;
                    const productDesc = activeSlide.dataset.detailedDescription || activeSlide.querySelector('p.text-xs.text-gray-600')?.textContent;
                    const productWeight = activeSlide.dataset.weight;
                    
                    if (productName && productPrice) {
                        const nameEl = document.getElementById('main-mobile-product-name');
                        const priceEl = document.getElementById('main-mobile-product-price');
                        const weightEl = document.getElementById('main-mobile-product-weight');
                        const descEl = document.getElementById('main-mobile-product-description');
                        const catEl = document.getElementById('main-mobile-product-category');
                        
                        if (nameEl) nameEl.textContent = productName;
                        if (priceEl) priceEl.textContent = productPrice;
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

            // Initialize main page featured swipers only if they exist
            let mainFeaturedSwiper = null;
            let mainMobileFeaturedSwiper = null;

            if (document.querySelector('.featured-swiper')) {
                mainFeaturedSwiper = new Swiper('.featured-swiper', {
                    slidesPerView: 1.5,
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
                        delay: 1000,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    },
                    speed: 800,
                    grabCursor: true,
                    keyboard: {
                        enabled: true,
                    },
                    effect: 'coverflow',
                    coverflowEffect: {
                        rotate: 0,
                        stretch: 0,
                        depth: 200,
                        modifier: 1.5,
                        slideShadows: false,
                    },
                    breakpoints: {
                        900: {
                            slidesPerView: 3,
                            spaceBetween: 30,
                            centeredSlides: true,
                            slideToClickedSlide: true,
                            effect: 'coverflow',
                            coverflowEffect: {
                                rotate: 0,
                                stretch: 0,
                                depth: 150,
                                modifier: 1.2,
                                slideShadows: false,
                            },
                        }
                    }
                });
            }

            if (document.querySelector('.mobile-featured-swiper')) {
                mainMobileFeaturedSwiper = new Swiper('.mobile-featured-swiper', {
                    slidesPerView: 1.5,
                    spaceBetween: 20,
                    centeredSlides: true,
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
                        delay: 1000,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    },
                    speed: 600,
                    grabCursor: true,
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
            closeModalBtn.addEventListener('click', hideProductModal);

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    hideProductModal();
                }
            });

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

            // Search Modal functionality
            const searchModal = document.getElementById('searchModal');
            const searchButton = document.getElementById('searchButton');
            const closeSearchModalBtn = document.getElementById('closeSearchModal');
            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            const noResults = document.getElementById('noResults');

            // Function to show search modal
            function showSearchModal() {
                if (!searchModal) return;
                searchModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                searchInput.focus();
            }

            // Function to hide search modal
            function hideSearchModal() {
                if (!searchModal) return;
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
                        showProductModal(product);
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

            // Show floating button after page load
            document.addEventListener('DOMContentLoaded', function() {
                const floatingBtn = document.getElementById('featuredFloatingBtn');
                if (floatingBtn) {
                    // Simple fixed positioning
                    floatingBtn.style.position = 'fixed';
                    floatingBtn.style.top = '100px';
                    floatingBtn.style.right = '20px';
                    floatingBtn.style.zIndex = '99999';
                    
                    // Show modal on first visit
                    if (!localStorage.getItem('featuredModalVisited')) {
                        setTimeout(() => {
                            openFeaturedModal();
                        }, 2000); // Show modal after 2 seconds
                    }
                }
            });
			}); // Close DOMContentLoaded
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

                <!-- Price Range Filter - More Prominent -->
                <div class="px-6 pb-6">
                    <div class="bg-yellow-50 rounded-2xl p-4 border border-yellow-100">
						<h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
							<i class="fas fa-dollar-sign text-yellow-500 mr-2"></i><?php echo htmlspecialchars(getSetting('price_range', 'Price Range')); ?>
						</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-25 transition-all cursor-pointer">
                                <input type="radio" name="price-range-mobile" value="all" class="text-yellow-500 focus:ring-yellow-500" checked onchange="filterByPrice(this.value)">
								<span class="ml-3 text-sm font-medium text-gray-700"><?php echo htmlspecialchars(getSetting('all_prices', 'All Prices')); ?></span>
                            </label>
                            <label class="flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-25 transition-all cursor-pointer">
                                <input type="radio" name="price-range-mobile" value="0-25" class="text-yellow-500 focus:ring-yellow-500" onchange="filterByPrice(this.value)">
								<span class="ml-3 text-sm font-medium text-gray-700"><?php echo htmlspecialchars(getSetting('price_0_25', '$0 - $25')); ?></span>
                            </label>
                            <label class="flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-25 transition-all cursor-pointer">
                                <input type="radio" name="price-range-mobile" value="25-50" class="text-yellow-500 focus:ring-yellow-500" onchange="filterByPrice(this.value)">
								<span class="ml-3 text-sm font-medium text-gray-700"><?php echo htmlspecialchars(getSetting('price_25_50', '$25 - $50')); ?></span>
                            </label>
                            <label class="flex items-center p-3 bg-white rounded-xl border border-gray-200 hover:border-yellow-300 hover:bg-yellow-25 transition-all cursor-pointer">
                                <input type="radio" name="price-range-mobile" value="50+" class="text-yellow-500 focus:ring-yellow-500" onchange="filterByPrice(this.value)">
								<span class="ml-3 text-sm font-medium text-gray-700"><?php echo htmlspecialchars(getSetting('price_50_plus', '$50+')); ?></span>
                            </label>
                        </div>
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
                            <span class="font-semibold text-sm"><?php echo htmlspecialchars(getSetting('all_products', 'All Products')); ?></span>
                            <span class="bg-black bg-opacity-20 text-xs px-2 py-1 rounded-full mt-1 font-medium"><?php echo count($products); ?></span>
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
                                    // Count products in this category
                                    $categoryCount = 0;
                                    foreach ($products as $product) {
                                        if ($product['category_id'] == $category['id']) {
                                            $categoryCount++;
                                        }
                                    }
                                    ?>
                                    <button onclick="filterProducts('category-<?php echo $category['id']; ?>'); setTimeout(() => toggleCategoriesModal(), 300);"
                                            class="w-full bg-white p-4 rounded-xl hover:bg-purple-50 transition-all duration-200 flex items-center justify-between shadow-sm hover:shadow-md border border-gray-100 hover:border-purple-200 category-btn-mobile"
                                            data-category="category-<?php echo $category['id']; ?>">
                                        <div class="flex items-center flex-1 min-w-0">
                                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                <i class="fas fa-tag text-purple-600 text-sm"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <span class="font-medium text-gray-800 block truncate"><?php echo htmlspecialchars($category['name']); ?></span>
                                                <span class="text-xs text-gray-500"><?php echo $categoryCount; ?> products</span>
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
		
		<!-- Related Products Zoom Overlay -->
		<div id="relatedProductZoomOverlay" class="related-product-zoom-overlay" onclick="closeRelatedProductZoom()">
			<img id="relatedProductZoomImage" src="" alt="Zoomed Product">
		</div>
		
</body>
</html>
