USE apex_learning_hub;

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
