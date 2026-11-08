CREATE DATABASE IF NOT EXISTS apex_learning_hub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE apex_learning_hub;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO departments (code, name)
VALUES
    ('DSEAD', 'Department of Software Engineering & Application Development'),
    ('DSCS', 'Department of Computer Science'),
    ('DISWT', 'Department of Information Systems & Web Technologies')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    department_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_programs_name (name),
    INDEX idx_programs_department (department_id),
    CONSTRAINT fk_programs_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO programs (code, name, department_id)
SELECT 'BSSE', 'BS in Software Engineering', id FROM departments WHERE code = 'DSEAD'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id)
SELECT 'BSMAD', 'BS in Mobile Application Development', id FROM departments WHERE code = 'DSEAD'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id)
SELECT 'BSCS', 'BS in Computer Science', id FROM departments WHERE code = 'DSCS'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id)
SELECT 'GPMC', 'BS in Game Programming / Multimedia Computing', id FROM departments WHERE code = 'DSCS'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id)
SELECT 'BSIT', 'BS in Information Systems', id FROM departments WHERE code = 'DISWT'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id)
SELECT 'ADFSWD', 'Associate Degree in Full-Stack Web Development', id FROM departments WHERE code = 'DISWT'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL,
    suffix VARCHAR(20) DEFAULT NULL,
    id_number VARCHAR(30) DEFAULT NULL,
    program VARCHAR(150) DEFAULT NULL,
    program_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    year_level TINYINT UNSIGNED DEFAULT NULL,
    cohort_section VARCHAR(20) DEFAULT NULL,
    role ENUM('admin', 'registrar', 'dean', 'professor', 'student') NOT NULL DEFAULT 'student',
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT DEFAULT NULL,
    INDEX idx_users_account_state (is_active, archived_at, role),
    INDEX idx_users_created_by (created_by),
    INDEX idx_users_program_id (program_id),
    INDEX idx_users_department_role (department_id, role),
    INDEX idx_users_student_cohort (program_id, year_level, cohort_section, role),
    UNIQUE KEY uq_users_id_number (id_number),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS academic_periods (
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    status ENUM('planned', 'open', 'closed') NOT NULL DEFAULT 'planned',
    opened_at DATETIME DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (academic_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO academic_periods (academic_year, semester, status, opened_at)
VALUES (
    CONCAT(
        IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE), YEAR(CURRENT_DATE) - 1),
        '-',
        IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE) + 1, YEAR(CURRENT_DATE))
    ),
    IF(MONTH(CURRENT_DATE) BETWEEN 7 AND 11, 'first', 'second'),
    'open',
    NOW()
)
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

CREATE TABLE IF NOT EXISTS student_academic_placements (
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

CREATE TABLE IF NOT EXISTS admin_password_changes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    request_channel ENUM('contact', 'ticket') NOT NULL,
    request_reference VARCHAR(100) DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_password_changes_user (user_id, changed_at),
    INDEX idx_admin_password_changes_admin (admin_id, changed_at),
    CONSTRAINT fk_admin_password_changes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_admin_password_changes_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(32) DEFAULT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(100) NOT NULL,
    requester_role VARCHAR(20) DEFAULT NULL,
    subject VARCHAR(40) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'in_progress', 'resolved') NOT NULL DEFAULT 'new',
    admin_response TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    INDEX idx_support_requests_status_created (status, created_at),
    INDEX idx_support_requests_user_created (user_id, created_at),
    CONSTRAINT fk_support_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_support_requests_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_request_reads (
    request_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    message_hash CHAR(64) NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, user_id),
    INDEX idx_support_request_reads_user (user_id),
    CONSTRAINT fk_support_request_reads_request
        FOREIGN KEY (request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_request_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    section_name VARCHAR(50) NOT NULL DEFAULT 'General',
    professor_id INT DEFAULT NULL,
    max_capacity INT DEFAULT 10,
    meeting_days VARCHAR(80) DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    room VARCHAR(100) DEFAULT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    active_course_code VARCHAR(20) GENERATED ALWAYS AS (
        CASE WHEN archived_at IS NULL THEN course_code ELSE NULL END
    ) STORED,
    UNIQUE KEY uq_courses_period_code_section (academic_year, semester, active_course_code, section_name),
    INDEX idx_courses_period_professor (academic_year, semester, professor_id),
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course_programs (
    course_id INT NOT NULL,
    program_id INT NOT NULL,
    PRIMARY KEY (course_id, program_id),
    INDEX idx_course_programs_program (program_id),
    CONSTRAINT fk_course_programs_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_course_programs_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    grade_status ENUM('Pass', 'Fail', 'Drop') DEFAULT NULL,
    grade_released_at DATETIME DEFAULT NULL,
    final_grade DECIMAL(3,2) DEFAULT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id),
    UNIQUE KEY uq_enrollments_student_course_period (student_id, course_code, academic_year, semester),
    INDEX idx_enrollments_period_student (academic_year, semester, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT DEFAULT NULL,
    author_id INT NOT NULL,
    headline VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    is_homepage_notice TINYINT(1) NOT NULL DEFAULT 0,
    homepage_category ENUM('academic', 'system', 'event', 'policy') DEFAULT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_homepage_latest (is_homepage_notice, created_at, id),
    INDEX idx_announcements_period (academic_year, semester, created_at),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcement_audience_roles (
    announcement_id INT NOT NULL,
    role ENUM('admin', 'registrar', 'dean', 'professor', 'student') NOT NULL,
    PRIMARY KEY (announcement_id, role),
    INDEX idx_announcement_audience_role (role),
    CONSTRAINT fk_announcement_audience_announcement
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registrar_announcement_acknowledgements (
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    acknowledged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, user_id),
    INDEX idx_registrar_announcement_ack_user (user_id),
    CONSTRAINT fk_registrar_announcement_ack_announcement
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_registrar_announcement_ack_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_announcement_reads (
    announcement_id INT NOT NULL,
    student_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, student_id),
    INDEX idx_student_announcement_reads_student (student_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_announcement_reads (
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, user_id),
    INDEX idx_user_announcement_reads_user (user_id),
    CONSTRAINT fk_user_announcement_reads_announcement
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_announcement_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_todo_views (
    student_id INT NOT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    last_viewed_at DATETIME NOT NULL,
    PRIMARY KEY (student_id, academic_year, semester),
    INDEX idx_student_todo_views_period (academic_year, semester),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    professor_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    term ENUM('Prelim', 'Midterm', 'Final') NOT NULL DEFAULT 'Prelim',
    assessment_type ENUM('Quiz', 'Assessment', 'Exam') NOT NULL DEFAULT 'Assessment',
    due_at DATETIME NOT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assignments_period (academic_year, semester, course_id),
    INDEX idx_assignments_course_term (course_id, term),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assignment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'identification') NOT NULL,
    choices JSON DEFAULT NULL,
    correct_answer VARCHAR(255) NOT NULL,
    question_order INT NOT NULL DEFAULT 1,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    professor_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    term ENUM('Prelim', 'Midterm', 'Final') NOT NULL DEFAULT 'Prelim',
    file_name VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    file_mime VARCHAR(100) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lessons_period (academic_year, semester, course_id),
    INDEX idx_lessons_course_term (course_id, term),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assignment_completions (
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    professor_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    term ENUM('Prelim', 'Midterm', 'Final') NOT NULL,
    assessment_type ENUM('Quiz', 'Exam') NOT NULL,
    question_format ENUM('Multiple Choice', 'True or False', 'Mixed') NOT NULL,
    total_points DECIMAL(8,2) NOT NULL,
    deadline DATETIME NOT NULL,
    time_limit_minutes SMALLINT UNSIGNED DEFAULT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assessments_course_term (course_id, term, deadline),
    INDEX idx_assessments_period (academic_year, semester, course_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assessment_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false') NOT NULL,
    choices_json JSON DEFAULT NULL,
    correct_answer VARCHAR(1000) DEFAULT NULL,
    points DECIMAL(8,2) NOT NULL DEFAULT 1,
    question_order INT NOT NULL DEFAULT 1,
    INDEX idx_assessment_questions_order (assessment_id, question_order),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    score DECIMAL(8,2) DEFAULT NULL,
    status ENUM('Pending', 'Submitted', 'Missed') NOT NULL DEFAULT 'Pending',
    answers_json JSON DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    reopened_until DATETIME DEFAULT NULL,
    assessment_started_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_submission (assessment_id, student_id),
    INDEX idx_student_submissions_student_status (student_id, status),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (id, email, password, first_name, last_name, role, id_number)
VALUES (1, 'admin@apex.edu.ph', '$2y$10$7lxX8wPp2FO.hI23tOGz9ODDMTwe9Anjx4Wu36p5GrfJ2BmuxZB0S', 'System', 'Administrator', 'admin', CONCAT('ADM-', YEAR(CURRENT_DATE), '-0001'))
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password = VALUES(password),
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    role = VALUES(role),
    id_number = COALESCE(id_number, VALUES(id_number));
