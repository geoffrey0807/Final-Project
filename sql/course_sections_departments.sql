ALTER TABLE courses
    DROP INDEX course_code,
    ADD UNIQUE KEY uq_courses_code_section (course_code, section_name);
