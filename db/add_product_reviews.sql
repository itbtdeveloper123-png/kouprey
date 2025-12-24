-- Add product_id to reviews table to link reviews to products
USE kouprey_db;

-- Add product_id column to reviews table (nullable first)
ALTER TABLE reviews ADD COLUMN product_id INT NULL AFTER id;

-- Update existing reviews to have a default product_id (assuming product id 1 exists)
-- You may need to adjust this based on your products
UPDATE reviews SET product_id = 1 WHERE product_id IS NULL;

-- Now make it NOT NULL
ALTER TABLE reviews MODIFY COLUMN product_id INT NOT NULL;

-- Add foreign key constraint
ALTER TABLE reviews ADD CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE;

-- Add index for better performance
CREATE INDEX idx_reviews_product_id ON reviews(product_id);