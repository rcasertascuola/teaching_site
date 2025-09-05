-- SQL Script for creating the database tables for the study platform
-- This script is designed for MySQL

-- Table: users
-- Stores user account information, including their role.
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'teacher') NOT NULL DEFAULT 'student',
    `classe` VARCHAR(50) DEFAULT NULL,
    `anno_scolastico` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: articles
-- Stores metadata for the articles (formerly texts). The content is now in the 'revisions' table.
CREATE TABLE IF NOT EXISTS `articles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `creator_id` INT NOT NULL, -- Corresponds to the original author
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: revisions
-- Stores the version history for each article.
CREATE TABLE IF NOT EXISTS `revisions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `article_id` INT NOT NULL,
    `editor_id` INT, -- Can be NULL if the user is deleted
    `content` TEXT NOT NULL,
    `edit_summary` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`editor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: user_preferences
-- Stores user-specific preferences, such as the selected theme.
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `theme` VARCHAR(50) NOT NULL DEFAULT 'light',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SECTION: Exercises --

-- Table: exercises
-- Stores the main information about an exercise.
CREATE TABLE IF NOT EXISTS `exercises` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `creator_id` INT NOT NULL,
    `content` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: exercise_articles
-- Links exercises to their prerequisite articles (many-to-many).
CREATE TABLE IF NOT EXISTS `exercise_articles` (
    `exercise_id` INT NOT NULL,
    `article_id` INT NOT NULL,
    PRIMARY KEY (`exercise_id`, `article_id`),
    FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: questions
-- Stores individual questions within an exercise.
CREATE TABLE IF NOT EXISTS `questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exercise_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('multiple_choice', 'multiple_response', 'open_ended', 'cloze_test') NOT NULL,
    `question_order` INT NOT NULL,
    `points` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `char_limit` INT DEFAULT NULL,
    `cloze_data` JSON DEFAULT NULL,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: question_options
-- Stores the options for a multiple-choice question.
CREATE TABLE IF NOT EXISTS `question_options` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT NOT NULL,
    `option_text` TEXT NOT NULL,
    `score` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: student_submissions
-- Records a student's attempt to complete an exercise.
CREATE TABLE IF NOT EXISTS `student_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exercise_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_graded` BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_submission` (`exercise_id`, `student_id`) -- Assuming a student gets one submission per exercise
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: submission_answers
-- Stores the student's specific answers for a submission.
CREATE TABLE IF NOT EXISTS `submission_answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_option_id` INT NULL,
    `open_ended_answer` TEXT NULL,
    `assigned_score` DECIMAL(5, 2) NULL, -- Final score determined by the teacher
    FOREIGN KEY (`submission_id`) REFERENCES `student_submissions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`selected_option_id`) REFERENCES `question_options`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
