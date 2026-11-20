-- ============================================================================
-- HudumaCampus - Campus Student Helpdesk Portal
-- Database Export: Schema + Sample Data
-- ============================================================================
-- Note: For live hosting (e.g., InfinityFree), select your assigned database
-- in phpMyAdmin first and comment out the CREATE DATABASE / USE lines below.

CREATE DATABASE IF NOT EXISTS `hudumacampus_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `hudumacampus_db`;

-- Drop tables in reverse dependency order for clean re-imports
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `submissions`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ----------------------------------------------------------------------------
-- 1. Users Table
-- Stores unique usernames, unique emails, bcrypt password hashes, and metadata
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL DEFAULT '',
    `reg_number` VARCHAR(40) DEFAULT NULL,
    `department` VARCHAR(80) NOT NULL DEFAULT 'Computer Science',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Categories Table (Additional Relational Table)
-- Normalizes campus helpdesk departments / inquiry topics
-- ----------------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `desk_email` VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Submissions Table (Contact Us / Helpdesk Tickets)
-- Linked to users and categories via Foreign Keys; indexed for search & sort
-- ----------------------------------------------------------------------------
CREATE TABLE `submissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `sender_name` VARCHAR(100) NOT NULL,
    `sender_email` VARCHAR(120) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('Open', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Open',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_submissions_user_id` (`user_id`),
    KEY `idx_submissions_category_id` (`category_id`),
    KEY `idx_submissions_email` (`sender_email`),
    KEY `idx_submissions_subject` (`subject`),
    KEY `idx_submissions_status` (`status`),
    KEY `idx_submissions_created_at` (`created_at`),
    CONSTRAINT `fk_submissions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_submissions_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Activity Logs Table (Relational Audit Trail)
-- Tracks key user actions inside MySQL alongside logs/activity.log
-- ----------------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(80) NOT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user_id` (`user_id`),
    KEY `idx_logs_created_at` (`created_at`),
    CONSTRAINT `fk_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA
-- Demo password for all seeded users below is: password
-- (Hashed with PHP bcrypt password_hash)
-- ============================================================================

INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `reg_number`, `department`, `created_at`) VALUES
('amina_k',   'amina.kamau@hudumacampus.ac.ke',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Amina Kamau',   'BCS-01-0101/2024', 'Computer Science',    '2026-10-05 09:15:00'),
('brian_o',   'brian.otieno@hudumacampus.ac.ke',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Brian Otieno',  'BIT-02-0214/2024', 'Information Tech',    '2026-10-08 11:30:00'),
('fatma_s',   'fatma.salim@hudumacampus.ac.ke',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatma Salim',   'BSE-01-0088/2025', 'Software Engineering','2026-10-12 14:45:00'),
('kevin_m',   'kevin.mwangi@hudumacampus.ac.ke',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kevin Mwangi',  'BCS-03-0342/2023', 'Computer Science',    '2026-10-18 08:20:00');

INSERT INTO `categories` (`name`, `desk_email`, `description`) VALUES
('IT & Portal Support',    'itdesk@hudumacampus.ac.ke',       'Student portal logins, campus Wi-Fi, email resets, and Moodle access'),
('Finance & Fee Queries',  'finance@hudumacampus.ac.ke',      'Fee statements, M-Pesa receipt reconciliation, HELB updates, and clearance'),
('Academics & Exams',      'academics@hudumacampus.ac.ke',    'Unit registration, exam cards, missing marks, and timetables'),
('Library Services',       'library@hudumacampus.ac.ke',      'Book borrowing, e-resource access, and overdue fines'),
('Hostels & Facilities',   'facilities@hudumacampus.ac.ke',   'Lab equipment, lecture hall bookings, and hostel maintenance');

INSERT INTO `submissions` (`user_id`, `category_id`, `sender_name`, `sender_email`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 1, 'Amina Kamau',  'amina.kamau@hudumacampus.ac.ke',  'IT & Portal Support',   'Unable to access Moodle course page for BSD 322 after resetting my student email password.', 'Resolved',    '2026-11-01 10:15:00'),
(2, 2, 'Brian Otieno', 'brian.otieno@hudumacampus.ac.ke', 'Finance & Fee Queries', 'Paid tuition via M-Pesa Paybill three days ago but my student fee statement has not updated yet.', 'In Progress', '2026-11-03 14:22:00'),
(3, 3, 'Fatma Salim',  'fatma.salim@hudumacampus.ac.ke',  'Academics & Exams',     'My CAT 1 score for Database Systems is missing on the portal despite submitting on time.', 'Open',        '2026-11-05 09:05:00'),
(4, 1, 'Kevin Mwangi', 'kevin.mwangi@hudumacampus.ac.ke', 'IT & Portal Support',   'Campus Wi-Fi in Computer Lab 3 drops every 15 minutes during practical sessions.', 'In Progress', '2026-11-07 16:40:00'),
(1, 4, 'Amina Kamau',  'amina.kamau@hudumacampus.ac.ke',  'Library Services',      'Requesting remote off-campus login credentials for IEEE Xplore and ACM Digital Library.', 'Resolved',    '2026-11-09 11:12:00'),
(2, 5, 'Brian Otieno', 'brian.otieno@hudumacampus.ac.ke', 'Hostels & Facilities',  'Projector HDMI port in Lecture Room B4 is faulty and needs replacement before Friday presentations.', 'Open',        '2026-11-11 08:50:00'),
(3, 2, 'Fatma Salim',  'fatma.salim@hudumacampus.ac.ke',  'Finance & Fee Queries', 'Requesting confirmation of HELB upkeep disbursement status for the current semester.', 'Open',        '2026-11-13 13:30:00'),
(4, 3, 'Kevin Mwangi', 'kevin.mwangi@hudumacampus.ac.ke', 'Academics & Exams',     'Need assistance printing my exam card; the portal shows a unit registration clash.', 'In Progress', '2026-11-15 15:18:00'),
(1, 3, 'Amina Kamau',  'amina.kamau@hudumacampus.ac.ke',  'Academics & Exams',     'Clarification needed on the final project submission deadline for Internet Programming II.', 'Resolved',    '2026-11-17 10:02:00'),
(2, 1, 'Brian Otieno', 'brian.otieno@hudumacampus.ac.ke', 'IT & Portal Support',   'Requesting a temporary student lab account reset for XAMPP and phpMyAdmin testing.', 'Open',        '2026-11-19 12:45:00');

INSERT INTO `activity_logs` (`user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 'REGISTER',          'Created new student account (amina_k)',       '127.0.0.1', '2026-10-05 09:15:00'),
(1, 'SUBMIT_INQUIRY',    'Submitted inquiry under IT & Portal Support', '127.0.0.1', '2026-11-01 10:15:00'),
(2, 'LOGIN',             'User logged in successfully',                 '127.0.0.1', '2026-11-03 14:20:00'),
(3, 'SUBMIT_INQUIRY',    'Submitted inquiry under Academics & Exams',   '127.0.0.1', '2026-11-05 09:05:00');