INSERT INTO users (full_name, email, password, role) VALUES
('Ahmad Faiz', 'admin1@uptm.edu.my', 'hashed_password_1', 'admin'),
('Siti Aminah', 'admin2@uptm.edu.my', 'hashed_password_2', 'admin'),
('Dr. Rahim Kassim', 'rahim@uptm.edu.my', 'hashed_password_3', 'lecturer'),
('Dr. Noraini Yusof', 'noraini@uptm.edu.my', 'hashed_password_4', 'lecturer'),
('Ali Hassan', 'ali@student.uptm.edu.my', 'hashed_password_5', 'student'),
('Fatimah Zahra', 'fatimah@student.uptm.edu.my', 'hashed_password_6', 'student'),
('Yusuf', 'yusuf@student.uptm.edu.my', 'hashed_password_7', 'student'),
('Salman', 'salman@student.uptm.edu.my', 'hashed_password_8', 'student'),
('Adila', 'adila@student.uptm.edu.my', 'hashed_password_9', 'student'),
('Athirah', 'athirah@student.uptm.edu.my', 'hashed_password_10', 'student');

INSERT INTO courses (course_code, course_name, lecturer_id) VALUES
('SWC3633', 'Web API Development', 3),
('SWC3013', 'Database Systems', 4),
('SWC3223', 'Software Engineering', 3),
('SWC2913', 'Data Structures', 4),
('SWC3403', 'Mobile App Development', 3);

INSERT INTO examinations (course_id, exam_title, exam_date, exam_time, venue) VALUES
(1, 'Web API Development Final Exam', '2026-12-10', '09:00:00', 'Hall A'),
(2, 'Database Systems Final Exam', '2026-12-11', '14:00:00', 'Hall B'),
(3, 'Software Engineering Final Exam', '2026-12-12', '09:00:00', 'Hall A'),
(4, 'Data Structures Final Exam', '2026-12-13', '09:00:00', 'Hall C'),
(5, 'Mobile App Development Final Exam', '2026-12-14', '14:00:00', 'Hall B');

INSERT INTO student_course_registration (student_id, course_id) VALUES
(5, 1), (6, 1), (7, 1), (8, 1), (9, 1),
(5, 2), (6, 2), (10, 2);

INSERT INTO results (exam_id, student_id, score, grade) VALUES
(1, 5, 85.50, 'A'),
(1, 6, 72.00, 'B'),
(1, 7, 91.25, 'A'),
(1, 8, 65.00, 'C'),
(1, 9, 78.75, 'B'),
(2, 5, 88.00, 'A'),
(2, 6, 70.50, 'B');