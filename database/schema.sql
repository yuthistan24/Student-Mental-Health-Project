CREATE DATABASE IF NOT EXISTS learning_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE learning_portal;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone_number VARCHAR(25) NULL,
    address_line VARCHAR(255) NULL,
    role_name ENUM('admin', 'educator', 'counselor') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_code VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    grade_level VARCHAR(30) NOT NULL,
    phone_number VARCHAR(25) NULL,
    address_line VARCHAR(255) NULL,
    community_zone VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_background_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    attempted_exam ENUM('none', 'neet', 'jee', 'both') NOT NULL DEFAULT 'none',
    target_stream VARCHAR(120) NULL,
    current_stream VARCHAR(120) NULL,
    stream_mismatch TINYINT(1) NOT NULL DEFAULT 0,
    financial_issues TINYINT(1) NOT NULL DEFAULT 0,
    worked_after_school TINYINT(1) NOT NULL DEFAULT 0,
    work_history_note VARCHAR(255) NULL,
    study_gap_months INT NOT NULL DEFAULT 0,
    gap_years INT NOT NULL DEFAULT 0,
    gap_year_reason VARCHAR(255) NULL,
    feeling_about_studies ENUM('motivated', 'neutral', 'stressed', 'burned_out') NOT NULL DEFAULT 'neutral',
    discomfort_due_to_issues TINYINT(1) NOT NULL DEFAULT 0,
    discomfort_reason VARCHAR(255) NULL,
    confidence_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    primary_challenge VARCHAR(255) NULL,
    goals VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_attendance_student_date (student_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS assessment_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_name VARCHAR(60) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    assessment_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_assessment_student_date (student_id, assessment_date)
);

CREATE TABLE IF NOT EXISTS behavior_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_date DATE NOT NULL,
    severity ENUM('low', 'moderate', 'high') NOT NULL,
    note VARCHAR(255) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_behavior_student_date (student_id, event_date)
);

CREATE TABLE IF NOT EXISTS learning_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(140) NOT NULL,
    subject_name VARCHAR(60) NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
    offline_enabled TINYINT(1) NOT NULL DEFAULT 1,
    estimated_minutes INT NOT NULL DEFAULT 20
);

CREATE TABLE IF NOT EXISTS module_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    module_id INT NOT NULL,
    completion_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    mastery_score DECIMAL(5,2) NULL,
    last_synced_at DATETIME NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES learning_modules(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_progress_student_module (student_id, module_id)
);

CREATE TABLE IF NOT EXISTS community_hubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hub_name VARCHAR(120) NOT NULL,
    location_name VARCHAR(120) NOT NULL,
    internet_reliability ENUM('low', 'medium', 'high') NOT NULL,
    active_tutors INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tutoring_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    hub_id INT NOT NULL,
    tutor_name VARCHAR(120) NOT NULL,
    subject_name VARCHAR(60) NOT NULL,
    session_date DATETIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (hub_id) REFERENCES community_hubs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    alert_level ENUM('low', 'medium', 'high') NOT NULL,
    alert_message VARCHAR(255) NOT NULL,
    recommended_action VARCHAR(255) NOT NULL,
    status ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_alert_status_level (status, alert_level)
);

CREATE TABLE IF NOT EXISTS chatbot_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('admin', 'educator', 'counselor') NOT NULL,
    user_message TEXT NOT NULL,
    bot_reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_chatbot_created (created_at)
);

