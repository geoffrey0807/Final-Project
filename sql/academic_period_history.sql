CREATE TABLE academic_periods (
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    status ENUM('planned', 'open', 'closed') NOT NULL DEFAULT 'planned',
    opened_at DATETIME DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (academic_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE courses
    ADD COLUMN academic_year CHAR(9) DEFAULT NULL AFTER room,
    ADD COLUMN semester ENUM('first', 'second') DEFAULT NULL AFTER academic_year;

ALTER TABLE enrollments
    ADD COLUMN academic_year CHAR(9) DEFAULT NULL AFTER course_code,
    ADD COLUMN semester ENUM('first', 'second') DEFAULT NULL AFTER academic_year;

ALTER TABLE announcements
    ADD COLUMN academic_year CHAR(9) DEFAULT NULL AFTER homepage_category,
    ADD COLUMN semester ENUM('first', 'second') DEFAULT NULL AFTER academic_year;

ALTER TABLE assignments
    ADD COLUMN academic_year CHAR(9) DEFAULT NULL AFTER due_at,
    ADD COLUMN semester ENUM('first', 'second') DEFAULT NULL AFTER academic_year;

ALTER TABLE lessons
    ADD COLUMN academic_year CHAR(9) DEFAULT NULL AFTER file_size,
    ADD COLUMN semester ENUM('first', 'second') DEFAULT NULL AFTER academic_year;

UPDATE courses
SET academic_year = CONCAT(
        IF(MONTH(created_at) >= 7, YEAR(created_at), YEAR(created_at) - 1),
        '-',
        IF(MONTH(created_at) >= 7, YEAR(created_at) + 1, YEAR(created_at))
    ),
    semester = IF(MONTH(created_at) BETWEEN 7 AND 11, 'first', 'second');

UPDATE enrollments
SET academic_year = CONCAT(
        IF(MONTH(applied_at) >= 7, YEAR(applied_at), YEAR(applied_at) - 1),
        '-',
        IF(MONTH(applied_at) >= 7, YEAR(applied_at) + 1, YEAR(applied_at))
    ),
    semester = IF(MONTH(applied_at) BETWEEN 7 AND 11, 'first', 'second');

UPDATE announcements
SET academic_year = CONCAT(
        IF(MONTH(created_at) >= 7, YEAR(created_at), YEAR(created_at) - 1),
        '-',
        IF(MONTH(created_at) >= 7, YEAR(created_at) + 1, YEAR(created_at))
    ),
    semester = IF(MONTH(created_at) BETWEEN 7 AND 11, 'first', 'second');

UPDATE assignments
SET academic_year = CONCAT(
        IF(MONTH(created_at) >= 7, YEAR(created_at), YEAR(created_at) - 1),
        '-',
        IF(MONTH(created_at) >= 7, YEAR(created_at) + 1, YEAR(created_at))
    ),
    semester = IF(MONTH(created_at) BETWEEN 7 AND 11, 'first', 'second');

UPDATE lessons
SET academic_year = CONCAT(
        IF(MONTH(created_at) >= 7, YEAR(created_at), YEAR(created_at) - 1),
        '-',
        IF(MONTH(created_at) >= 7, YEAR(created_at) + 1, YEAR(created_at))
    ),
    semester = IF(MONTH(created_at) BETWEEN 7 AND 11, 'first', 'second');

ALTER TABLE courses
    MODIFY academic_year CHAR(9) NOT NULL,
    MODIFY semester ENUM('first', 'second') NOT NULL,
    DROP INDEX uq_courses_code_section,
    ADD UNIQUE KEY uq_courses_period_code_section (academic_year, semester, course_code, section_name),
    ADD INDEX idx_courses_period_professor (academic_year, semester, professor_id);

ALTER TABLE enrollments
    MODIFY academic_year CHAR(9) NOT NULL,
    MODIFY semester ENUM('first', 'second') NOT NULL,
    DROP INDEX uq_enrollments_student_course_code,
    ADD UNIQUE KEY uq_enrollments_student_course_period (student_id, course_code, academic_year, semester),
    ADD INDEX idx_enrollments_period_student (academic_year, semester, student_id);

ALTER TABLE announcements
    MODIFY academic_year CHAR(9) NOT NULL,
    MODIFY semester ENUM('first', 'second') NOT NULL,
    ADD INDEX idx_announcements_period (academic_year, semester, created_at);

ALTER TABLE assignments
    MODIFY academic_year CHAR(9) NOT NULL,
    MODIFY semester ENUM('first', 'second') NOT NULL,
    ADD INDEX idx_assignments_period (academic_year, semester, course_id);

ALTER TABLE lessons
    MODIFY academic_year CHAR(9) NOT NULL,
    MODIFY semester ENUM('first', 'second') NOT NULL,
    ADD INDEX idx_lessons_period (academic_year, semester, course_id);

INSERT IGNORE INTO academic_periods (academic_year, semester, status)
SELECT DISTINCT
    CONCAT(start_year, '-', start_year + 1),
    semester,
    CASE
        WHEN CONCAT(start_year, '-', start_year + 1) = current_year
             AND semester = current_semester THEN 'open'
        WHEN period_end <= CURRENT_DATE THEN 'closed'
        ELSE 'planned'
    END
FROM (
    SELECT
        IF(MONTH(period_date) >= 7, YEAR(period_date), YEAR(period_date) - 1) AS start_year,
        IF(MONTH(period_date) BETWEEN 7 AND 11, 'first', 'second') AS semester,
        CASE
            WHEN MONTH(period_date) BETWEEN 7 AND 11 THEN STR_TO_DATE(CONCAT(YEAR(period_date), '-12-01'), '%Y-%m-%d')
            WHEN MONTH(period_date) = 12 THEN STR_TO_DATE(CONCAT(YEAR(period_date) + 1, '-07-01'), '%Y-%m-%d')
            ELSE STR_TO_DATE(CONCAT(YEAR(period_date), '-07-01'), '%Y-%m-%d')
        END AS period_end,
        CONCAT(
            IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE), YEAR(CURRENT_DATE) - 1),
            '-',
            IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE) + 1, YEAR(CURRENT_DATE))
        ) AS current_year,
        IF(MONTH(CURRENT_DATE) BETWEEN 7 AND 11, 'first', 'second') AS current_semester
    FROM (
        SELECT created_at AS period_date FROM courses
        UNION ALL SELECT applied_at FROM enrollments
        UNION ALL SELECT created_at FROM announcements
        UNION ALL SELECT created_at FROM assignments
        UNION ALL SELECT created_at FROM lessons
    ) period_dates
) period_rows;

INSERT INTO academic_periods (academic_year, semester, status, opened_at)
SELECT
    CONCAT(
        IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE), YEAR(CURRENT_DATE) - 1),
        '-',
        IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE) + 1, YEAR(CURRENT_DATE))
    ),
    IF(MONTH(CURRENT_DATE) BETWEEN 7 AND 11, 'first', 'second'),
    'open',
    NOW()
ON DUPLICATE KEY UPDATE status = 'open', opened_at = COALESCE(opened_at, NOW()), closed_at = NULL;

INSERT IGNORE INTO academic_periods (academic_year, semester, status, opened_at, closed_at)
SELECT academic_year, semester,
       CASE
           WHEN academic_year = current_year AND semester = current_semester THEN 'open'
           WHEN period_end <= CURRENT_DATE THEN 'closed'
           ELSE 'planned'
       END,
       CASE WHEN academic_year = current_year AND semester = current_semester THEN NOW() ELSE NULL END,
       CASE
           WHEN period_end <= CURRENT_DATE
                AND NOT (academic_year = current_year AND semester = current_semester)
           THEN period_end
           ELSE NULL
       END
FROM (
    SELECT CONCAT(years.start_year, '-', years.start_year + 1) AS academic_year,
           semesters.semester,
           IF(semesters.semester = 'first',
              STR_TO_DATE(CONCAT(years.start_year, '-12-01'), '%Y-%m-%d'),
              STR_TO_DATE(CONCAT(years.start_year + 1, '-07-01'), '%Y-%m-%d')) AS period_end,
           CONCAT(
               IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE), YEAR(CURRENT_DATE) - 1),
               '-',
               IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE) + 1, YEAR(CURRENT_DATE))
           ) AS current_year,
           IF(MONTH(CURRENT_DATE) BETWEEN 7 AND 11, 'first', 'second') AS current_semester
    FROM (
        SELECT 2022 AS start_year UNION ALL SELECT 2023 UNION ALL SELECT 2024
        UNION ALL SELECT 2025 UNION ALL SELECT 2026 UNION ALL SELECT 2027
    ) years
    CROSS JOIN (
        SELECT 'first' AS semester UNION ALL SELECT 'second'
    ) semesters
) periods;

CREATE TABLE student_academic_placements (
    student_id INT NOT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    program_id INT NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    cohort_section VARCHAR(20) DEFAULT NULL,
    progression_status ENUM('initial', 'new', 'continued', 'promoted', 'retake') NOT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, academic_year, semester),
    INDEX idx_student_placements_period (academic_year, semester, program_id, year_level, cohort_section),
    CONSTRAINT fk_student_placements_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_placements_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT,
    CONSTRAINT fk_student_placements_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_student_placements_period FOREIGN KEY (academic_year, semester)
        REFERENCES academic_periods(academic_year, semester) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
