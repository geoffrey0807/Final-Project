ALTER TABLE users
    ADD COLUMN year_level TINYINT UNSIGNED DEFAULT NULL AFTER department_id,
    ADD COLUMN cohort_section VARCHAR(20) DEFAULT NULL AFTER year_level,
    ADD INDEX idx_users_student_cohort (program_id, year_level, cohort_section, role);
