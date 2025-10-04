-- Visitor Tracker Database Schema
-- Database: tracker_db

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS tracker_db 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE tracker_db;

-- Visitor logs table
CREATE TABLE IF NOT EXISTS visitor_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- IP Information
    ip VARCHAR(45) NOT NULL COMMENT 'Actual client IP address (IPv4 or IPv6)',
    geo_ip VARCHAR(45) NULL COMMENT 'IP used for geolocation lookup (fallback to 8.8.8.8 for localhost)',
    
    -- Geolocation Data
    country VARCHAR(100) NULL COMMENT 'Country name from MaxMind GeoIP',
    city VARCHAR(100) NULL COMMENT 'City name from MaxMind GeoIP',
    latitude DECIMAL(10, 8) NULL COMMENT 'Latitude coordinates',
    longitude DECIMAL(11, 8) NULL COMMENT 'Longitude coordinates',
    
    -- Request Information
    user_agent TEXT NULL COMMENT 'Browser user agent string',
    referer TEXT NULL COMMENT 'HTTP referer (previous page)',
    url TEXT NULL COMMENT 'Requested URL path',
    
    -- Metadata
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Visit timestamp',
    
    -- Indexes for performance
    INDEX idx_ip (ip),
    INDEX idx_geo_ip (geo_ip),
    INDEX idx_visited_at (visited_at),
    INDEX idx_country (country)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores visitor tracking data with geolocation information';
