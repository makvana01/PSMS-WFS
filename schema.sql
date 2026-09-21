-- ==========================================================
-- SMART CAMPUS PLACEMENT MANAGEMENT SYSTEM (PMS)
-- Relational Database Schema Specification
-- Storage Engine: InnoDB | Character Set: utf8mb4
-- ==========================================================

CREATE DATABASE IF NOT EXISTS placement_management
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE placement_management;

-- ----------------------------------------------------------
-- 1. Table: users
-- Core authentication, identity credentials, and access roles
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student', 'company') DEFAULT 'student',
    is_verified BOOLEAN DEFAULT FALSE,
    otp VARCHAR(6) NULL DEFAULT NULL,
    otp_expires DATETIME NULL DEFAULT NULL,
    remember_token VARCHAR(100) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_user_role_status (role, is_verified, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Table: students
-- Academic details, documents, and institutional audit status
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone VARCHAR(15) NULL DEFAULT NULL,
    department VARCHAR(100) NULL DEFAULT NULL,
    passing_year INT(4) NULL DEFAULT NULL,
    cgpa DECIMAL(3,2) NULL DEFAULT NULL,
    skills TEXT NULL DEFAULT NULL,
    resume_url VARCHAR(255) NULL DEFAULT NULL,
    id_card_url VARCHAR(255) NULL DEFAULT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    bio TEXT NULL DEFAULT NULL,
    profile_pic_url VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_department (department),
    INDEX idx_student_audit (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Table: companies
-- Corporate recruitment profiles and industry classification
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    company_name VARCHAR(150) NOT NULL,
    industry VARCHAR(50) NULL DEFAULT NULL,
    location VARCHAR(150) NULL DEFAULT NULL,
    website VARCHAR(150) NULL DEFAULT NULL,
    description TEXT NULL DEFAULT NULL,
    logo_url VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_company_name (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Table: jobs
-- Recruitment drives, eligibility criteria, and moderation
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    title VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    salary VARCHAR(50) NOT NULL,
    eligibility VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    last_date DATE NOT NULL,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (company_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_jobs_filter (approval_status, status, last_date),
    INDEX idx_jobs_company (company_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Table: applications
-- Mapping students to job drives with stage tracking
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('Pending', 'Reviewed', 'Shortlisted', 'Rejected', 'Selected') DEFAULT 'Pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_job_student (job_id, student_id),
    INDEX idx_app_student_status (student_id, status),
    INDEX idx_app_job_status (job_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Default Placement Administrator Seed Account
-- Email: admin@example.com | Password: password
-- ----------------------------------------------------------
INSERT INTO users (name, email, password, role, is_verified) 
VALUES ('System Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
