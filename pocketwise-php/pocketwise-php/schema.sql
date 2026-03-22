-- PocketWise Database Schema
-- Run this file once to set up the database

CREATE DATABASE IF NOT EXISTS pocketwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pocketwise;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(64) PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) DEFAULT '',
    currency VARCHAR(10) DEFAULT '₹',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100) DEFAULT NULL,
    color VARCHAR(20) DEFAULT NULL
);

-- Insert default categories (single canonical set)
INSERT IGNORE INTO categories (id, name, icon, color) VALUES
('food',          'Food & Dining',    'utensils',    '#ff6b6b'),
('transport',     'Transportation',   'car',          '#4ecdc4'),
('shopping',      'Shopping',         'shopping-bag', '#45b7d1'),
('entertainment', 'Entertainment',    'film',         '#f9ca24'),
('bills',         'Bills & Utilities','bolt',         '#6c5ce7'),
('health',        'Health & Fitness', 'heartbeat',    '#a29bfe'),
('education',     'Education',        'book',         '#fd79a8'),
('travel',        'Travel',           'plane',        '#00b894'),
('other',         'Other',            'ellipsis-h',   '#636e72');

-- Budgets table
CREATE TABLE IF NOT EXISTS budgets (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    type ENUM('daily','monthly','category') NOT NULL,
    category_id VARCHAR(64) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    month VARCHAR(7) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('expense','income') NOT NULL DEFAULT 'expense',
    category_id VARCHAR(64) DEFAULT NULL,
    merchant VARCHAR(255) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    payment_method VARCHAR(100) DEFAULT 'Cash',
    date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Profiles table
CREATE TABLE IF NOT EXISTS profiles (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL UNIQUE,
    full_name VARCHAR(255) DEFAULT '',
    currency VARCHAR(10) DEFAULT '₹',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chat history table
CREATE TABLE IF NOT EXISTS chat_history (
    id VARCHAR(64) PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    user_message TEXT NOT NULL,
    assistant_reply TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);



