-- EduPlan AI — MySQL/MariaDB schema
-- Run this once if you'd rather import it manually than rely on
-- auto-creation (includes/db.php creates these same tables automatically
-- on first request if they don't exist yet).
--
--   mysql -u root -p eduplan_ai < schema.sql

CREATE DATABASE IF NOT EXISTS eduplan_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eduplan_ai;

CREATE TABLE IF NOT EXISTS auth_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'Admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'Teacher',
    department VARCHAR(190) DEFAULT NULL,
    classes INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A "resource" is either a syllabus or a book/reference text, uploaded by a
-- user. Both are used by the AI Generator as grounding material. This table
-- is intentionally never seeded — every row exists only because a user
-- uploaded it through the Resource Library.
CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'syllabus',
    subject VARCHAR(190) DEFAULT NULL,
    class VARCHAR(190) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Processing',
    file_path VARCHAR(500) DEFAULT NULL,
    extracted_text MEDIUMTEXT DEFAULT NULL,
    extraction_note VARCHAR(255) DEFAULT NULL,
    uploaded_by VARCHAR(190) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS generated_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(30) NOT NULL,
    title VARCHAR(255) NOT NULL,
    meta VARCHAR(255) DEFAULT NULL,
    content MEDIUMTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY,
    name VARCHAR(190) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    school VARCHAR(190) DEFAULT NULL,
    language VARCHAR(50) NOT NULL DEFAULT 'English',
    notifications TINYINT(1) DEFAULT 1,
    ai_suggestions TINYINT(1) DEFAULT 1,
    weekly_report TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE settings ADD COLUMN IF NOT EXISTS language VARCHAR(50) NOT NULL DEFAULT 'English';

INSERT IGNORE INTO settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS mail_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    delivered_at DATETIME DEFAULT NULL,
    error_message VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mail_pending (delivered_at, attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
