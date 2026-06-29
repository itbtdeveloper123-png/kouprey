-- Database setup for Kopres project
-- Run this in phpMyAdmin or MySQL console

CREATE DATABASE IF NOT EXISTS kouprey_db;
USE kouprey_db;

-- About table
CREATE TABLE about (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    hero_image VARCHAR(500),
    person_image VARCHAR(500)
);

-- Features table
CREATE TABLE features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL
);

-- Feature products relationship table
CREATE TABLE feature_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_feature_product (feature_id, product_id)
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    enabled TINYINT(1) DEFAULT 1
);

-- Reviews table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    review TEXT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Settings table for front-end configuration
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'email', 'url', 'boolean') DEFAULT 'text',
    category VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO about (title, content) VALUES ('About Us', 'Welcome to Kopres. We are a company dedicated to providing quality products. We believe in sustainability, quality, and customer satisfaction. Our journey began with a passion for excellence and continues with a commitment to innovation.');
INSERT INTO features (title, description) VALUES ('Quality', 'High quality products sourced from the best suppliers'), ('Service', 'Excellent customer service with fast response times'), ('Sustainability', 'Eco-friendly practices and sustainable sourcing'), ('Innovation', 'Constantly improving our products and processes');
INSERT INTO products (name, description, price) VALUES ('Premium Coffee Beans', 'Freshly roasted Arabica beans with rich flavor', 24.99), ('Organic Ground Coffee', 'Finely ground organic coffee for your daily brew', 18.99), ('Coffee Subscription', 'Monthly delivery of our finest coffee blends', 45.00);
INSERT INTO reviews (name, review, rating) VALUES ('John Doe', 'Great product! Highly recommend this coffee.', 5), ('Jane Smith', 'Excellent quality and fast shipping. Will buy again!', 5), ('Mike Johnson', 'Good coffee but packaging could be better.', 4);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, description) VALUES
('site_title', 'KouPrey Coffee', 'text', 'general', 'Main website title'),
('site_description', 'Premium coffee beans and sustainable brewing solutions', 'textarea', 'general', 'Website meta description'),
('site_keywords', 'coffee, premium, organic, sustainable', 'text', 'general', 'SEO keywords'),
('company_name', 'KouPrey Coffee Co.', 'text', 'general', 'Full company name'),
('company_email', 'info@kouprey.com', 'email', 'contact', 'Primary contact email'),
('company_phone', '+855 12 345 678', 'text', 'contact', 'Contact phone number'),
('company_address', 'Phnom Penh, Cambodia', 'textarea', 'contact', 'Company address'),
('hero_title', 'Discover The Finest Coffee', 'text', 'hero', 'Main hero section title'),
('hero_subtitle', 'At KouPrey Coffee, we believe that every cup of coffee should be a journey. We source the finest beans and craft exceptional blends.', 'textarea', 'hero', 'Hero section subtitle'),
('hero_cta_text', 'Shop Now', 'text', 'hero', 'Hero call-to-action button text'),
('hero_cta_link', '#featured', 'url', 'hero', 'Hero CTA button link'),
('newsletter_title', 'Get 10% Discount', 'text', 'newsletter', 'Newsletter section title'),
('newsletter_description', 'Subscribe to our newsletter to receive discounts and latest product news.', 'textarea', 'newsletter', 'Newsletter section description'),
('newsletter_button_text', 'Subscribe', 'text', 'newsletter', 'Newsletter button text'),
('footer_text', '© 2025 KouPrey. All rights reserved.', 'text', 'footer', 'Footer copyright text'),
('social_facebook', 'https://facebook.com/koupreycoffee', 'url', 'social', 'Facebook page URL'),
('social_instagram', 'https://instagram.com/koupreycoffee', 'url', 'social', 'Instagram page URL'),
('social_twitter', 'https://twitter.com/koupreycoffee', 'url', 'social', 'Twitter page URL'),
('enable_newsletter', '1', 'boolean', 'features', 'Enable newsletter subscription'),
('enable_social_links', '1', 'boolean', 'features', 'Show social media links'),
('products_per_page', '12', 'number', 'pagination', 'Number of products per page'),
('reviews_per_page', '10', 'number', 'pagination', 'Number of reviews per page'),
('nav_product', 'Product', 'text', 'navigation', 'Product navigation label'),
('nav_features', 'Features', 'text', 'navigation', 'Features navigation label'),
('nav_reviews', 'Reviews', 'text', 'navigation', 'Reviews navigation label'),
('nav_about', 'About us', 'text', 'navigation', 'About navigation label');