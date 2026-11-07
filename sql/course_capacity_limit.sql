USE apex_learning_hub;

ALTER TABLE courses
    ALTER max_capacity SET DEFAULT 10;

UPDATE courses
SET max_capacity = 10
WHERE archived_at IS NULL
  AND max_capacity > 10;
