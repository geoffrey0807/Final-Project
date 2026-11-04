USE apex_learning_hub;

ALTER TABLE users
    MODIFY role ENUM('admin', 'registrar', 'dean', 'professor', 'student') NOT NULL DEFAULT 'student';

SET @migration_schema = DATABASE();
SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'is_active'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER profile_photo'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'archived_at'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER is_active'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'created_by'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN created_by INT NULL DEFAULT NULL AFTER archived_at'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'idx_users_account_state'
    ),
    'DO 0',
    'CREATE INDEX idx_users_account_state ON users (is_active, archived_at, role)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'idx_users_created_by'
    ),
    'DO 0',
    'CREATE INDEX idx_users_created_by ON users (created_by)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND CONSTRAINT_NAME = 'fk_users_created_by'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'DO 0',
    'ALTER TABLE users ADD CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
