-- ============================================================
-- GAME ARENA: Flash Words Module - Additional Tables
-- Add these to your story_prediction2 database
-- ============================================================

-- Table: arena_questions
-- Stores questions + options linked to each story part
-- Questions are created explicitly (not auto-generated from story text)
CREATE TABLE `arena_questions` (
  `question_id` INT(11) NOT NULL AUTO_INCREMENT,
  `story_id` INT(11) NOT NULL,
  `part_number` INT(11) NOT NULL,
  `question_text` TEXT NOT NULL,
  `correct_option` VARCHAR(300) NOT NULL,
  `misleading_option` VARCHAR(300) NOT NULL,
  `created_by` INT(11) NOT NULL COMMENT 'user_id of author who created the question',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`question_id`),
  KEY `idx_story_part` (`story_id`, `part_number`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_aq_story` FOREIGN KEY (`story_id`) REFERENCES `stories`(`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_aq_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table: arena_progress
-- Tracks each user's WPM level, streak, and best score per story part
-- WPM tiers: 60 → 100 → 140 → 180 → 220
-- Streak: 3 correct in a row = advance | wrong = drop one tier
CREATE TABLE `arena_progress` (
  `progress_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `story_id` INT(11) NOT NULL,
  `part_number` INT(11) NOT NULL,
  `current_wpm` INT(11) NOT NULL DEFAULT 60 COMMENT 'Active WPM level: 60,100,140,180,220',
  `streak` INT(11) NOT NULL DEFAULT 0 COMMENT 'Consecutive correct answers at current WPM',
  `total_correct` INT(11) NOT NULL DEFAULT 0,
  `total_attempts` INT(11) NOT NULL DEFAULT 0,
  `best_wpm` INT(11) NOT NULL DEFAULT 60 COMMENT 'Highest WPM tier ever reached',
  `last_played` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`progress_id`),
  UNIQUE KEY `unique_user_story_part` (`user_id`, `story_id`, `part_number`),
  KEY `idx_user_progress` (`user_id`),
  KEY `idx_story_progress` (`story_id`),
  CONSTRAINT `fk_ap_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ap_story` FOREIGN KEY (`story_id`) REFERENCES `stories`(`story_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
---
-- ============================================================
-- ARENA FIX: Make story_id and part_number nullable
-- arena_progress has NO FK on story_id — just modify directly
-- ============================================================

-- Drop the unique key first (required before changing column definition)
ALTER TABLE `arena_progress`
  DROP INDEX `unique_user_story_part`;

-- Make story_id and part_number nullable
ALTER TABLE `arena_progress`
  MODIFY COLUMN `story_id`    INT(11) NULL DEFAULT NULL,
  MODIFY COLUMN `part_number` INT(11) NULL DEFAULT NULL;

-- Re-add unique key (NULL values are treated as distinct in MySQL unique keys, so this is safe)
ALTER TABLE `arena_progress`
  ADD UNIQUE KEY `unique_user_story_part` (`user_id`, `story_id`, `part_number`);

-- Done. Verify with: DESCRIBE arena_progress;
-- story_id and part_number should show YES in the Null column.


-- ============================================================
-- ARENA FIX: Add loss_streak column to arena_progress
-- Tracks consecutive wrong answers for drop-level logic
-- Drop after 2 consecutive losses (not just 1)
-- ============================================================
ALTER TABLE `arena_progress`
  ADD COLUMN IF NOT EXISTS `loss_streak` INT(11) NOT NULL DEFAULT 0
    COMMENT 'Consecutive wrong answers at current WPM. Drop level after 2.'
    AFTER `streak`;
