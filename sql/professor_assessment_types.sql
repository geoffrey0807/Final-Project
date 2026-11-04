ALTER TABLE assignments
    ADD COLUMN assessment_type ENUM('Quiz', 'Assessment', 'Exam') NOT NULL DEFAULT 'Assessment';
