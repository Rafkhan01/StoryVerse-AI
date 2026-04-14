-- ============================================================
-- ARENA ADMIN MIGRATION
-- Run this on your story_prediction2 database
-- Modifies arena_questions to support chunk tracking + external questions
-- ============================================================

-- Step 1: Add chunk_text column to arena_questions
-- Stores the actual passage chunk the question was created from
-- NULL = external question (not from story content)
ALTER TABLE `arena_questions`
  ADD COLUMN `chunk_text` TEXT DEFAULT NULL
    COMMENT 'The story passage chunk this question is based on. NULL for external questions.'
    AFTER `part_number`,

  ADD COLUMN `question_type` ENUM('story_chunk', 'external') NOT NULL DEFAULT 'story_chunk'
    COMMENT 'story_chunk = derived from story_parts content | external = admin-written custom passage'
    AFTER `chunk_text`,

  ADD COLUMN `external_passage` TEXT DEFAULT NULL
    COMMENT 'For external type: the custom knowledge paragraph written by admin (e.g. solar system facts)'
    AFTER `question_type`;

-- Step 2: Add index for faster filtering by type
ALTER TABLE `arena_questions`
  ADD KEY `idx_question_type` (`question_type`),
  ADD KEY `idx_story_part_type` (`story_id`, `part_number`, `question_type`);

-- ============================================================
-- RESULT: arena_questions now has these columns:
--   question_id       INT AUTO_INCREMENT PK
--   story_id          INT FK → stories   (NULL allowed for external? No — keep required, use 0 or a sentinel)
--   part_number       INT                (use 0 for external questions)
--   chunk_text        TEXT NULL          (the paragraph chunk used; NULL for external)
--   question_type     ENUM('story_chunk','external')
--   external_passage  TEXT NULL          (admin-written paragraph for external questions)
--   question_text     TEXT
--   correct_option    VARCHAR(300)
--   misleading_option VARCHAR(300)
--   created_by        INT FK → users
--   created_at        TIMESTAMP
--
-- For EXTERNAL questions:
--   story_id     = the story they belong to (for flash words game context)
--   part_number  = 0  (sentinel meaning "not tied to a specific part")
--   chunk_text   = NULL
--   external_passage = the custom paragraph
--
-- For STORY CHUNK questions:
--   story_id     = actual story
--   part_number  = actual part number
--   chunk_text   = the paragraph chunk text
--   external_passage = NULL
-- ============================================================

-- Step 3: Update the unique key — original was (story_id, part_number, question_id is PK so no unique needed)
-- But we should allow multiple questions per story+part, which is already the case. No change needed there.

-- Step 4: Allow part_number = 0 for external questions
-- The FK on story_parts won't allow part_number=0, but there's no FK on part_number column
-- (only story_id has a FK to stories). So part_number=0 is safe for external questions.

-- Verify: show updated structure
-- DESCRIBE arena_questions;
