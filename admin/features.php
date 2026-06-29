<?php
session_start();
require_once '../app/Config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Get current language from GET or default to 'en'
$currentLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$supportedLanguages = ['en' => 'English', 'km' => 'Khmer'];

// Handle add feature
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_feature'])) {
    $title_en = $_POST['title_en'];
    $description_en = $_POST['description_en'];
    $title_km = $_POST['title_km'];
    $description_km = $_POST['description_km'];

    // Create base_feature_id
    $stmt = $pdo->prepare("INSERT INTO features (title, description, language, base_feature_id) VALUES (?, ?, 'en', NULL)");
    $stmt->execute([$title_en, $description_en]);
    $base_feature_id = $pdo->lastInsertId();

    // Update base_feature_id for English entry
    $stmt = $pdo->prepare("UPDATE features SET base_feature_id = ? WHERE id = ?");
    $stmt->execute([$base_feature_id, $base_feature_id]);

    // Add Khmer translation if provided
    if (!empty($title_km) || !empty($description_km)) {
        $stmt = $pdo->prepare("INSERT INTO features (title, description, language, base_feature_id) VALUES (?, ?, 'km', ?)");
        $stmt->execute([$title_km ?: $title_en, $description_km ?: $description_en, $base_feature_id]);
    }

    $message = "Feature added successfully!";
}

// Handle update feature
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_feature'])) {
    $base_id = $_POST['base_feature_id'];
    $title_en = $_POST['title_en'];
    $desc_en = $_POST['description_en'];
    $title_km = $_POST['title_km'];
    $desc_km = $_POST['description_km'];

    // Update/Insert English
    $stmt = $pdo->prepare("SELECT id FROM features WHERE base_feature_id = ? AND language = 'en'");
    $stmt->execute([$base_id]);
    $exists_en = $stmt->fetch();

    if ($exists_en) {
        $stmt = $pdo->prepare("UPDATE features SET title = ?, description = ? WHERE id = ?");
        $stmt->execute([$title_en, $desc_en, $exists_en['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO features (title, description, language, base_feature_id) VALUES (?, ?, 'en', ?)");
        $stmt->execute([$title_en, $desc_en, $base_id]);
    }

    // Update/Insert Khmer
    $stmt = $pdo->prepare("SELECT id FROM features WHERE base_feature_id = ? AND language = 'km'");
    $stmt->execute([$base_id]);
    $exists_km = $stmt->fetch();

    if ($exists_km) {
        $stmt = $pdo->prepare("UPDATE features SET title = ?, description = ? WHERE id = ?");
        $stmt->execute([$title_km, $desc_km, $exists_km['id']]);
    } else {
         if (!empty($title_km) || !empty($desc_km)) {
            $stmt = $pdo->prepare("INSERT INTO features (title, description, language, base_feature_id) VALUES (?, ?, 'km', ?)");
            $stmt->execute([$title_km, $desc_km, $base_id]);
         }
    }
    $message = "Feature updated successfully!";
}

// Handle delete feature
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Get base_feature_id
    $stmt = $pdo->prepare("SELECT base_feature_id FROM features WHERE id = ?");
    $stmt->execute([$id]);
    $feature = $stmt->fetch();
    if ($feature) {
        // Delete all translations
        $stmt = $pdo->prepare("DELETE FROM features WHERE base_feature_id = ?");
        $stmt->execute([$feature['base_feature_id']]);
    }
    $message = "Feature deleted successfully!";
}

// Handle product assignments
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_products'])) {
    $feature_id = $_POST['feature_id'];
    
    // Get base_feature_id
    $stmt = $pdo->prepare("SELECT base_feature_id FROM features WHERE id = ?");
    $stmt->execute([$feature_id]);
    $feature = $stmt->fetch();
    $base_feature_id = $feature ? $feature['base_feature_id'] : $feature_id;
    
    $product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];

    // Delete existing assignments for all translations of this feature
    $stmt = $pdo->prepare("DELETE FROM feature_products WHERE feature_id IN (SELECT id FROM features WHERE base_feature_id = ?)");
    $stmt->execute([$base_feature_id]);

    // Insert new assignments for all translations
    if (!empty($product_ids)) {
        $stmt = $pdo->prepare("SELECT id FROM features WHERE base_feature_id = ?");
        $stmt->execute([$base_feature_id]);
        $feature_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($feature_ids as $fid) {
            $stmt = $pdo->prepare("INSERT INTO feature_products (feature_id, product_id) VALUES (?, ?)");
            foreach ($product_ids as $product_id) {
                $stmt->execute([$fid, $product_id]);
            }
        }
    }
    $message = "Product assignments updated successfully!";
}

// Fetch all features
$stmt = $pdo->prepare("SELECT * FROM features WHERE language = ? ORDER BY id DESC");
$stmt->execute([$currentLanguage]);
$features = $stmt->fetchAll();

// Fetch all features grouped by base_feature_id for editing
$stmt = $pdo->query("SELECT * FROM features");
$allFeaturesRaw = $stmt->fetchAll();
$allFeaturesGrouped = [];
foreach ($allFeaturesRaw as $f) {
    if ($f['language'] === 'en') {
        // Ensure base_feature_id is set for the English version if it's acting as base
        $bid = $f['base_feature_id'] ?: $f['id']; 
        $allFeaturesGrouped[$bid]['en'] = $f;
    } else {
        $bid = $f['base_feature_id'];
        if ($bid) {
            $allFeaturesGrouped[$bid][$f['language']] = $f;
        }
    }
}

// Fetch all products
$currentLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$stmt = $pdo->prepare("SELECT * FROM products WHERE language = ? ORDER BY name");
$stmt->execute([$currentLanguage]);
$products = $stmt->fetchAll();

// Fetch current assignments for each feature
$assignments = [];
foreach ($features as $feature) {
    $stmt = $pdo->prepare("SELECT product_id FROM feature_products WHERE feature_id = ?");
    $stmt->execute([$feature['id']]);
    $assignments[$feature['id']] = array_column($stmt->fetchAll(), 'product_id');
}
?>

<?php ob_start(); ?>

    <style>
        .navbar-brand { font-weight: bold; }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .form-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .table-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; }
        .btn-custom { border-radius: 25px; padding: 0.5rem 1.5rem; font-weight: 500; }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        .feature-item { transition: all 0.2s; }
        .feature-item:hover { background-color: #f8f9fa; }
    </style>

    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-5 mb-0">
                        <i class="bi bi-star-fill me-3 text-warning"></i>Manage Features
                    </h1>
                    <p class="lead mb-0">Add and manage website features</p>
                </div>
                <div class="col-md-3">
                    <div class="bg-white bg-opacity-20 p-3 rounded">
                        <h3 class="mb-0 text-white"><?php echo count($features); ?></h3>
                        <small>Total Features</small>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <label for="languageSelect" class="me-2 text-white fw-bold">Language:</label>
                        <select id="languageSelect" class="form-select" style="width: auto;" onchange="changeLanguage(this.value)">
                            <?php foreach ($supportedLanguages as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo $code === $currentLanguage ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card form-card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Add New Feature
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <!-- Language Tabs -->
                            <ul class="nav nav-tabs nav-fill mb-3" id="featureLangTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="en-tab" data-bs-toggle="tab" data-bs-target="#en-content" type="button" role="tab" aria-controls="en-content" aria-selected="true">
                                        <i class="bi bi-translate me-1"></i>English
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="km-tab" data-bs-toggle="tab" data-bs-target="#km-content" type="button" role="tab" aria-controls="km-content" aria-selected="false">
                                        <i class="bi bi-translate me-1"></i>Khmer
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" id="featureLangTabContent">
                                <!-- English Content -->
                                <div class="tab-pane fade show active" id="en-content" role="tabpanel" aria-labelledby="en-tab">
                                    <div class="mb-3">
                                        <label for="title_en" class="form-label fw-bold">
                                            <i class="bi bi-tag-fill me-1 text-success"></i>Title (English) *
                                        </label>
                                        <input type="text" class="form-control" id="title_en" name="title_en" required placeholder="Enter feature title in English">
                                    </div>
                                    <div class="mb-3">
                                        <label for="description_en" class="form-label fw-bold">
                                            <i class="bi bi-textarea-resize me-1 text-success"></i>Description (English) *
                                        </label>
                                        <textarea class="form-control" id="description_en" name="description_en" rows="4" required placeholder="Describe the feature in English"></textarea>
                                    </div>
                                </div>

                                <!-- Khmer Content -->
                                <div class="tab-pane fade" id="km-content" role="tabpanel" aria-labelledby="km-tab">
                                    <div class="mb-3">
                                        <label for="title_km" class="form-label fw-bold">
                                            <i class="bi bi-tag-fill me-1 text-success"></i>Title (Khmer)
                                        </label>
                                        <input type="text" class="form-control" id="title_km" name="title_km" placeholder="បញ្ចូលចំណងជើងលក្ខណៈពិសេសជាភាសាខ្មែរ">
                                    </div>
                                    <div class="mb-3">
                                        <label for="description_km" class="form-label fw-bold">
                                            <i class="bi bi-textarea-resize me-1 text-success"></i>Description (Khmer)
                                        </label>
                                        <textarea class="form-control" id="description_km" name="description_km" rows="4" placeholder="ពិពណ៌នាអំពីលក្ខណៈពិសេសជាភាសាខ្មែរ"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="add_feature" class="btn btn-success btn-custom">
                                    <i class="bi bi-plus-lg me-2"></i>Add Feature
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card table-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>Existing Features
                        </h5>
                        <span class="badge bg-light text-dark"><?php echo count($features); ?> items</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($features)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-star display-4 mb-3"></i>
                                <h5>No features yet</h5>
                                <p>Add your first feature using the form</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="bi bi-hash me-1"></i>ID</th>
                                            <th><i class="bi bi-tag me-1"></i>Title</th>
                                            <th><i class="bi bi-textarea me-1"></i>Description</th>
                                            <th><i class="bi bi-box-seam me-1"></i>Assigned Products</th>
                                            <th class="text-center"><i class="bi bi-gear me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($features as $feature): ?>
                                            <tr class="feature-item">
                                                <td><?php echo $feature['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($feature['title']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($feature['description'], 0, 50)) . (strlen($feature['description']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo count($assignments[$feature['id']] ?? []); ?> products</span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-primary btn-sm btn-custom me-2" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#assignModal" 
                                                            onclick="loadProductsForFeature(<?php echo $feature['id']; ?>, '<?php echo htmlspecialchars($feature['title']); ?>')">
                                                        <i class="bi bi-box-seam me-1"></i>Assign
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm btn-custom me-2" 
                                                            onclick="openEditModal(<?php echo $feature['base_feature_id'] ?? $feature['id']; ?>)">
                                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                                    </button>
                                                    <a href="?delete=<?php echo $feature['id']; ?>" 
                                                       class="btn btn-danger btn-sm btn-custom" 
                                                       onclick="return confirm('Are you sure you want to delete this feature?')">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Feature Modal -->
    <div class="modal fade" id="editFeatureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-dark">
                        <i class="bi bi-pencil-square me-2"></i>Edit Feature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editFeatureForm">
                        <input type="hidden" name="base_feature_id" id="edit_base_feature_id">
                        
                        <!-- Language Tabs -->
                        <ul class="nav nav-tabs nav-fill mb-3" id="editFeatureLangTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-en-tab" data-bs-toggle="tab" data-bs-target="#edit-en-content" type="button" role="tab" aria-controls="edit-en-content" aria-selected="true">
                                    <i class="bi bi-translate me-1"></i>English
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-km-tab" data-bs-toggle="tab" data-bs-target="#edit-km-content" type="button" role="tab" aria-controls="edit-km-content" aria-selected="false">
                                    <i class="bi bi-translate me-1"></i>Khmer
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- English Content -->
                            <div class="tab-pane fade show active" id="edit-en-content" role="tabpanel">
                                <div class="mb-3">
                                    <label for="edit_title_en" class="form-label fw-bold">Title (English) *</label>
                                    <input type="text" class="form-control" id="edit_title_en" name="title_en" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_description_en" class="form-label fw-bold">Description (English) *</label>
                                    <textarea class="form-control" id="edit_description_en" name="description_en" rows="4" required></textarea>
                                </div>
                            </div>

                            <!-- Khmer Content -->
                            <div class="tab-pane fade" id="edit-km-content" role="tabpanel">
                                <div class="mb-3">
                                    <label for="edit_title_km" class="form-label fw-bold">Title (Khmer)</label>
                                    <input type="text" class="form-control" id="edit_title_km" name="title_km">
                                </div>
                                <div class="mb-3">
                                    <label for="edit_description_km" class="form-label fw-bold">Description (Khmer)</label>
                                    <textarea class="form-control" id="edit_description_km" name="description_km" rows="4"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" name="update_feature" class="btn btn-warning fw-bold">
                                <i class="bi bi-check-circle-fill me-2"></i>Update Feature
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Assignment Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalLabel">
                        <i class="bi bi-box-seam me-2"></i>Assign Products to Feature: <span id="featureTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="feature_id" id="modalFeatureId">
                        <div class="row">
                            <?php foreach ($products as $product): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input product-checkbox" 
                                               type="checkbox" 
                                               name="product_ids[]" 
                                               value="<?php echo $product['id']; ?>" 
                                               id="product_<?php echo $product['id']; ?>">
                                        <label class="form-check-label" for="product_<?php echo $product['id']; ?>">
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 60)) . (strlen($product['description']) > 60 ? '...' : ''); ?></small><br>
                                            <span class="badge bg-success">$<?php echo number_format($product['price'], 2); ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($products)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-exclamation-triangle display-4 mb-3"></i>
                                <h5>No products available</h5>
                                <p>Add products first before assigning them to features.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_products" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Save Assignments
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function changeLanguage(lang) {
            window.location.href = '?lang=' + lang;
        }

        const featuresData = <?php echo json_encode($allFeaturesGrouped); ?>;

        function loadProductsForFeature(featureId, featureTitle) {
            document.getElementById('modalFeatureId').value = featureId;
            document.getElementById('featureTitle').textContent = featureTitle;
            
            // Reset all checkboxes
            document.querySelectorAll('.product-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Load current assignments via PHP data
            const currentAssignments = <?php echo json_encode($assignments); ?>;
            if (currentAssignments[featureId]) {
                currentAssignments[featureId].forEach(productId => {
                    const checkbox = document.getElementById('product_' + productId);
                    if (checkbox) checkbox.checked = true;
                });
            }
        }

        function openEditModal(baseId) {
            const data = featuresData[baseId];
            if (!data) return;

            document.getElementById('edit_base_feature_id').value = baseId;

            // Populate English
            const enData = data['en'] || {};
            document.getElementById('edit_title_en').value = enData.title || '';
            document.getElementById('edit_description_en').value = enData.description || '';

            // Populate Khmer
            const kmData = data['km'] || {};
            document.getElementById('edit_title_km').value = kmData.title || '';
            document.getElementById('edit_description_km').value = kmData.description || '';

            // Show modal
            new bootstrap.Modal(document.getElementById('editFeatureModal')).show();
        }
    </script>

<?php
$pageContent = ob_get_clean();
$pageTitle = 'Manage Features';
$activeNav = 'management';
include 'layout.php';
