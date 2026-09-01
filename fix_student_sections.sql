-- Fix missing or NULL section values in students table
-- Run this to ensure all students have a valid section (A or B)

-- Set all NULL sections to 'A'
UPDATE students
SET section = 'A'
WHERE section IS NULL;

-- Set all empty sections to 'A'
UPDATE students
SET section = 'A'
WHERE TRIM(section) = '';

-- Verify fix
SELECT COUNT(*) as null_sections FROM students WHERE section IS NULL;
SELECT COUNT(*) as empty_sections FROM students WHERE TRIM(section) = '';
SELECT COUNT(*) as valid_sections FROM students WHERE section IN ('A', 'B');
SELECT COUNT(*) as invalid_sections FROM students WHERE section NOT IN ('A', 'B');

-- Show all students with their sections
SELECT id, roll_no, student_name, department, section FROM students ORDER BY department, section;
