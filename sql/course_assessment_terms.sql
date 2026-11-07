ALTER TABLE assignments
    ADD COLUMN IF NOT EXISTS term ENUM('Prelim', 'Midterm', 'Final') NOT NULL DEFAULT 'Prelim' AFTER description,
    ADD INDEX IF NOT EXISTS idx_assignments_course_term (course_id, term);