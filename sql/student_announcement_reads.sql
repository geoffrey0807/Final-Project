USE apex_learning_hub;

CREATE TABLE IF NOT EXISTS student_announcement_reads (
    announcement_id INT NOT NULL,
    student_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, student_id),
    INDEX idx_student_announcement_reads_student (student_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
