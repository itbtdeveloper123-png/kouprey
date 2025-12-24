-- Create settings table and insert default data
USE kouprey_db;

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
('hero_background_image', '/uploads/hero-bg-1765508631.png', 'url', 'hero', 'Hero section background image URL'),
('banner_image_1', '', 'url', 'banners', 'Banner slide 1 image'),
('banner_title_1', 'Premium Coffee Collection', 'text', 'banners', 'Banner slide 1 title'),
('banner_description_1', 'Discover our finest selection of artisanal coffee beans, carefully sourced and expertly roasted for the perfect cup every time.', 'textarea', 'banners', 'Banner slide 1 description'),
('banner_button_text_1', 'Explore Products', 'text', 'banners', 'Banner slide 1 button text'),
('banner_button_link_1', '#products', 'url', 'banners', 'Banner slide 1 button link'),
('banner_image_2', '', 'url', 'banners', 'Banner slide 2 image'),
('banner_title_2', 'Fresh Roasted Daily', 'text', 'banners', 'Banner slide 2 title'),
('banner_description_2', 'Experience the difference of daily roasted beans. Our expert roasters ensure maximum freshness and flavor in every batch.', 'textarea', 'banners', 'Banner slide 2 description'),
('banner_button_text_2', 'View Collection', 'text', 'banners', 'Banner slide 2 button text'),
('banner_button_link_2', '#products', 'url', 'banners', 'Banner slide 2 button link'),
('banner_image_3', '', 'url', 'banners', 'Banner slide 3 image'),
('banner_title_3', 'Sustainable Sourcing', 'text', 'banners', 'Banner slide 3 title'),
('banner_description_3', 'We\'re committed to ethical sourcing and sustainable farming practices. Every cup supports coffee farmers worldwide.', 'textarea', 'banners', 'Banner slide 3 description'),
('banner_button_text_3', 'Learn More', 'text', 'banners', 'Banner slide 3 button text'),
('banner_button_link_3', '#products', 'url', 'banners', 'Banner slide 3 button link'),
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
('reviews_per_page', '10', 'number', 'pagination', 'Number of reviews per page');