ALTER TABLE assessments
    ADD COLUMN IF NOT EXISTS is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER time_limit_minutes;
