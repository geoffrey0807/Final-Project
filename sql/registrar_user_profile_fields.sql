USE apex_learning_hub;

SET @migration_schema = DATABASE();
SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'middle_name'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN middle_name VARCHAR(50) NULL AFTER last_name'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'suffix'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN suffix VARCHAR(20) NULL AFTER middle_name'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'id_number'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN id_number VARCHAR(30) NULL AFTER suffix'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'program'
    ),
    'DO 0',
    'ALTER TABLE users ADD COLUMN program VARCHAR(150) NULL AFTER id_number'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'uq_users_id_number'
    ),
    'DO 0',
    'ALTER TABLE users ADD UNIQUE INDEX uq_users_id_number (id_number)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
