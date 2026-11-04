USE apex_learning_hub;

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
