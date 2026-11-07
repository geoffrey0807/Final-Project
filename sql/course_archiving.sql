USE apex_learning_hub;

SET @migration_schema = DATABASE();

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'courses'
          AND COLUMN_NAME = 'archived_at'
    ),
    'DO 0',
    'ALTER TABLE courses ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER created_at'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @migration_schema
          AND TABLE_NAME = 'courses'
          AND COLUMN_NAME = 'active_course_code'
    ),
    'DO 0',
    'ALTER TABLE courses ADD COLUMN active_course_code VARCHAR(20) GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL THEN course_code ELSE NULL END) STORED'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @course_index_is_current = EXISTS (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @migration_schema
      AND TABLE_NAME = 'courses'
      AND INDEX_NAME = 'uq_courses_period_code_section'
    GROUP BY INDEX_NAME
    HAVING MIN(NON_UNIQUE) = 0
       AND GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') =
           'academic_year,semester,active_course_code,section_name'
);

SET @migration_sql = IF(
    @course_index_is_current,
    'DO 0',
    IF(
        EXISTS (
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = @migration_schema
              AND TABLE_NAME = 'courses'
              AND INDEX_NAME = 'uq_courses_period_code_section'
        ),
        'ALTER TABLE courses DROP INDEX uq_courses_period_code_section',
        'DO 0'
    )
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    @course_index_is_current,
    'DO 0',
    'ALTER TABLE courses ADD UNIQUE KEY uq_courses_period_code_section (academic_year, semester, active_course_code, section_name)'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
