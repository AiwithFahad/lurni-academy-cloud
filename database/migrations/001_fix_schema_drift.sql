-- =====================================================================
-- Lurni Academy Cloud — Migration 001: Fix schema drift (columns the PHP code already references)
-- MySQL 8 · InnoDB · Non-destructive (adds columns/tables only, except
-- 002 which replaces the submissions unique key with a plain index to
-- allow multiple attempts — no data is deleted).
-- Run in numeric order on an EXISTING install whose schema matches the
-- original 39-table database.sql. Fresh installs do NOT need these:
-- database.sql already creates the final schema directly.
-- =====================================================================

USE lurni_academy_cloud;


-- certificates: title / grade shown on certificates, revoked_at for revocation
ALTER TABLE certificates
  ADD COLUMN title VARCHAR(255) NULL AFTER certificate_code,
  ADD COLUMN revoked_at DATETIME NULL AFTER issued_at,
  ADD COLUMN grade VARCHAR(20) NULL;

-- enrollments: how the enrollment happened + last course access; 'pending'
-- added to the status enum (e.g. awaiting payment verification)
ALTER TABLE enrollments
  ADD COLUMN source VARCHAR(30) NOT NULL DEFAULT 'self' AFTER status,
  ADD COLUMN last_accessed_at DATETIME NULL AFTER completed_at,
  MODIFY COLUMN status ENUM('active','completed','cancelled','pending') NOT NULL DEFAULT 'active';

-- quiz_attempts: per-attempt answers snapshot + instructor feedback
-- (the legacy `answers` JSON column is kept untouched for compatibility)
ALTER TABLE quiz_attempts
  ADD COLUMN answers_json JSON NULL AFTER answers,
  ADD COLUMN feedback TEXT NULL,
  ADD COLUMN feedback_by INT UNSIGNED NULL;
