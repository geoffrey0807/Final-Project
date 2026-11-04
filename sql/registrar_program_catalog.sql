USE apex_learning_hub;

CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @migration_schema = DATABASE();
SET @has_department_program_catalog = EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @migration_schema
      AND TABLE_NAME = 'programs'
      AND COLUMN_NAME = 'code'
);
SET @migration_sql = IF(
    @has_department_program_catalog,
    'DO 0',
    'INSERT INTO programs (name) VALUES (''Computer Science''), (''Information Technology'') ON DUPLICATE KEY UPDATE name = VALUES(name)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'program_id'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN program_id INT NULL AFTER id_number'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @has_department_program_catalog,
    'DO 0',
    'INSERT INTO programs (name) SELECT DISTINCT TRIM(program) FROM users WHERE program IS NOT NULL AND TRIM(program) <> '''' ON DUPLICATE KEY UPDATE name = VALUES(name)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @has_department_program_catalog,
    'DO 0',
    'UPDATE users AS u JOIN programs AS p ON p.name = TRIM(u.program) SET u.program_id = p.id WHERE u.program_id IS NULL AND u.program IS NOT NULL AND TRIM(u.program) <> '''''
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'idx_users_program_id'
    ),
    'DO 0',
    'ALTER TABLE users ADD INDEX idx_users_program_id (program_id)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND CONSTRAINT_NAME = 'fk_users_program'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'DO 0',
    'ALTER TABLE users ADD CONSTRAINT fk_users_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
