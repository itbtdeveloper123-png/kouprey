<?php
// Database configuration for MySQL connection

// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'kouprey_db');

define('DB_HOST', 'localhost');
define('DB_USER', 'samann1_kouprey');
define('DB_PASS', 'kouprey@2025');
define('DB_NAME', 'samann1_kouprey_db');


// Create PDO connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone to Phnom Penh (UTC+7)
    $pdo->exec("SET time_zone = '+07:00'");
    
    // Set character set for Khmer language support
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>