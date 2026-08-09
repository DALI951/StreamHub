CREATE DATABASE IF NOT EXISTS streamhub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE streamhub_db;

CREATE TABLE IF NOT EXISTS sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    base_url VARCHAR(255) NOT NULL,
    scraper_class VARCHAR(100) NOT NULL,
    priority INT DEFAULT 0,
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO sources (name, base_url, scraper_class, priority, enabled) VALUES
('egydead', 'https://www.egydead.today', 'EgyDeadScraper', 1, 1),
('cima4u', 'https://www.cima4ua.top', 'Cima4uScraper', 2, 1),
('topcinema', 'https://topcinema.fan', 'TopCinemaScraper', 3, 1),
('faselhd', 'https://www.faselhd.com', 'FaselHDScraper', 4, 1),
('mycima', 'https://mycima.win', 'MyCimaScraper', 5, 1),
('arabseed', 'https://arabseed.show', 'ArabSeedScraper', 6, 1),
('akwam', 'https://akwam.ws', 'AkwamScraper', 7, 1)
ON DUPLICATE KEY UPDATE priority = VALUES(priority);

CREATE TABLE IF NOT EXISTS cache_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(1024) NOT NULL,
    source VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    title_ar VARCHAR(500) DEFAULT NULL,
    type ENUM('movie', 'series', 'season', 'episode', 'anime') DEFAULT 'movie',
    year VARCHAR(10) DEFAULT NULL,
    poster VARCHAR(1024) DEFAULT NULL,
    banner VARCHAR(1024) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    rating FLOAT DEFAULT NULL,
    seasons_data JSON DEFAULT NULL,
    extra_data JSON DEFAULT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    UNIQUE KEY idx_url_source (url(500), source),
    INDEX idx_title (title(100)),
    INDEX idx_type (type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cache_streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_url VARCHAR(1024) NOT NULL,
    source VARCHAR(50) NOT NULL,
    quality VARCHAR(20) DEFAULT NULL,
    quality_label VARCHAR(20) DEFAULT NULL,
    stream_url VARCHAR(2048) NOT NULL,
    stream_type ENUM('hls', 'mp4', 'direct', 'iframe') DEFAULT 'hls',
    referer VARCHAR(1024) DEFAULT NULL,
    server_name VARCHAR(100) DEFAULT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_content (content_url(500), source),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS search_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_hash VARCHAR(64) NOT NULL UNIQUE,
    query_text VARCHAR(500) NOT NULL,
    results JSON NOT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_hash (query_hash),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;
