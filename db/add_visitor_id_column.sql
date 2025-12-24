-- Add visitor_id column to visitors table for persistent tracking
-- Run this in phpMyAdmin or MySQL console

USE kouprey_db;

-- Add visitor_id column to visitors table
ALTER TABLE visitors ADD COLUMN visitor_id VARCHAR(32) NOT NULL AFTER session_id;

-- Create index for better performance
CREATE INDEX idx_visitor_id_date ON visitors (visitor_id, visit_date);

-- Update existing records with a generated visitor_id (optional - for existing data)
-- This will give existing visitors a persistent ID based on their session
UPDATE visitors SET visitor_id = MD5(CONCAT(session_id, ip_address)) WHERE visitor_id = '' OR visitor_id IS NULL;