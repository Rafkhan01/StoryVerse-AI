-- ═══════════════════════════════════════════════════
-- StoryVerse Chat Feature — New Tables
-- Run this in phpMyAdmin on story_prediction2 database
-- ═══════════════════════════════════════════════════

-- 1. Private messages (author ↔ admin only)
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `message_id`  INT(11)       NOT NULL AUTO_INCREMENT,
    `sender_id`   INT(11)       NOT NULL,
    `receiver_id` INT(11)       NOT NULL,
    `message_text` TEXT         NOT NULL,
    `is_read`     TINYINT(1)    NOT NULL DEFAULT 0,
    `is_edited`   TINYINT(1)    NOT NULL DEFAULT 0,
    `is_deleted`  TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT current_timestamp(),
    `edited_at`   TIMESTAMP     NULL     DEFAULT NULL,
    PRIMARY KEY (`message_id`),
    KEY `idx_sender`   (`sender_id`),
    KEY `idx_receiver` (`receiver_id`),
    KEY `idx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Group chat messages (all authors + admin)
CREATE TABLE IF NOT EXISTS `group_chat_messages` (
    `message_id`  INT(11)       NOT NULL AUTO_INCREMENT,
    `sender_id`   INT(11)       NOT NULL,
    `message_text` TEXT         NOT NULL,
    `is_edited`   TINYINT(1)    NOT NULL DEFAULT 0,
    `is_deleted`  TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT current_timestamp(),
    `edited_at`   TIMESTAMP     NULL     DEFAULT NULL,
    PRIMARY KEY (`message_id`),
    KEY `idx_sender`  (`sender_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Unread tracking (for admin sidebar badges)
-- other_user_id = NULL means group chat
CREATE TABLE IF NOT EXISTS `chat_read_status` (
    `id`            INT(11)   NOT NULL AUTO_INCREMENT,
    `user_id`       INT(11)   NOT NULL,
    `other_user_id` INT(11)   NULL DEFAULT NULL,
    `last_read_at`  TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_conversation` (`user_id`, `other_user_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
