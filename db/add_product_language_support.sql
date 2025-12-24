-- Add language support to products table
ALTER TABLE products ADD COLUMN language VARCHAR(5) DEFAULT 'en' AFTER roast_level;

-- Add new unique constraint on (id, language) - but actually, since id is primary key, we need to think differently
-- For products, we might want to allow multiple translations per product, so perhaps change the primary key or add a base_product_id

-- Actually, let's modify the approach: add a base_product_id column
-- Each product can have multiple language versions, linked by base_product_id
ALTER TABLE products ADD COLUMN base_product_id INT DEFAULT NULL AFTER language;

-- Update existing products to set base_product_id to their own id
UPDATE products SET base_product_id = id WHERE base_product_id IS NULL;

-- Add index for better performance
CREATE INDEX idx_products_base_lang ON products (base_product_id, language);

-- Insert Khmer translations for existing products (you can modify these)
-- Note: This is sample data, adjust as needed
INSERT INTO products (name, description, price, category_id, featured, best_seller, image, detailed_description, ingredients, origin, brewing_instructions, tasting_notes, weight, roast_level, language, base_product_id)
SELECT
    'គូហ្វីប៉្រីមីអាំងប៊ីន' as name,
    'គូហ្វីប៉្រីមីអាំងប៊ីនដែលថ្មីឆ្អិនជាមួយរសជាតិខ្លាំង' as description,
    price, category_id, featured, best_seller, image, detailed_description, ingredients, origin, brewing_instructions, tasting_notes, weight, roast_level, 'km' as language, id as base_product_id
FROM products WHERE language = 'en';