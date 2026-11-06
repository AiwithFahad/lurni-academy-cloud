-- =====================================================================
-- Lurni Academy Cloud — Migration 002: Course & assessment fields, course payments
-- MySQL 8 · InnoDB · Non-destructive (adds columns/tables only, except
-- 002 which replaces the submissions unique key with a plain index to
-- allow multiple attempts — no data is deleted).
-- Run in numeric order on an EXISTING install whose schema matches the
-- original 39-table database.sql. Fresh installs do NOT need these:
-- database.sql already creates the final schema directly.
-- =====================================================================

USE lurni_academy_cloud;


-- courses: learning objectives / prerequisites, total duration, and
-- marketplace-style approval + visibility controls
ALTER TABLE courses
  ADD COLUMN objectives TEXT NULL AFTER description,
  ADD COLUMN requirements TEXT NULL,
  ADD COLUMN duration_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_featured,
  ADD COLUMN approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status,
  ADD COLUMN visibility ENUM('public','unlisted','private') NOT NULL DEFAULT 'public';

-- quizzes: 0 = unlimited attempts
ALTER TABLE quizzes
  ADD COLUMN max_attempts SMALLINT NOT NULL DEFAULT 0 AFTER time_limit_minutes;

-- assignments: allow students to resubmit after grading / feedback
ALTER TABLE assignments
  ADD COLUMN allow_resubmission TINYINT(1) NOT NULL DEFAULT 0 AFTER max_points;

-- submissions: a student may now submit more than once per assignment,
-- so the (assignment_id, student_id) UNIQUE key becomes a plain index
-- and each row records which attempt it is
ALTER TABLE submissions
  DROP INDEX uq_submission,
  ADD COLUMN attempt_no SMALLINT NOT NULL DEFAULT 1 AFTER student_id,
  ADD KEY idx_subm_assignment_student (assignment_id, student_id);

-- payments: distinguish platform subscription payments from one-off
-- course purchases, and link course purchases to the course bought
ALTER TABLE payments
  ADD COLUMN kind ENUM('subscription','course') NOT NULL DEFAULT 'subscription' AFTER user_id,
  ADD COLUMN course_id INT UNSIGNED NULL,
  ADD CONSTRAINT fk_pay_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;
