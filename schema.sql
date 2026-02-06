-- SuperStudy Database Schema
-- Run this file to create the required database and tables
-- Usage: mysql -u root -p < schema.sql

-- Create database
CREATE DATABASE IF NOT EXISTS superstudy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE superstudy;

-- ============================================
-- Users Table
-- Stores registered users with hashed passwords
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- ============================================
-- Projects Table
-- Stores user projects with AI provider settings
-- API keys are stored encrypted
-- ============================================
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ai_provider ENUM('openai', 'anthropic', 'google-gemini', 'xai-grok', 'openrouter') NOT NULL DEFAULT 'openai',
    model_name VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    api_key TEXT NOT NULL COMMENT 'Encrypted API key',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- ============================================
-- Documents Table
-- Stores uploaded document metadata and extracted text
-- ============================================
CREATE TABLE IF NOT EXISTS documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    extracted_text LONGTEXT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id)
) ENGINE=InnoDB;

-- ============================================
-- Generated Content Table
-- Stores AI-generated study materials
-- ============================================
CREATE TABLE IF NOT EXISTS generated_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    document_id INT UNSIGNED,
    type ENUM('summary', 'notes', 'quiz', 'flashcards') NOT NULL,
    content_text LONGTEXT NOT NULL,
    prompt_used TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_document_id (document_id),
    INDEX idx_type (type)
) ENGINE=InnoDB;

-- ============================================
-- Session tracking (optional, for future Telegram bot integration)
-- ============================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;
