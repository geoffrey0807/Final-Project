ALTER TABLE lessons
    ADD COLUMN IF NOT EXISTS term ENUM('Prelim', 'Midterm', 'Final') NOT NULL DEFAULT 'Prelim' AFTER content,
    ADD INDEX IF NOT EXISTS idx_lessons_course_term (course_id, term);