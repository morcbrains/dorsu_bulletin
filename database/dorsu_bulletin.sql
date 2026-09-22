-- DOrSU Bulletin one-paste database installer
-- Usage: open phpMyAdmin -> SQL tab -> paste this entire file -> Go
-- No manual schema creation needed.

DROP DATABASE IF EXISTS dorsu_bulletin_db;
CREATE DATABASE dorsu_bulletin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dorsu_bulletin_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'informant', 'faculty', 'student') NOT NULL,
    student_id VARCHAR(50) NULL,
    faculty_id VARCHAR(50) NULL,
    office_name VARCHAR(150) NULL,
    program VARCHAR(150) NULL,
    year_level VARCHAR(50) NULL,
    section VARCHAR(50) NULL,
    department VARCHAR(150) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reset_code VARCHAR(10) NULL,
    reset_code_expires DATETIME NULL
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_type ENUM('faculty', 'program') DEFAULT 'faculty',
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    announcement_date DATE NULL,
    announcement_time TIME NULL,
    faculty_name VARCHAR(150) NULL,
    program_name VARCHAR(150) NULL,
    category VARCHAR(100) DEFAULT 'General Announcement',
    target_audience VARCHAR(100) DEFAULT 'All Users',
    urgency ENUM('normal', 'urgent') DEFAULT 'normal',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    posted_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcements_posted_by
        FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE announcement_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (announcement_id, user_id),
    CONSTRAINT fk_announcement_reads_announcement
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE saved_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_saved (announcement_id, user_id),
    CONSTRAINT fk_saved_announcements_announcement
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_announcements_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE university_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_title VARCHAR(200) NOT NULL,
    event_description TEXT NOT NULL,
    event_category VARCHAR(100) DEFAULT 'University Event',
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    venue VARCHAR(150) NOT NULL,
    organizer VARCHAR(150) NOT NULL,
    target_audience VARCHAR(100) DEFAULT 'All Users',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_university_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE private_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_private_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_private_messages_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (
    id, full_name, email, password, role, student_id, faculty_id, office_name,
    program, year_level, section, department, status
) VALUES
(
    1,
    'System Administrator',
    'admin@dorsu.edu.ph',
    '$2y$12$WH/LR0patjtTWLUlbrD0w.y5T.jrmSeFKn1/gFp25Y0dQeRwyFvXy',
    'admin',
    NULL, NULL, NULL, NULL, NULL, NULL, NULL,
    'approved'
),
(
    14,
    'Chan Romano',
    'chanromano1@email.com',
    '$2y$10$gttRhMqX0ST9OVCp1l4hm.0ihr3xXUdTsjYUcVX.sOqUOKVd2baym',
    'informant',
    NULL, NULL, 'University Student Council (USC)', NULL, NULL, NULL, NULL,
    'approved'
),
(
    15,
    'Chan Romano',
    'chanromano2@email.com',
    '$2y$10$qrSEa67hySZ0UfjGN10emeiZ6./nSFGdZBjaGwjmUrkus4CyZTF.q',
    'faculty',
    NULL, 'FCT-2000', NULL, NULL, NULL, NULL,
    'Faculty of Computing, Engineering and Technology (FaCET)',
    'approved'
),
(
    16,
    'Caleb Bangon',
    'calebbangon@dorsu.edu.ph',
    '$2y$10$0M8ZMxvjA8O5UGqHrO8kLOfvTM3QWLK8uFFYe/FNZA1IxuzIXh7iK',
    'student',
    '2024-0455', NULL, NULL,
    'BS Information Technology', '2nd Year', NULL,
    'Faculty of Computing, Engineering and Technology (FaCET)',
    'approved'
);

INSERT INTO categories (category_name, description, status) VALUES
('General Announcement', 'General university announcements and updates.', 'active'),
('University Event', 'Official university events and activities.', 'active'),
('Academic Announcement', 'Class, program, department, and academic updates.', 'active'),
('Enrollment', 'Enrollment schedules, requirements, and reminders.', 'active'),
('Examination', 'Exam schedules, reminders, and guidelines.', 'active'),
('Scholarship', 'Scholarship announcements and opportunities.', 'active'),
('Emergency Notice', 'Urgent and emergency university notices.', 'active');

INSERT INTO system_settings (setting_key, setting_value) VALUES
('system_name', 'DOrSU Bulletin'),
('university_name', 'Davao Oriental State University'),
('contact_email', 'dorsu.bulletin@dorsu.edu.ph'),
('maintenance_mode', 'off'),
('default_account_status', 'pending');

INSERT INTO announcements (
    id, announcement_type, title, description, announcement_date, announcement_time,
    faculty_name, program_name, category, target_audience, urgency, status, posted_by
) VALUES
(
    39, 'faculty', 'Test', 'test', NULL, NULL, NULL, NULL,
    'Academic Announcement', 'All Users', 'urgent', 'published', 14
),
(
    40, 'faculty', 'Test', 'Test', '2026-07-22', '08:30:00',
    'Faculty of Computing, Engineering and Technology (FaCET)', 'All Programs',
    'Faculty Announcement', 'Students and Faculty', 'normal', 'published', 15
);

INSERT INTO university_events (
    id, event_title, event_description, event_category, event_date, start_time, end_time,
    venue, organizer, target_audience, status, created_by
) VALUES
(
    9, 'Test', 'Test', 'Sports Event', '2026-07-24', '20:35:00', '08:33:00',
    'Gymnasium', 'University Student Council (USC)', 'All Users', 'published', 14
);

-- Default accounts use bcrypt hashes from the project export.
-- If login fails, use Forgot Password on localhost or reset the password in phpMyAdmin.
