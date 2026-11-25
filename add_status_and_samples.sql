-- Run these in phpMyAdmin > student_db > SQL tab

-- STEP 1 (required): add the Status column
ALTER TABLE students
ADD status VARCHAR(10) NOT NULL DEFAULT 'Active';

-- STEP 2 (optional): sample data to test search, filters and screenshots
INSERT INTO students (student_number, first_name, last_name, course, year_level, status) VALUES
('2026-001', 'Juan',  'Dela Cruz', 'BS Information Technology', '1st Year', 'Active'),
('2026-002', 'Maria', 'Santos',    'BS Computer Science',       '2nd Year', 'Active'),
('2026-003', 'Pedro', 'Reyes',     'BS Information Systems',    '3rd Year', 'Inactive'),
('2026-004', 'Ana',   'Garcia',    'BS Information Technology', '4th Year', 'Active');
