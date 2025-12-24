-- Add language support to categories table
ALTER TABLE categories ADD COLUMN language VARCHAR(5) DEFAULT 'en' AFTER image;
ALTER TABLE categories ADD COLUMN base_category_id INT DEFAULT NULL AFTER language;

-- Update existing categories to set base_category_id to their own id
UPDATE categories SET base_category_id = id WHERE base_category_id IS NULL;

-- Add index for better performance
CREATE INDEX idx_categories_base_lang ON categories (base_category_id, language);

-- Note: Remove UNIQUE constraint on name since names can be duplicated across languages
ALTER TABLE categories DROP INDEX name;
ALTER TABLE categories ADD INDEX idx_categories_name_lang (name, language);

-- Insert Khmer translations for existing categories (you can modify these)
-- Note: This is sample data, adjust as needed
INSERT INTO categories (name, description, image, language, base_category_id)
SELECT
    'ប្រភេទគូហ្វី' as name,
    'ប្រភេទគូហ្វីផ្សេងៗ' as description,
    image, 'km' as language, id as base_category_id
FROM categories WHERE language = 'en';