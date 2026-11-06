-- =====================================================================
-- Lurni Academy Cloud — Migration 004: Commerce, content & notifications (attempt answers, refunds, blog, email queue)
-- MySQL 8 · InnoDB · Non-destructive (adds columns/tables only, except
-- 002 which replaces the submissions unique key with a plain index to
-- allow multiple attempts — no data is deleted).
-- Run in numeric order on an EXISTING install whose schema matches the
-- original 39-table database.sql. Fresh installs do NOT need these:
-- database.sql already creates the final schema directly.
-- =====================================================================

USE lurni_academy_cloud;


CREATE TABLE IF NOT EXISTS quiz_attempt_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_answer_ids VARCHAR(255) NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt_question (attempt_id, question_id),
  INDEX idx_qaa_question (question_id),
  CONSTRAINT fk_qaa_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_qaa_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_refunds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  payment_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) NULL,
  status ENUM('recorded','processed') NOT NULL DEFAULT 'recorded',
  processed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_refund_payment (payment_id),
  CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  author_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  category VARCHAR(60) NULL,
  excerpt VARCHAR(255) NULL,
  content TEXT NULL,
  cover_image VARCHAR(255) NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_blog_tenant_slug (tenant_id, slug),
  INDEX idx_blog_status (status),
  CONSTRAINT fk_blog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  body TEXT NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  INDEX idx_emailq_tenant (tenant_id),
  INDEX idx_emailq_status (status),
  CONSTRAINT fk_emailq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB;
