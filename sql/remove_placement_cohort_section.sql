-- Migration: Remove cohort_section from student_academic_placements
ALTER TABLE student_academic_placements
  DROP COLUMN IF EXISTS cohort_section;
