ALTER TABLE assessment_questions
    ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL AFTER question_text;
