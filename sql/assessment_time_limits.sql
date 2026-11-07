ALTER TABLE assessments
    ADD COLUMN IF NOT EXISTS time_limit_minutes SMALLINT UNSIGNED DEFAULT NULL AFTER deadline;

ALTER TABLE assessments
    MODIFY COLUMN time_limit_minutes SMALLINT UNSIGNED DEFAULT NULL;

ALTER TABLE student_submissions
    ADD COLUMN IF NOT EXISTS assessment_started_at DATETIME DEFAULT NULL AFTER reopened_until;
