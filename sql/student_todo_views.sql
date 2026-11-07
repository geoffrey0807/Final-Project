USE apex_learning_hub;

CREATE TABLE IF NOT EXISTS student_todo_views (
    student_id INT NOT NULL,
    academic_year CHAR(9) NOT NULL,
    semester ENUM('first', 'second') NOT NULL,
    last_viewed_at DATETIME NOT NULL,
    PRIMARY KEY (student_id, academic_year, semester),
    INDEX idx_student_todo_views_period (academic_year, semester),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;