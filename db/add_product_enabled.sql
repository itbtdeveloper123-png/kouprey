-- Add enabled column to products table to allow enabling/disabling products
ALTER TABLE products ADD COLUMN IF NOT EXISTS enabled TINYINT(1) DEFAULT 1 AFTER best_seller;

-- Ensure existing rows default to enabled
UPDATE products SET enabled = 1 WHERE enabled IS NULL;
