USE apex_learning_hub;

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

INSERT IGNORE INTO user_announcement_reads (announcement_id, user_id, read_at)
SELECT announcement_id, student_id, read_at
FROM student_announcement_reads;

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

ALTER TABLE support_request_reads
    MODIFY message_hash CHAR(64) NOT NULL;
