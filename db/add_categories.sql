-- Add categories table and update products table
-- Run this after setup.sql

USE kouprey_db;

-- Create categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add category_id to products table
ALTER TABLE products ADD COLUMN category_id INT DEFAULT NULL AFTER roast_level;
ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Add more columns to products table for better organization
ALTER TABLE products ADD COLUMN featured TINYINT(1) DEFAULT 0 AFTER price;
ALTER TABLE products ADD COLUMN best_seller TINYINT(1) DEFAULT 0 AFTER featured;
ALTER TABLE products ADD COLUMN image VARCHAR(500) AFTER best_seller;
ALTER TABLE products ADD COLUMN detailed_description TEXT AFTER image;
ALTER TABLE products ADD COLUMN ingredients TEXT AFTER detailed_description;
ALTER TABLE products ADD COLUMN origin VARCHAR(255) AFTER ingredients;
ALTER TABLE products ADD COLUMN brewing_instructions TEXT AFTER origin;
ALTER TABLE products ADD COLUMN tasting_notes TEXT AFTER brewing_instructions;
ALTER TABLE products ADD COLUMN weight VARCHAR(50) AFTER tasting_notes;
ALTER TABLE products ADD COLUMN roast_level VARCHAR(50) AFTER weight;

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Coffee Beans', 'Premium coffee beans from around the world'),
('Ground Coffee', 'Finely ground coffee for easy brewing'),
('Coffee Accessories', 'Tools and equipment for coffee brewing'),
('Tea & Alternatives', 'Tea blends and coffee alternatives'),
('Subscription Boxes', 'Monthly coffee delivery services');

-- Update existing products with categories (assuming they exist)
UPDATE products SET category_id = 1 WHERE name LIKE '%bean%' OR name LIKE '%Bean%';
UPDATE products SET category_id = 2 WHERE name LIKE '%ground%' OR name LIKE '%Ground%';
UPDATE products SET category_id = 5 WHERE name LIKE '%subscription%' OR name LIKE '%Subscription%';