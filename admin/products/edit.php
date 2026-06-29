<?php
session_start();
require_once '../../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ../products.php');
    exit;
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ../products.php');
    exit;
}

// Handle update product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $best_seller = isset($_POST['best_seller']) ? 1 : 0;
    $image_url = $_POST['image_url'] ?? '';

    // Detailed product information
    $detailed_description = $_POST['detailed_description'] ?? '';
    $ingredients = $_POST['ingredients'] ?? '';
    $origin = $_POST['origin'] ?? '';
    $brewing_instructions = $_POST['brewing_instructions'] ?? '';
    $tasting_notes = $_POST['tasting_notes'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $roast_level = $_POST['roast_level'] ?? '';

    // Handle file upload
    $uploaded_image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../../public/assets/images/products/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $uploaded_image = '/assets/images/products/' . $file_name;
        }
    }

    // Use uploaded image if available, otherwise use URL, otherwise keep existing
    $final_image = $uploaded_image ?: ($image_url ?: $product['image']);

    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, featured = ?, best_seller = ?, image = ?, detailed_description = ?, ingredients = ?, origin = ?, brewing_instructions = ?, tasting_notes = ?, weight = ?, roast_level = ? WHERE id = ?");
    $stmt->execute([$name, $description, $price, $featured, $best_seller, $final_image, $detailed_description, $ingredients, $origin, $brewing_instructions, $tasting_notes, $weight, $roast_level, $id]);
    $message = "Product updated successfully!";

    // Refresh product data
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">Admin Panel</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Edit Product</h1>
        <a href="../products.php" class="btn btn-secondary mb-3">← Back to Products</a>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Current Image</label>
                <div>
                    <?php if ($product['image']): ?>
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Current product image" style="width: 100px; height: 100px; object-fit: cover;">
                    <?php else: ?>
                        <span class="text-muted">No image</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Upload New Image</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <div class="form-text">Upload a new image file (JPG, PNG, GIF) - this will replace the current image</div>
            </div>
            <div class="mb-3">
                <label for="image_url" class="form-label">Or New Image URL</label>
                <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg">
                <div class="form-text">Alternatively, provide a direct URL to a new image</div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" <?php echo $product['featured'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="featured">
                        Mark as Featured Product
                    </label>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="best_seller" name="best_seller" value="1" <?php echo $product['best_seller'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="best_seller">
                        Mark as Best Seller
                    </label>
                </div>
            </div>

            <h4 class="mt-4 mb-3">Detailed Product Information</h4>

            <div class="mb-3">
                <label for="detailed_description" class="form-label">Detailed Description</label>
                <textarea class="form-control" id="detailed_description" name="detailed_description" rows="4" placeholder="Provide a detailed description of the coffee..."><?php echo htmlspecialchars($product['detailed_description'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="ingredients" class="form-label">Ingredients</label>
                <textarea class="form-control" id="ingredients" name="ingredients" rows="3" placeholder="List the key ingredients..."><?php echo htmlspecialchars($product['ingredients'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="origin" class="form-label">Origin</label>
                <input type="text" class="form-control" id="origin" name="origin" placeholder="Country or region of origin" value="<?php echo htmlspecialchars($product['origin'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label for="brewing_instructions" class="form-label">Brewing Instructions</label>
                <textarea class="form-control" id="brewing_instructions" name="brewing_instructions" rows="3" placeholder="How to brew this coffee..."><?php echo htmlspecialchars($product['brewing_instructions'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="tasting_notes" class="form-label">Tasting Notes</label>
                <textarea class="form-control" id="tasting_notes" name="tasting_notes" rows="3" placeholder="Describe the flavor profile..."><?php echo htmlspecialchars($product['tasting_notes'] ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="weight" class="form-label">Weight</label>
                        <input type="text" class="form-control" id="weight" name="weight" placeholder="e.g., 250g, 500g" value="<?php echo htmlspecialchars($product['weight'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="roast_level" class="form-label">Roast Level</label>
                        <select class="form-control" id="roast_level" name="roast_level">
                            <option value="">Select roast level</option>
                            <option value="Light" <?php echo ($product['roast_level'] ?? '') == 'Light' ? 'selected' : ''; ?>>Light</option>
                            <option value="Medium-Light" <?php echo ($product['roast_level'] ?? '') == 'Medium-Light' ? 'selected' : ''; ?>>Medium-Light</option>
                            <option value="Medium" <?php echo ($product['roast_level'] ?? '') == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Medium-Dark" <?php echo ($product['roast_level'] ?? '') == 'Medium-Dark' ? 'selected' : ''; ?>>Medium-Dark</option>
                            <option value="Dark" <?php echo ($product['roast_level'] ?? '') == 'Dark' ? 'selected' : ''; ?>>Dark</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>