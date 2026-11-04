ALTER TABLE courses
    ADD COLUMN IF NOT EXISTS meeting_days VARCHAR(40) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS start_time TIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS end_time TIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS room VARCHAR(100) DEFAULT NULL;

ALTER TABLE courses
    MODIFY COLUMN meeting_days VARCHAR(80) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    professor_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    assessment_type ENUM('Quiz', 'Assessment', 'Exam') NOT NULL DEFAULT 'Assessment',
    due_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assignment_completions (
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
