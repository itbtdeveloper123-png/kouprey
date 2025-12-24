<?php
// Database setup script
// This script will create the database and tables automatically

try {
    // Connect without specifying database to create it
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS kouprey_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Now connect to the database
    $pdo = new PDO("mysql:host=localhost;dbname=kouprey_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS about (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS features (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            review TEXT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5)
        )",
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            status ENUM('active', 'inactive') DEFAULT 'active'
        )"
    ];

    foreach ($tables as $table) {
        $pdo->exec($table);
    }

    // Insert sample data (only if tables are empty)
    $pdo->exec("INSERT IGNORE INTO about (id, title, content) VALUES (1, 'About Us', 'Welcome to Kopres. We are a company dedicated to providing quality products.')");
    $pdo->exec("INSERT IGNORE INTO features (title, description) VALUES ('Quality', 'High quality products'), ('Service', 'Excellent customer service')");
    $pdo->exec("INSERT IGNORE INTO products (name, description, price) VALUES ('Product 1', 'Description of product 1', 10.00)");
    $pdo->exec("INSERT IGNORE INTO reviews (name, review, rating) VALUES ('John Doe', 'Great product!', 5)");

    // Create default admin user if not exists
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@kopres.com', $hashed_password, 'Administrator']);

    echo "Database and tables created successfully! Default admin account: admin / admin123";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>