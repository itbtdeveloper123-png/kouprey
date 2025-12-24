<?php
session_start();
require_once '../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Fetch current about content FIRST
$stmt = $pdo->query("SELECT * FROM about ORDER BY id DESC LIMIT 1");
$about = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];

    // Handle hero image - only update if new data provided
    $hero_image = $about['hero_image'] ?? '';
    if (!empty(trim($_POST['hero_image_url']))) {
        $hero_image = trim($_POST['hero_image_url']);
    } elseif (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == 0) {
        $upload_dir = '../public/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_extension = pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'about-hero-' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $file_path)) {
            $hero_image = '/kouprey/public/uploads/' . $file_name;
        }
    }

    // Handle person image - only update if new data provided
    $person_image = $about['person_image'] ?? '';
    if (!empty(trim($_POST['person_image_url']))) {
        $person_image = trim($_POST['person_image_url']);
    } elseif (isset($_FILES['person_image']) && $_FILES['person_image']['error'] == 0) {
        $upload_dir = '../public/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_extension = pathinfo($_FILES['person_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'about-person-' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['person_image']['tmp_name'], $file_path)) {
            $person_image = '/kouprey/public/uploads/' . $file_name;
        }
    }

    // Update about content (preserve existing data)
    if ($about) {
        $stmt = $pdo->prepare("UPDATE about SET title = ?, content = ?, hero_image = ?, person_image = ? WHERE id = ?");
        $stmt->execute([$title, $content, $hero_image, $person_image, $about['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO about (title, content, hero_image, person_image) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $hero_image, $person_image]);
    }
    $message = "About content updated successfully! Existing images were preserved unless new ones were provided.";
}

ob_start();
?>

    <div class="container mt-4">
        <h1>Manage About Page</h1>
        <div class="alert alert-info">
            <strong>How it works:</strong> When updating, only fields with new data will be changed. Existing images and content will be preserved if you leave those fields empty.
        </div>
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($about['title'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="content" class="form-label">Content</label>
                <textarea class="form-control" id="content" name="content" rows="10" required><?php echo htmlspecialchars($about['content'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="hero_image" class="form-label">About Hero Image</label>
                <?php if (!empty($about['hero_image'])): ?>
                    <div class="mb-3 p-3 border rounded bg-light">
                        <h6 class="text-muted mb-2">Current Image:</h6>
                        <img src="<?php echo htmlspecialchars($about['hero_image']); ?>" alt="Current Hero Image" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd;">
                        <p class="text-muted mt-2 mb-0 small">Leave fields below empty to keep this image.</p>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <label class="form-label small text-muted">Option 1: Upload new image from computer</label>
                    <input type="file" class="form-control" id="hero_image" name="hero_image" accept="image/*">
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted">Option 2: Use new image URL</label>
                    <input type="url" class="form-control" id="hero_image_url" name="hero_image_url" placeholder="https://example.com/image.jpg">
                    <small class="text-muted">Enter a new URL to replace the current image.</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="person_image" class="form-label">Person 1 Image</label>
                <?php if (!empty($about['person_image'])): ?>
                    <div class="mb-3 p-3 border rounded bg-light">
                        <h6 class="text-muted mb-2">Current Image:</h6>
                        <img src="<?php echo htmlspecialchars($about['person_image']); ?>" alt="Current Person Image" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd;">
                        <p class="text-muted mt-2 mb-0 small">Leave fields below empty to keep this image.</p>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <label class="form-label small text-muted">Option 1: Upload new image from computer</label>
                    <input type="file" class="form-control" id="person_image" name="person_image" accept="image/*">
                </div>
                <div class="mb-2">
                    <label class="form-label small text-muted">Option 2: Use new image URL</label>
                    <input type="url" class="form-control" id="person_image_url" name="person_image_url" placeholder="https://example.com/image.jpg">
                    <small class="text-muted">Enter a new URL to replace the current image.</small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Manage About';
$activeNav = 'management';
include 'layout.php';
?>