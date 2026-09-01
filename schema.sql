CREATE DATABASE IF NOT EXISTS attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty', 'student') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    department VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users
MODIFY role ENUM('admin', 'faculty', 'student') NOT NULL;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(50) NOT NULL UNIQUE,
    student_name VARCHAR(150) NOT NULL,
    roll_no VARCHAR(50) NOT NULL UNIQUE,
    reg_no VARCHAR(50) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL,
    section VARCHAR(5) NOT NULL DEFAULT 'A',
    year_of_study TINYINT NOT NULL,
    batch VARCHAR(20) NOT NULL,
    category ENUM('MQ', 'GQ') NOT NULL,
    scholar_type ENUM('Dayscholar', 'Hosteller') NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_date DATE NOT NULL,
    period VARCHAR(30) NOT NULL,
    department VARCHAR(100) NOT NULL,
    year_of_study TINYINT NOT NULL,
    batch VARCHAR(20) NOT NULL,
    subject_name VARCHAR(120) NULL,
    faculty_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session (attendance_date, period, department, year_of_study, batch),
    CONSTRAINT fk_session_faculty FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('Present', 'Absent', 'OD') NOT NULL DEFAULT 'Present',
    semester INT DEFAULT 1,
    remarks VARCHAR(255) NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_student (session_id, student_id),
    CONSTRAINT fk_record_session FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_record_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

ALTER TABLE attendance_records
ADD COLUMN IF NOT EXISTS semester INT DEFAULT 1;

-- Set all existing records to semester 1 if NULL
UPDATE attendance_records
SET semester = 1
WHERE semester IS NULL;

ALTER TABLE students
ADD COLUMN IF NOT EXISTS section VARCHAR(5) NOT NULL DEFAULT 'A' AFTER department;

UPDATE students
SET section = 'A'
WHERE section IS NULL OR TRIM(section) = '';

ALTER TABLE students
MODIFY section VARCHAR(5) NOT NULL DEFAULT 'A';

INSERT INTO users (username, password_hash, role, full_name, email, department)
VALUES ('faculty1', '$2y$10$wU8zAa6VC8zx6pYQ6QfINuXKoR.Bh8JfK58HfVQx6M5vY9q5yy6tC', 'faculty', 'Faculty One', 'faculty1@college.edu', 'CSE')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO users (username, password_hash, role, full_name, email, department)
VALUES ('admin1', '$2y$10$ajRckpEifnN0tDKZnIHvhe7h8rxqCbKqRDYqJeh0LmKrD8ekA9Tpm', 'admin', 'System Admin', 'admin@college.edu', NULL)
ON DUPLICATE KEY UPDATE username = username;

-- Password for faculty1: Faculty@123
-- Password for admin1: Admin@123
