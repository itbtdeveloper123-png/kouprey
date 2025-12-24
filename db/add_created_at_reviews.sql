-- Add created_at column to reviews table if it doesn't exist
USE kouprey_db;

-- Add created_at column with default timestamp
ALTER TABLE reviews ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;