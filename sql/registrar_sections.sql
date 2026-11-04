ALTER TABLE courses
    ADD COLUMN section_name VARCHAR(50) NOT NULL DEFAULT 'General' AFTER course_name;
