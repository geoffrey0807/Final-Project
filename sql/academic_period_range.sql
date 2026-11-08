INSERT IGNORE INTO academic_periods (academic_year, semester, status, opened_at, closed_at)
SELECT academic_year, semester,
       CASE
           WHEN academic_year = current_year AND semester = current_semester THEN 'open'
           WHEN period_end <= CURRENT_DATE THEN 'closed'
           ELSE 'planned'
       END,
       CASE WHEN academic_year = current_year AND semester = current_semester THEN NOW() ELSE NULL END,
       CASE
           WHEN period_end <= CURRENT_DATE
                AND NOT (academic_year = current_year AND semester = current_semester)
           THEN period_end
           ELSE NULL
       END
FROM (
    SELECT CONCAT(years.start_year, '-', years.start_year + 1) AS academic_year,
           semesters.semester,
           IF(semesters.semester = 'first',
              STR_TO_DATE(CONCAT(years.start_year, '-12-01'), '%Y-%m-%d'),
              STR_TO_DATE(CONCAT(years.start_year + 1, '-07-01'), '%Y-%m-%d')) AS period_end,
           CONCAT(
               IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE), YEAR(CURRENT_DATE) - 1),
               '-',
               IF(MONTH(CURRENT_DATE) >= 7, YEAR(CURRENT_DATE) + 1, YEAR(CURRENT_DATE))
           ) AS current_year,
           IF(MONTH(CURRENT_DATE) BETWEEN 7 AND 11, 'first', 'second') AS current_semester
    FROM (
        SELECT 2022 AS start_year UNION ALL SELECT 2023 UNION ALL SELECT 2024
        UNION ALL SELECT 2025 UNION ALL SELECT 2026 UNION ALL SELECT 2027
    ) years
    CROSS JOIN (
        SELECT 'first' AS semester UNION ALL SELECT 'second'
    ) semesters
) periods;