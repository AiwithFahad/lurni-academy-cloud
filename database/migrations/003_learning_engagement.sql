-- =====================================================================
-- Lurni Academy Cloud — Migration 003: Learning engagement (bookmarks, notes, announcements, discussions)
-- MySQL 8 · InnoDB · Non-destructive (adds columns/tables only, except
-- 002 which replaces the submissions unique key with a plain index to
-- allow multiple attempts — no data is deleted).
-- Run in numeric order on an EXISTING install whose schema matches the
-- original 39-table database.sql. Fresh installs do NOT need these:
-- database.sql already creates the final schema directly.
-- =====================================================================

USE lurni_academy_cloud;


CREATE TABLE IF NOT EXISTS lesson_bookmarks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lesson_bookmark (student_id, lesson_id),
  INDEX idx_bookmark_student_course (student_id, course_id),
  CONSTRAINT fk_bookmark_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookmark_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookmark_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookmark_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lesson_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lesson_note (student_id, lesson_id),
  INDEX idx_note_student_course (student_id, course_id),
  CONSTRAINT fk_note_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_note_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_note_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_note_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS course_announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  author_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_announce_course (course_id),
  CONSTRAINT fk_announce_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_announce_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_announce_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS discussions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  status ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_discussion_course (course_id),
  INDEX idx_discussion_parent (parent_id),
  CONSTRAINT fk_discussion_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_discussion_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_discussion_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL,
  CONSTRAINT fk_discussion_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_discussion_parent FOREIGN KEY (parent_id) REFERENCES discussions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
