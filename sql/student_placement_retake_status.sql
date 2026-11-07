ALTER TABLE student_academic_placements
    MODIFY COLUMN progression_status ENUM('initial', 'new', 'continued', 'promoted', 'relegated', 'retake') NOT NULL;