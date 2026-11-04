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
