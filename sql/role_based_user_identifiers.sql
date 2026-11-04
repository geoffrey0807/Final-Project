USE apex_learning_hub;

DROP TEMPORARY TABLE IF EXISTS role_identifier_backfill;

CREATE TEMPORARY TABLE role_identifier_backfill AS
SELECT
    missing.id,
    CASE missing.role
        WHEN 'admin' THEN 'ADM'
        WHEN 'registrar' THEN 'REG'
        WHEN 'dean' THEN 'D'
        WHEN 'professor' THEN 'EDU'
        WHEN 'student' THEN 'STU'
    END AS id_prefix,
    YEAR(missing.created_at) AS identifier_year,
    GREATEST(
        CASE missing.role
            WHEN 'admin' THEN 0
            WHEN 'registrar' THEN 0
            WHEN 'dean' THEN 1000
            WHEN 'professor' THEN 2000
            WHEN 'student' THEN 3000
        END,
        COALESCE(existing.last_number, 0)
    ) + ROW_NUMBER() OVER (
        PARTITION BY missing.role, YEAR(missing.created_at)
        ORDER BY missing.id
    ) AS identifier_number
FROM users AS missing
LEFT JOIN (
    SELECT
        SUBSTRING_INDEX(id_number, '-', 1) AS id_prefix,
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(id_number, '-', -2), '-', 1) AS UNSIGNED) AS identifier_year,
        MAX(CAST(SUBSTRING_INDEX(id_number, '-', -1) AS UNSIGNED)) AS last_number
    FROM users
    WHERE id_number REGEXP '^(ADM|REG|D|EDU|STU)-[0-9]{4}-[0-9]{4}$'
    GROUP BY id_prefix, identifier_year
) AS existing
    ON existing.id_prefix = CASE missing.role
        WHEN 'admin' THEN 'ADM'
        WHEN 'registrar' THEN 'REG'
        WHEN 'dean' THEN 'D'
        WHEN 'professor' THEN 'EDU'
        WHEN 'student' THEN 'STU'
    END
    AND existing.identifier_year = YEAR(missing.created_at)
WHERE missing.id_number IS NULL;

UPDATE users AS account
JOIN role_identifier_backfill AS generated ON generated.id = account.id
SET account.id_number = CONCAT(
    generated.id_prefix,
    '-',
    generated.identifier_year,
    '-',
    LPAD(generated.identifier_number, 4, '0')
);

DROP TEMPORARY TABLE role_identifier_backfill;
