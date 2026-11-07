USE apex_learning_hub;

DROP PROCEDURE IF EXISTS remove_coding_challenge_support;
DELIMITER //
CREATE PROCEDURE remove_coding_challenge_support()
BEGIN
    DECLARE coding_assessment_count INT DEFAULT 0;
    DECLARE coding_format_count INT DEFAULT 0;
    DECLARE coding_question_count INT DEFAULT 0;

    SELECT COUNT(*) INTO coding_assessment_count
    FROM assessments
    WHERE assessment_type = 'Coding Challenge';

    SELECT COUNT(*) INTO coding_format_count
    FROM assessments
    WHERE question_format = 'Coding Challenge';

    SELECT COUNT(*) INTO coding_question_count
    FROM assessment_questions
    WHERE question_type = 'coding_challenge';

    IF coding_assessment_count > 0 OR coding_format_count > 0 OR coding_question_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Coding Challenge records exist. Export or remove them before applying this migration.';
    END IF;

    ALTER TABLE assessments
        MODIFY COLUMN assessment_type ENUM('Quiz', 'Exam') NOT NULL,
        MODIFY COLUMN question_format ENUM('Multiple Choice', 'True or False', 'Mixed') NOT NULL;

    ALTER TABLE assessment_questions
        MODIFY COLUMN question_type ENUM('multiple_choice', 'true_false') NOT NULL;

    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'assessment_questions'
          AND COLUMN_NAME = 'starter_code'
    ) THEN
        ALTER TABLE assessment_questions DROP COLUMN starter_code;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'assessment_questions'
          AND COLUMN_NAME = 'test_cases_json'
    ) THEN
        ALTER TABLE assessment_questions DROP COLUMN test_cases_json;
    END IF;
END//
DELIMITER ;

CALL remove_coding_challenge_support();
DROP PROCEDURE remove_coding_challenge_support;
