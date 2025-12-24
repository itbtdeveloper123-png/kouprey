-- Add custom_name column and modify related_product_id to allow NULL
ALTER TABLE product_related ADD COLUMN IF NOT EXISTS custom_name VARCHAR(255) DEFAULT '' AFTER custom_url;
ALTER TABLE product_related MODIFY COLUMN related_product_id INT NULL;