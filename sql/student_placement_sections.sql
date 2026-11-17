SET @migration_schema = DATABASE();
SET @migration_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'student_academic_placements'
          AND COLUMN_NAME = 'cohort_section'
    ),
    'ALTER TABLE student_academic_placements MODIFY cohort_section VARCHAR(20) DEFAULT NULL',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;