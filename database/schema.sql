CREATE DATABASE IF NOT EXISTS ebook_platform;
USE ebook_platform;

-- ======================
-- USERS
-- ======================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),
    role ENUM('reader','author','admin') DEFAULT 'reader',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================
-- BOOKS
-- ======================
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(200),
    description TEXT,
    cover_image VARCHAR(255),
    category VARCHAR(100),
    type ENUM('novel','comic') DEFAULT 'novel',
    price DECIMAL(10,2) DEFAULT 0,
    is_published TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ======================
-- CHAPTERS
-- ======================
CREATE TABLE chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT,
    title VARCHAR(200),
    content LONGTEXT,
    chapter_no INT,
    price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id)
);

-- ======================
-- COMIC IMAGES
-- ======================
CREATE TABLE chapter_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id INT,
    image_path VARCHAR(255),
    page_no INT,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id)
);

-- ======================
-- PURCHASES
-- ======================
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    chapter_id INT,
    price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (chapter_id) REFERENCES chapters(id)
);

-- ======================
-- WALLET
-- ======================
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    type ENUM('credit','debit'),
    amount DECIMAL(10,2),
    reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ======================
-- PAYMENTS (RAZORPAY)
-- ======================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_id VARCHAR(100),
    payment_id VARCHAR(100),
    amount DECIMAL(10,2),
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ======================
-- EARNINGS
-- ======================
CREATE TABLE earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT,
    chapter_id INT,
    amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (chapter_id) REFERENCES chapters(id)
);

-- ======================
-- BANK DETAILS
-- ======================
CREATE TABLE bank_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    account_name VARCHAR(100),
    account_number VARCHAR(50),
    ifsc_code VARCHAR(20),
    bank_name VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ======================
-- COMMENTS
-- ======================
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    book_id INT,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);
-- ======================
-- UPLOADED BOOK FILES (PDF / EPUB / ZIP / COMIC FILES)
-- ======================
CREATE TABLE uploaded_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    book_id INT,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    file_type VARCHAR(50),
    file_size BIGINT,
    price DECIMAL(10,2) DEFAULT 0,
    is_published TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);
