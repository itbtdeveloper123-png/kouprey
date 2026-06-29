<?php
require_once 'app/Config/database.php';

$translations = [
    'footer_quick_links' => 'តំណភ្ជាប់រហ័ស',
    'footer_home' => 'ទំព័រដើម',
    'footer_products' => 'ផលិតផល',
    'footer_about_us' => 'អំពីយើង',
    'footer_reviews' => 'មតិយោបល់',
    'footer_admin' => 'អ្នកគ្រប់គ្រង',
    'footer_connect_with_us' => 'ភ្ជាប់ទំនាក់ទំនងជាមួយយើង',
    'footer_stay_updated' => 'ទទួលបានព័ត៌មានថ្មីៗ',
    'footer_enter_email' => 'បញ្ចូលអ៊ីមែលរបស់អ្នក',
    'footer_privacy_policy' => 'គោលការណ៍ឯកជនភាព',
    'footer_terms_of_service' => 'លក្ខខណ្ឌប្រើប្រាស់',
    'footer_contact_us' => 'ទាក់ទងមកយើង',
    'footer_quick_links' => 'តំណភ្ជាប់រហ័ស',
];

foreach ($translations as $key => $value) {
    // Check if it exists for KM
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND language = 'km'");
    $stmt->execute([$key]);
    if ($stmt->fetch()) {
        // Update
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND language = 'km'");
        $stmt->execute([$value, $key]);
        echo "Updated $key for KM\n";
    } else {
        // Insert (find category from EN version first)
        $stmt = $pdo->prepare("SELECT category FROM settings WHERE setting_key = ? AND language = 'en' LIMIT 1");
        $stmt->execute([$key]);
        $en_row = $stmt->fetch();
        $category = $en_row ? $en_row['category'] : 'footer';
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, category, language) VALUES (?, ?, ?, 'km')");
        $stmt->execute([$key, $value, $category]);
        echo "Inserted $key for KM\n";
    }
}
echo "Done.\n";
