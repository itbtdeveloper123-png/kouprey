<?php
session_start();
require_once '../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Handle delete review
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Review deleted successfully!";
}

// Fetch all reviews with product names
$selected_product = isset($_GET['product_id']) ? $_GET['product_id'] : null;
$query = "
    SELECT r.*, p.name as product_name
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
";
if ($selected_product) {
    $query .= " WHERE r.product_id = ?";
}
$query .= " ORDER BY r.id DESC";

$stmt = $pdo->prepare($query);
if ($selected_product) {
    $stmt->execute([$selected_product]);
} else {
    $stmt->execute();
}
$reviews = $stmt->fetchAll();

// Fetch all products for the dropdown
$productStmt = $pdo->query("SELECT id, name FROM products ORDER BY name");
$products = $productStmt->fetchAll();

ob_start();
?>

    <div class="container mt-4">
        <h1>Manage Reviews</h1>
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <h3>Filter Reviews by Product</h3>
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <select class="form-control" name="product_id" onchange="this.form.submit()">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo ($selected_product == $product['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <h3 class="mt-4">Existing Reviews<?php if ($selected_product): ?> for <?php foreach ($products as $p) { if ($p['id'] == $selected_product) echo htmlspecialchars($p['name']); } ?><?php endif; ?></h3>
        <?php if (!empty($reviews)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Name</th>
                    <th>Review</th>
                    <th>Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td><?php echo $review['id']; ?></td>
                        <td><?php echo htmlspecialchars($review['product_name'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($review['name']); ?></td>
                        <td><?php echo htmlspecialchars($review['review']); ?></td>
                        <td><?php echo htmlspecialchars($review['rating']); ?></td>
                        <td>
                            <a href="?delete=<?php echo $review['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted">No reviews found.</p>
        <?php endif; ?>
    </div>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Manage Reviews';
$activeNav = 'management';
include 'layout.php';
