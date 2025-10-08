-- Migration: Create login_attempts table for rate limiting
-- Purpose: Track failed login/register attempts to prevent brute force attacks
-- Date: 2024

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL COMMENT 'Email or IP address',
    attempt_type ENUM('login', 'register') NOT NULL DEFAULT 'login',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    user_agent VARCHAR(500) DEFAULT NULL,
    INDEX idx_identifier_type (identifier, attempt_type),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cleanup old attempts (older than 1 day) - can be run periodically
-- DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
