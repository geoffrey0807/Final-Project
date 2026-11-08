ALTER TABLE enrollments
    ADD COLUMN IF NOT EXISTS grade_status ENUM('Pass', 'Fail', 'Drop') DEFAULT NULL AFTER status;
