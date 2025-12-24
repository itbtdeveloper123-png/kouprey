-- Add feature_products table to existing database
USE kouprey_db;

-- Feature products relationship table
CREATE TABLE IF NOT EXISTS feature_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_feature_product (feature_id, product_id)
);