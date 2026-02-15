USE learning_portal;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE chatbot_messages;
TRUNCATE TABLE alerts;
TRUNCATE TABLE tutoring_sessions;
TRUNCATE TABLE community_hubs;
TRUNCATE TABLE module_progress;
TRUNCATE TABLE learning_modules;
TRUNCATE TABLE behavior_records;
TRUNCATE TABLE assessment_scores;
TRUNCATE TABLE attendance_records;
TRUNCATE TABLE student_background_profiles;
TRUNCATE TABLE student_accounts;
TRUNCATE TABLE students;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (full_name, email, role_name, password_hash, is_active) VALUES
('System Administrator', 'admin@platform.local', 'admin', '$2y$10$.WY3JF4mJsYf5aD2KKf3LOv3.v6k8TNF3zX4GNkxgvzS0EmPfvocW', 1),
('Lead Educator', 'educator@platform.local', 'educator', '$2y$10$BV7uVdFrROduCjIlKfScaOZUFZutC3h0uIzpsAP6yHIDozey2Xpgm', 1),
('Student Counselor', 'counselor@platform.local', 'counselor', '$2y$10$a.6lBIS.tvGe/25VNLCgWeayGcVmQOt87imjarGgkFO/9aINpdhAi', 1);

INSERT INTO students (student_code, full_name, grade_level, community_zone) VALUES
('STU-001', 'Amina Lopez', '8', 'North District'),
('STU-002', 'Jayden Cruz', '9', 'River Ward'),
('STU-003', 'Priya Nair', '10', 'North District'),
('STU-004', 'Noah Dela Rosa', '8', 'Hill Block'),
('STU-005', 'Fatima Rahman', '11', 'River Ward');

INSERT INTO student_accounts (student_id, email, password_hash, is_active) VALUES
(1, 'student1@platform.local', '$2y$10$nnK3nIdhYbLUUocha3hV/ekJJu4enxWCRgh7sVruREDGqmNS/tASa', 1);

INSERT INTO student_background_profiles (
    student_id, attempted_exam, target_stream, current_stream, stream_mismatch,
    financial_issues, worked_after_school, work_history_note, study_gap_months,
    confidence_level, primary_challenge, goals
) VALUES
(1, 'neet', 'Medicine', 'B.Sc Life Sciences', 1, 1, 1, 'Part-time evening store work after school', 18, 'low', 'Forgot core biology and chemistry basics after a study break', 'Rebuild foundations and transition to a healthcare-related career path');

INSERT INTO attendance_records (student_id, attendance_date, status) VALUES
(1, '2026-01-05', 'present'), (1, '2026-01-06', 'late'), (1, '2026-01-07', 'present'), (1, '2026-01-08', 'absent'), (1, '2026-01-09', 'present'),
(2, '2026-01-05', 'absent'), (2, '2026-01-06', 'absent'), (2, '2026-01-07', 'present'), (2, '2026-01-08', 'absent'), (2, '2026-01-09', 'late'),
(3, '2026-01-05', 'present'), (3, '2026-01-06', 'present'), (3, '2026-01-07', 'present'), (3, '2026-01-08', 'present'), (3, '2026-01-09', 'present'),
(4, '2026-01-05', 'late'), (4, '2026-01-06', 'absent'), (4, '2026-01-07', 'absent'), (4, '2026-01-08', 'present'), (4, '2026-01-09', 'absent'),
(5, '2026-01-05', 'present'), (5, '2026-01-06', 'present'), (5, '2026-01-07', 'late'), (5, '2026-01-08', 'present'), (5, '2026-01-09', 'present');

INSERT INTO assessment_scores (student_id, subject_name, score, assessment_date) VALUES
(1, 'Mathematics', 62, '2026-01-10'), (1, 'Science', 68, '2026-01-11'),
(2, 'Mathematics', 48, '2026-01-10'), (2, 'Science', 52, '2026-01-11'),
(3, 'Mathematics', 91, '2026-01-10'), (3, 'Science', 88, '2026-01-11'),
(4, 'Mathematics', 44, '2026-01-10'), (4, 'Science', 58, '2026-01-11'),
(5, 'Mathematics', 78, '2026-01-10'), (5, 'Science', 81, '2026-01-11');

INSERT INTO behavior_records (student_id, event_date, severity, note) VALUES
(1, '2026-01-09', 'moderate', 'Withdrawal observed during class activity'),
(2, '2026-01-07', 'high', 'Conflict with peers and repeated disengagement'),
(2, '2026-01-09', 'moderate', 'Refused assignment submission'),
(4, '2026-01-06', 'moderate', 'Frequent class disruption'),
(4, '2026-01-08', 'high', 'Aggressive verbal outburst'),
(5, '2026-01-07', 'low', 'Minor focus issue resolved quickly');

INSERT INTO learning_modules (title, subject_name, difficulty, offline_enabled, estimated_minutes) VALUES
('Fractions Mastery Sprint', 'Mathematics', 'beginner', 1, 25),
('Reading Comprehension Lab', 'Language Arts', 'intermediate', 1, 30),
('Foundations of Ecology', 'Science', 'beginner', 1, 20),
('Algebra Bridge Program', 'Mathematics', 'intermediate', 1, 35);

INSERT INTO module_progress (student_id, module_id, completion_pct, mastery_score, last_synced_at) VALUES
(1, 1, 70, 64, '2026-02-10 14:20:00'),
(2, 4, 40, 49, '2026-02-10 14:25:00'),
(3, 3, 95, 92, '2026-02-10 14:30:00'),
(4, 1, 35, 46, '2026-02-10 14:35:00'),
(5, 2, 88, 79, '2026-02-10 14:40:00');

INSERT INTO community_hubs (hub_name, location_name, internet_reliability, active_tutors) VALUES
('North Learning Hub', 'North District Library', 'medium', 6),
('River Digital Center', 'River Ward Community Hall', 'low', 4),
('Hill Knowledge Point', 'Hill Block Youth Center', 'medium', 5);

INSERT INTO tutoring_sessions (student_id, hub_id, tutor_name, subject_name, session_date, status) VALUES
(2, 2, 'Coach Evelyn M.', 'Mathematics', '2026-02-15 15:00:00', 'scheduled'),
(4, 3, 'Coach Mark J.', 'Science', '2026-02-16 16:00:00', 'scheduled'),
(1, 1, 'Coach Hana P.', 'Language Arts', '2026-02-12 13:00:00', 'completed');

INSERT INTO alerts (student_id, alert_level, alert_message, recommended_action, status) VALUES
(2, 'high', 'High dropout risk detected: low attendance and weak performance trend.', 'Assign counselor within 24h, start family outreach, and enroll in math recovery path.', 'open'),
(4, 'high', 'Behavior escalation with repeated absences indicates urgent risk.', 'Trigger behavior intervention plan and schedule weekly tutor check-ins.', 'in_progress'),
(1, 'medium', 'Attendance instability may impact progression in core subjects.', 'Set attendance mentor and push offline micro-lessons for missed units.', 'open');

