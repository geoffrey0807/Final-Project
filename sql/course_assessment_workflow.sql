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

ALTER TABLE enrollments
    ADD COLUMN IF NOT EXISTS grade_released_at DATETIME DEFAULT NULL AFTER grade_status;

ALTER TABLE enrollments
    ADD COLUMN IF NOT EXISTS final_grade DECIMAL(3,2) DEFAULT NULL AFTER grade_released_at;