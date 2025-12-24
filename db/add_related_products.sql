-- Add related products table
CREATE TABLE product_related (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    related_product_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_related (product_id, related_product_id)
);

-- Add index for better performance
CREATE INDEX idx_product_related_product ON product_related (product_id);
CREATE INDEX idx_product_related_related ON product_related (related_product_id);