ALTER TABLE enrollments
    ADD COLUMN course_code VARCHAR(20) NULL AFTER course_id;

UPDATE enrollments e
JOIN courses c ON c.id = e.course_id
SET e.course_code = c.course_code;

ALTER TABLE enrollments
    MODIFY course_code VARCHAR(20) NOT NULL,
    ADD UNIQUE KEY uq_enrollments_student_course_code (student_id, course_code);
