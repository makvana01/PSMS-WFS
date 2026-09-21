-- ==========================================================
-- SAFE MIGRATION SCRIPT FOR EXISTING DATABASE
-- Smart Campus Placement Management System (PMS)
-- Run this in phpMyAdmin (SQL tab) or MySQL CLI
-- ==========================================================

USE placement_management;

-- 1. Optimize Table: users
ALTER TABLE users 
    MODIFY name VARCHAR(100) NOT NULL,
    MODIFY email VARCHAR(100) NOT NULL,
    MODIFY password VARCHAR(100) NOT NULL,
    MODIFY otp VARCHAR(6) NULL DEFAULT NULL,
    MODIFY remember_token VARCHAR(100) NULL DEFAULT NULL;

-- Add index if not already present
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_user_role_status');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE users ADD INDEX idx_user_role_status (role, is_verified, deleted_at)', 'SELECT "Index idx_user_role_status already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 2. Optimize Table: students (Remove hardcoded defaults, set NULL, clean lengths)
ALTER TABLE students 
    ALTER department DROP DEFAULT,
    ALTER passing_year DROP DEFAULT,
    ALTER cgpa DROP DEFAULT;

ALTER TABLE students 
    MODIFY phone VARCHAR(15) NULL DEFAULT NULL,
    MODIFY department VARCHAR(100) NULL DEFAULT NULL,
    MODIFY passing_year INT(4) NULL DEFAULT NULL,
    MODIFY cgpa DECIMAL(3,2) NULL DEFAULT NULL,
    MODIFY resume_url VARCHAR(255) NULL DEFAULT NULL,
    MODIFY id_card_url VARCHAR(255) NULL DEFAULT NULL,
    MODIFY profile_pic_url VARCHAR(255) NULL DEFAULT NULL;

-- Add indexes on students
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'students' AND index_name = 'idx_student_department');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE students ADD INDEX idx_student_department (department)', 'SELECT "Index idx_student_department already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'students' AND index_name = 'idx_student_audit');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE students ADD INDEX idx_student_audit (is_verified)', 'SELECT "Index idx_student_audit already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 3. Optimize Table: companies
ALTER TABLE companies 
    MODIFY company_name VARCHAR(150) NOT NULL,
    MODIFY industry VARCHAR(50) NULL DEFAULT NULL,
    MODIFY location VARCHAR(150) NULL DEFAULT NULL,
    MODIFY website VARCHAR(150) NULL DEFAULT NULL;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'companies' AND index_name = 'idx_company_name');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE companies ADD INDEX idx_company_name (company_name)', 'SELECT "Index idx_company_name already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 4. Optimize Table: jobs
ALTER TABLE jobs 
    MODIFY company_name VARCHAR(150) NOT NULL,
    MODIFY title VARCHAR(100) NOT NULL,
    MODIFY location VARCHAR(150) NOT NULL,
    MODIFY salary VARCHAR(50) NOT NULL,
    MODIFY eligibility VARCHAR(200) NOT NULL;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'jobs' AND index_name = 'idx_jobs_filter');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE jobs ADD INDEX idx_jobs_filter (approval_status, status, last_date)', 'SELECT "Index idx_jobs_filter already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'jobs' AND index_name = 'idx_jobs_company');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE jobs ADD INDEX idx_jobs_company (company_id, deleted_at)', 'SELECT "Index idx_jobs_company already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 5. Optimize Table: applications (Add Unique constraint and indexes)
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'applications' AND index_name = 'uq_job_student');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE applications ADD UNIQUE KEY uq_job_student (job_id, student_id)', 'SELECT "Unique constraint uq_job_student already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'applications' AND index_name = 'idx_app_student_status');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE applications ADD INDEX idx_app_student_status (student_id, status)', 'SELECT "Index idx_app_student_status already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'applications' AND index_name = 'idx_app_job_status');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE applications ADD INDEX idx_app_job_status (job_id, status)', 'SELECT "Index idx_app_job_status already exists"');
PREPARE stmt FROM @sqlstmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Conversion complete
SELECT "Database migration successfully updated!" AS status;
