ALTER TABLE announcements
    CHANGE COLUMN title headline VARCHAR(150) NOT NULL,
    ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER message;

CREATE TABLE IF NOT EXISTS announcement_audience_roles (
    announcement_id INT NOT NULL,
    role ENUM('admin', 'registrar', 'dean', 'professor', 'student') NOT NULL,
    PRIMARY KEY (announcement_id, role),
    INDEX idx_announcement_audience_role (role),
    CONSTRAINT fk_announcement_audience_announcement
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
