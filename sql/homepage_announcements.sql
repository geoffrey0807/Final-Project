ALTER TABLE announcements
    ADD COLUMN is_homepage_notice TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path,
    ADD COLUMN homepage_category ENUM('academic', 'system', 'event', 'policy') DEFAULT NULL AFTER is_homepage_notice,
    ADD INDEX idx_announcements_homepage_latest (is_homepage_notice, created_at, id);
