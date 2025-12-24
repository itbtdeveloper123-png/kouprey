-- Add custom_fields column to products table for dynamic detailed information
ALTER TABLE products ADD COLUMN custom_fields JSON DEFAULT NULL;

-- Update existing records to have empty JSON object if needed
UPDATE products SET custom_fields = '{}' WHERE custom_fields IS NULL;