-- Add visitors tracking table to KouPrey database
-- Run this in phpMyAdmin or MySQL console

USE kouprey_db;

-- Visitors table for tracking website visitors
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    page_url VARCHAR(500),
    referrer VARCHAR(500),
    session_id VARCHAR(255),
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visit_date (visit_date),
    INDEX idx_ip_address (ip_address),
    INDEX idx_session_id (session_id)
);

-- Daily visitor summary table for quick statistics
CREATE TABLE IF NOT EXISTS visitor_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    total_visitors INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    page_views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date)
);

-- Insert sample data for demonstration (optional)
INSERT INTO visitor_stats (date, total_visitors, unique_visitors, page_views) VALUES
(CURDATE(), 150, 120, 280),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 145, 118, 275),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 138, 112, 260);