USE apex_learning_hub;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO departments (code, name)
VALUES
    ('DSEAD', 'Department of Software Engineering & Application Development'),
    ('DSCS', 'Department of Computer Science'),
    ('DISWT', 'Department of Information Systems & Web Technologies')
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET @migration_schema = DATABASE();
SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @migration_schema AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department_id'),
    'DO 0',
    'ALTER TABLE users ADD COLUMN department_id INT NULL AFTER program_id'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @migration_schema AND TABLE_NAME = 'programs' AND COLUMN_NAME = 'code'),
    'DO 0',
    'ALTER TABLE programs ADD COLUMN code VARCHAR(20) NULL AFTER id'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @migration_schema AND TABLE_NAME = 'programs' AND COLUMN_NAME = 'department_id'),
    'DO 0',
    'ALTER TABLE programs ADD COLUMN department_id INT NULL AFTER name'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE programs
SET code = 'BSCS', name = 'BS in Computer Science',
    department_id = (SELECT id FROM departments WHERE code = 'DSCS')
WHERE code IS NULL AND name IN ('Computer Science', 'BS in Computer Science');

UPDATE programs
SET code = 'BSIT', name = 'BS in Information Systems',
    department_id = (SELECT id FROM departments WHERE code = 'DISWT')
WHERE code IS NULL AND name IN ('Information Technology', 'BS in Information Systems');

UPDATE programs SET code = CONCAT('LEG', id), is_active = 0
WHERE code IS NULL;

INSERT INTO programs (code, name, department_id, is_active)
SELECT 'BSSE', 'BS in Software Engineering', id, 1 FROM departments WHERE code = 'DSEAD'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id, is_active)
SELECT 'BSMAD', 'BS in Mobile Application Development', id, 1 FROM departments WHERE code = 'DSEAD'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id, is_active)
SELECT 'BSCS', 'BS in Computer Science', id, 1 FROM departments WHERE code = 'DSCS'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id, is_active)
SELECT 'GPMC', 'BS in Game Programming / Multimedia Computing', id, 1 FROM departments WHERE code = 'DSCS'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id, is_active)
SELECT 'BSIT', 'BS in Information Systems', id, 1 FROM departments WHERE code = 'DISWT'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;
INSERT INTO programs (code, name, department_id, is_active)
SELECT 'ADFSWD', 'Associate Degree in Full-Stack Web Development', id, 1 FROM departments WHERE code = 'DISWT'
ON DUPLICATE KEY UPDATE name = VALUES(name), department_id = VALUES(department_id), is_active = 1;

SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @migration_schema AND TABLE_NAME = 'programs' AND INDEX_NAME = 'uq_programs_code'),
    'DO 0',
    'ALTER TABLE programs ADD UNIQUE INDEX uq_programs_code (code)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE users AS account
JOIN programs AS program ON program.id = account.program_id
SET account.department_id = program.department_id
WHERE account.role IN ('dean', 'professor', 'student') AND account.department_id IS NULL;

UPDATE users AS account
JOIN programs AS program ON program.name = TRIM(account.program)
SET account.program_id = program.id,
    account.department_id = program.department_id
WHERE account.role = 'student'
  AND account.program_id IS NULL
  AND account.program IS NOT NULL
  AND program.is_active = 1;

UPDATE users
SET program_id = (SELECT id FROM programs WHERE code = 'BSCS'),
    department_id = (SELECT department_id FROM programs WHERE code = 'BSCS')
WHERE role = 'student' AND program_id IS NULL AND TRIM(COALESCE(program, '')) = 'Computer Science';

UPDATE users
SET program_id = (SELECT id FROM programs WHERE code = 'BSIT'),
    department_id = (SELECT department_id FROM programs WHERE code = 'BSIT')
WHERE role = 'student' AND program_id IS NULL AND TRIM(COALESCE(program, '')) = 'Information Technology';

UPDATE users
SET department_id = CASE
    WHEN TRIM(COALESCE(program, '')) IN ('Computer Science', 'BSCS', 'GPMC')
        THEN (SELECT id FROM departments WHERE code = 'DSCS')
    WHEN TRIM(COALESCE(program, '')) IN ('Information Technology', 'BSIT', 'ADFSWD')
        THEN (SELECT id FROM departments WHERE code = 'DISWT')
    WHEN TRIM(COALESCE(program, '')) IN ('BSSE', 'BSMAD')
        THEN (SELECT id FROM departments WHERE code = 'DSEAD')
    ELSE department_id
END
WHERE role IN ('dean', 'professor') AND department_id IS NULL;

UPDATE users SET program_id = NULL WHERE role IN ('dean', 'professor');

UPDATE users AS account
JOIN programs AS program ON program.id = account.program_id
SET account.department_id = program.department_id
WHERE account.role = 'student' AND account.department_id IS NULL;

SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @migration_schema AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_department_role'),
    'DO 0',
    'ALTER TABLE users ADD INDEX idx_users_department_role (department_id, role)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @migration_schema AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_department'),
    'DO 0',
    'ALTER TABLE users ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @migration_schema AND TABLE_NAME = 'programs' AND CONSTRAINT_NAME = 'fk_programs_department'),
    'DO 0',
    'ALTER TABLE programs ADD CONSTRAINT fk_programs_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

CREATE TABLE IF NOT EXISTS course_programs (
    course_id INT NOT NULL,
    program_id INT NOT NULL,
    PRIMARY KEY (course_id, program_id),
    INDEX idx_course_programs_program (program_id),
    CONSTRAINT fk_course_programs_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_course_programs_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO course_programs (course_id, program_id)
SELECT c.id, p.id
FROM courses c
JOIN users professor ON professor.id = c.professor_id AND professor.role = 'professor'
JOIN programs p ON p.department_id = professor.department_id AND p.is_active = 1;
