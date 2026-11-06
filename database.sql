-- =====================================================================
-- Lurni Academy Cloud — Learning Management System SaaS
-- Core PHP 8 + MySQL 8 · Multi-tenant · Subscriptions · RBAC
-- Import:  mysql -u root -p < database.sql   (creates the database)
-- All demo accounts use the password "password" (bcrypt hash below,
-- verifiable with password_verify()).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS lurni_academy_cloud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lurni_academy_cloud;

-- ---------------------------------------------------------------------
-- PLATFORM / COMMON SAAS TABLES
-- ---------------------------------------------------------------------

CREATE TABLE tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  org_type ENUM('academy','school','university','training_center','individual') NOT NULL DEFAULT 'academy',
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  website VARCHAR(190) NULL,
  country VARCHAR(80) NULL,
  logo VARCHAR(255) NULL,
  status ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','instructor','student') NOT NULL DEFAULT 'student',
  status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  avatar VARCHAR(255) NULL,
  phone VARCHAR(40) NULL,
  email_verified_at DATETIME NULL,
  verification_token VARCHAR(120) NULL,
  activation_token VARCHAR(120) NULL,
  two_factor_secret VARCHAR(120) NULL,
  remember_token VARCHAR(64) NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_tenant (tenant_id),
  INDEX idx_users_role (role),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  label VARCHAR(140) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tenant_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  UNIQUE KEY uq_tenant_setting (tenant_id, setting_key),
  CONSTRAINT fk_ts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE platform_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80) NOT NULL UNIQUE,
  setting_value TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE subscription_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_yearly DECIMAL(10,2) NOT NULL DEFAULT 0,
  trial_days SMALLINT NOT NULL DEFAULT 0,
  max_students INT NOT NULL DEFAULT 50,
  max_instructors INT NOT NULL DEFAULT 2,
  max_courses INT NOT NULL DEFAULT 5,
  storage_mb INT NOT NULL DEFAULT 500,
  features JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  status ENUM('trial','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trial',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  current_period_end DATETIME NULL,
  trial_ends_at DATETIME NULL,
  grace_days SMALLINT NOT NULL DEFAULT 7,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sub_tenant (tenant_id),
  INDEX idx_sub_status (status),
  CONSTRAINT fk_sub_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB;

CREATE TABLE subscription_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  metric VARCHAR(60) NOT NULL,
  used_value BIGINT NOT NULL DEFAULT 0,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usage (tenant_id, metric),
  CONSTRAINT fk_usage_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  subscription_id INT UNSIGNED NULL,
  plan_id INT UNSIGNED NULL,
  billing_cycle ENUM('monthly','yearly') NULL,
  invoice_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  kind ENUM('subscription','course') NOT NULL DEFAULT 'subscription',
  course_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(60) NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  method ENUM('manual','card','bank_transfer','paypal','stripe') NOT NULL DEFAULT 'manual',
  reference VARCHAR(120) NULL,
  status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pay_tenant (tenant_id),
  INDEX idx_pay_status (status),
  CONSTRAINT fk_pay_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  subscription_id INT UNSIGNED NULL,
  payment_id INT UNSIGNED NULL,
  invoice_number VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('unpaid','paid','void') NOT NULL DEFAULT 'unpaid',
  issued_at DATETIME NULL,
  due_at DATETIME NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inv_tenant (tenant_id),
  CONSTRAINT fk_inv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT NOT NULL DEFAULT 0,
  times_used INT NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE coupon_redemptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  payment_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_red_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_red_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_red_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  link VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user (user_id),
  INDEX idx_notif_tenant (tenant_id),
  CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ticket_status (status),
  CONSTRAINT fk_ticket_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE support_ticket_replies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reply_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_tenant (tenant_id),
  CONSTRAINT fk_activity_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity VARCHAR(80) NULL,
  entity_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_tenant (tenant_id),
  INDEX idx_audit_action (action)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_email (email)
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LMS TABLES (all tenant-isolated)
-- ---------------------------------------------------------------------

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  icon VARCHAR(60) NULL,
  color VARCHAR(20) NULL,
  description VARCHAR(255) NULL,
  status ENUM('active','hidden') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_tenant_slug (tenant_id, slug),
  CONSTRAINT fk_cat_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE instructors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  title VARCHAR(140) NULL,
  bio TEXT NULL,
  expertise VARCHAR(255) NULL,
  avatar VARCHAR(255) NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_inst_tenant (tenant_id),
  CONSTRAINT fk_inst_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_inst_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  student_code VARCHAR(40) NULL,
  phone VARCHAR(40) NULL,
  date_of_birth DATE NULL,
  address VARCHAR(255) NULL,
  status ENUM('active','suspended','graduated') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stu_tenant (tenant_id),
  CONSTRAINT fk_stu_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_stu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  instructor_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  subtitle VARCHAR(255) NULL,
  description TEXT NULL,
  objectives TEXT NULL,
  requirements TEXT NULL,
  thumbnail VARCHAR(255) NULL,
  level ENUM('beginner','intermediate','advanced','all_levels') NOT NULL DEFAULT 'beginner',
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  old_price DECIMAL(10,2) NULL,
  language VARCHAR(60) NOT NULL DEFAULT 'English',
  rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  visibility ENUM('public','unlisted','private') NOT NULL DEFAULT 'public',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_course_tenant_slug (tenant_id, slug),
  INDEX idx_course_tenant_status (tenant_id, status),
  CONSTRAINT fk_course_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_course_instructor FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE SET NULL,
  CONSTRAINT fk_course_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- payments is defined before courses (platform section above), so its
-- course FK can only be attached once courses exists:
ALTER TABLE payments ADD CONSTRAINT fk_pay_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;

CREATE TABLE course_modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  CONSTRAINT fk_mod_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_mod_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  type ENUM('video','text','quiz','assignment','resource') NOT NULL DEFAULT 'video',
  video_url VARCHAR(255) NULL,
  content LONGTEXT NULL,
  duration_minutes SMALLINT NOT NULL DEFAULT 0,
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lesson_course (course_id),
  CONSTRAINT fk_lesson_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_lesson_module FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lesson_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  status ENUM('started','completed') NOT NULL DEFAULT 'started',
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_progress (student_id, lesson_id),
  CONSTRAINT fk_prog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE resources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NULL,
  lesson_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(40) NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_res_tenant (tenant_id),
  CONSTRAINT fk_res_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
  CONSTRAINT fk_res_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('active','completed','cancelled','pending') NOT NULL DEFAULT 'active',
  source VARCHAR(30) NOT NULL DEFAULT 'self',
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  last_accessed_at DATETIME NULL,
  UNIQUE KEY uq_enroll (student_id, course_id),
  INDEX idx_enroll_tenant (tenant_id),
  CONSTRAINT fk_enroll_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quizzes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  pass_percent SMALLINT NOT NULL DEFAULT 70,
  time_limit_minutes SMALLINT NOT NULL DEFAULT 0,
  max_attempts SMALLINT NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_quiz_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_quiz_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_quiz_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  quiz_id INT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  type ENUM('single','multiple','true_false') NOT NULL DEFAULT 'single',
  points SMALLINT NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  CONSTRAINT fk_q_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_q_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  answer_text VARCHAR(255) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_a_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  quiz_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  score DECIMAL(8,2) NOT NULL DEFAULT 0,
  total_points DECIMAL(8,2) NOT NULL DEFAULT 0,
  percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  passed TINYINT(1) NOT NULL DEFAULT 0,
  answers JSON NULL,
  answers_json JSON NULL,
  feedback TEXT NULL,
  feedback_by INT UNSIGNED NULL,
  started_at DATETIME NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempt_student (student_id),
  CONSTRAINT fk_att_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  instructions TEXT NULL,
  due_date DATETIME NULL,
  max_points SMALLINT NOT NULL DEFAULT 100,
  allow_resubmission TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_asg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_asg_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  assignment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  attempt_no SMALLINT NOT NULL DEFAULT 1,
  content TEXT NULL,
  file_path VARCHAR(255) NULL,
  status ENUM('submitted','graded','late') NOT NULL DEFAULT 'submitted',
  grade DECIMAL(6,2) NULL,
  feedback TEXT NULL,
  graded_by INT UNSIGNED NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  graded_at DATETIME NULL,
  KEY idx_subm_assignment_student (assignment_id, student_id),
  CONSTRAINT fk_subm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_subm_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_subm_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  certificate_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  grade VARCHAR(20) NULL,
  UNIQUE KEY uq_cert (student_id, course_id),
  CONSTRAINT fk_cert_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  comment TEXT NULL,
  status ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (course_id, student_id),
  CONSTRAINT fk_rev_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_msg_receiver (receiver_id),
  CONSTRAINT fk_msg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- UPGRADE TABLES (migrations 003 + 004 — learning engagement,
-- commerce, content & notifications)
-- ---------------------------------------------------------------------

CREATE TABLE lesson_bookmarks (
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

CREATE TABLE lesson_notes (
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

CREATE TABLE course_announcements (
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

CREATE TABLE discussions (
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

CREATE TABLE quiz_attempt_answers (
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

CREATE TABLE payment_refunds (
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

CREATE TABLE blog_posts (
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

CREATE TABLE email_queue (
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

-- ---------------------------------------------------------------------
-- SEEDS: RBAC
-- ---------------------------------------------------------------------
INSERT INTO roles (id, name, label) VALUES
(1, 'super_admin', 'Platform Super Admin'),
(2, 'admin', 'Academy Admin'),
(3, 'instructor', 'Instructor'),
(4, 'student', 'Student');

INSERT INTO permissions (id, name, label) VALUES
(1, 'dashboard.view', 'View dashboard'),
(2, 'students.manage', 'Manage students'),
(3, 'instructors.manage', 'Manage instructors'),
(4, 'courses.manage', 'Manage courses'),
(5, 'categories.manage', 'Manage categories'),
(6, 'lessons.manage', 'Manage lessons & resources'),
(7, 'quizzes.manage', 'Manage quizzes & questions'),
(8, 'assignments.manage', 'Manage assignments'),
(9, 'grading.manage', 'Grade submissions'),
(10, 'enrollments.manage', 'Manage enrollments'),
(11, 'certificates.manage', 'Manage certificates'),
(12, 'reviews.manage', 'Moderate reviews'),
(13, 'payments.view', 'View payments & invoices'),
(14, 'reports.view', 'View reports'),
(15, 'settings.manage', 'Manage academy settings'),
(16, 'subscription.manage', 'Manage subscription'),
(17, 'teaching.courses', 'Teach & edit own courses'),
(18, 'learning.access', 'Access student portal'),
(19, 'messages.use', 'Send & receive messages');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN (
  'dashboard.view','students.manage','instructors.manage','courses.manage','categories.manage',
  'lessons.manage','quizzes.manage','assignments.manage','grading.manage','enrollments.manage',
  'certificates.manage','reviews.manage','payments.view','reports.view','settings.manage',
  'subscription.manage','messages.use');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN (
  'dashboard.view','teaching.courses','lessons.manage','quizzes.manage','assignments.manage',
  'grading.manage','messages.use');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN ('dashboard.view','learning.access','messages.use');

-- ---------------------------------------------------------------------
-- SEEDS: PLANS, COUPONS, PLATFORM SETTINGS
-- ---------------------------------------------------------------------
INSERT INTO subscription_plans (id, name, slug, description, price_monthly, price_yearly, trial_days, max_students, max_instructors, max_courses, storage_mb, features, sort_order) VALUES
(1, 'Free', 'free', 'For trying the platform', 0, 0, 0, 50, 2, 5, 500, '["Course builder","Up to 5 courses","Basic quizzes","Community support"]', 1),
(2, 'Starter', 'starter', 'For small academies', 19, 190, 14, 200, 5, 25, 5120, '["Everything in Free","Assignments & grading","Certificates","Email support"]', 2),
(3, 'Professional', 'professional', 'For growing academies', 49, 490, 14, 1000, 20, 100, 20480, '["Everything in Starter","Question banks","Advanced reports","Priority support"]', 3),
(4, 'Business', 'business', 'For institutions', 99, 990, 14, 5000, 50, 500, 102400, '["Everything in Professional","Custom branding","API access","Dedicated support"]', 4),
(5, 'Enterprise', 'enterprise', 'For universities & groups', 299, 2990, 30, -1, -1, -1, -1, '["Unlimited everything","SSO","SLA & onboarding","Account manager"]', 5);

INSERT INTO coupons (code, description, discount_type, discount_value, max_uses, times_used, expires_at, is_active) VALUES
('WELCOME10', 'Welcome offer — 10% off your first payment', 'percent', 10, 1000, 1, DATE_ADD(NOW(), INTERVAL 1 YEAR), 1),
('LAUNCH25', 'Launch week — 25% off', 'percent', 25, 200, 0, DATE_ADD(NOW(), INTERVAL 6 MONTH), 1);

INSERT INTO platform_settings (setting_key, setting_value) VALUES
('platform_name', 'Lurni Academy Cloud'),
('support_email', 'support@lurni.example'),
('currency', 'USD'),
('trial_days_default', '14'),
('maintenance_mode', '0');

-- ---------------------------------------------------------------------
-- SEEDS: DEMO ACADEMY + USERS  (password for every account: "password")
-- ---------------------------------------------------------------------
INSERT INTO tenants (id, name, slug, org_type, email, country, status) VALUES
(1, 'Demo Academy', 'demo-academy', 'academy', 'admin@demo.com', 'United States', 'active');

INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) VALUES
(1, 'brand_color', '#4f46e5'),
(1, 'site_title', 'Demo Academy'),
(1, 'welcome_message', 'Welcome to Demo Academy — learn from industry experts.'),
(1, 'onboarding_step', 'completed');

-- bcrypt hash of "password"
INSERT INTO users (id, tenant_id, name, email, password_hash, role, status, email_verified_at) VALUES
(1, NULL, 'Lurni Super Admin', 'super@lurni.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active', NOW()),
(2, 1, 'Ayesha Khan', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW()),
(3, 1, 'Daniel Reed', 'instructor@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor', 'active', NOW()),
(4, 1, 'Sara Mitchell', 'instructor2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor', 'active', NOW()),
(5, 1, 'Ali Hassan', 'student@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
(6, 1, 'Emma Wilson', 'student2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
(7, 1, 'Omar Farooq', 'student3@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW()),
(8, 1, 'Grace Lee', 'student4@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', NOW());

INSERT INTO instructors (id, tenant_id, user_id, title, bio, expertise) VALUES
(1, 1, 3, 'Senior Web Development Instructor', '10+ years building PHP and JavaScript applications; previously lead engineer at two ed-tech startups.', 'PHP, MySQL, JavaScript, Bootstrap'),
(2, 1, 4, 'UI/UX Design Lead', 'Product designer specialising in education platforms, design systems and Figma workflows.', 'UI/UX, Figma, Design Systems');

INSERT INTO students (id, tenant_id, user_id, student_code) VALUES
(1, 1, 5, 'STU-1001'),
(2, 1, 6, 'STU-1002'),
(3, 1, 7, 'STU-1003'),
(4, 1, 8, 'STU-1004');

-- Demo academy subscription (Professional, active) + one verified payment + paid invoice
INSERT INTO subscriptions (id, tenant_id, plan_id, billing_cycle, status, amount, starts_at, current_period_end, grace_days) VALUES
(1, 1, 3, 'monthly', 'active', 49.00, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY), 7);

INSERT INTO payments (id, tenant_id, subscription_id, user_id, amount, method, reference, status, verified_by, verified_at) VALUES
(1, 1, 1, 2, 49.00, 'manual', 'BANK-TRX-1001', 'completed', 1, DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT INTO invoices (tenant_id, subscription_id, payment_id, invoice_number, description, amount, status, issued_at, due_at, paid_at) VALUES
(1, 1, 1, 'INV-2026-0001', 'Professional plan (monthly)', 49.00, 'paid', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY));

UPDATE payments SET invoice_id = (SELECT id FROM invoices WHERE invoice_number = 'INV-2026-0001') WHERE id = 1;

-- ---------------------------------------------------------------------
-- SEEDS: LMS CONTENT
-- ---------------------------------------------------------------------
INSERT INTO categories (id, tenant_id, name, slug, icon, color, description) VALUES
(1, 1, 'Development', 'development', 'bi-code-slash', '#2563eb', 'Programming, web and software development'),
(2, 1, 'Design', 'design', 'bi-palette', '#7c3aed', 'UI/UX, graphic design and product design'),
(3, 1, 'Business', 'business', 'bi-briefcase', '#0ea5e9', 'Management, marketing and entrepreneurship'),
(4, 1, 'Data Science', 'data-science', 'bi-bar-chart', '#db2777', 'Data, analytics and machine learning'),
(5, 1, 'Languages', 'languages', 'bi-translate', '#059669', 'Language and communication skills');

INSERT INTO courses (id, tenant_id, instructor_id, category_id, title, slug, subtitle, description, thumbnail, level, price, old_price, rating, status, is_featured) VALUES
(1, 1, 1, 1, 'PHP & MySQL Bootcamp: Build Real Web Apps', 'php-mysql-bootcamp', 'From zero to a deployed CRUD application', 'A complete, project-based bootcamp covering Core PHP 8, PDO, MySQL, authentication, and building a full multi-page web application step by step.', '/assets/img/course-php.svg', 'beginner', 49.00, 99.00, 4.8, 'published', 1),
(2, 1, 1, 1, 'Modern JavaScript & Bootstrap 5 Masterclass', 'javascript-bootstrap-masterclass', 'Interactive UIs the professional way', 'Master modern JavaScript (ES2024), the DOM, Fetch/AJAX and Bootstrap 5 by building dashboards, galleries and real-time widgets.', '/assets/img/course-js.svg', 'intermediate', 39.00, 79.00, 4.7, 'published', 1),
(3, 1, 2, 2, 'UI/UX Design Fundamentals with Figma', 'ui-ux-figma-fundamentals', 'Design products people love', 'Learn the full product design workflow: research, wireframes, design systems, prototyping and handoff in Figma.', '/assets/img/course-design.svg', 'beginner', 45.00, 89.00, 4.9, 'published', 1),
(4, 1, 2, 3, 'Digital Marketing Mastery', 'digital-marketing-mastery', 'SEO, ads and analytics that convert', 'A practical course on SEO, paid ads, email funnels and analytics for growing any business online.', '/assets/img/course-marketing.svg', 'all_levels', 35.00, 69.00, 4.6, 'published', 0),
(5, 1, 1, 4, 'Data Science with Python — Foundations', 'data-science-python-foundations', 'Pandas, charts and your first ML model', 'Start data science with Python: NumPy, Pandas, visualisation and a first machine-learning model, explained simply.', '/assets/img/course-data.svg', 'beginner', 59.00, 119.00, 4.8, 'published', 1),
(6, 1, 2, 5, 'English Communication Skills', 'english-communication-skills', 'Speak and write with confidence', 'Improve grammar, professional writing, presentations and interview English with guided practice.', '/assets/img/course-english.svg', 'all_levels', 25.00, 49.00, 4.5, 'published', 0);

INSERT INTO course_modules (id, tenant_id, course_id, title, sort_order) VALUES
(1, 1, 1, 'Getting Started with PHP', 1),
(2, 1, 1, 'Working with MySQL & PDO', 2),
(3, 1, 1, 'Building the Project', 3),
(4, 1, 3, 'Design Foundations', 1),
(5, 1, 3, 'Figma in Practice', 2);

INSERT INTO lessons (id, tenant_id, course_id, module_id, title, type, video_url, content, duration_minutes, is_preview, sort_order) VALUES
(1, 1, 1, 1, 'Welcome & Course Roadmap', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Overview of what you will build and how to get the most from this bootcamp.', 6, 1, 1),
(2, 1, 1, 1, 'Installing PHP, MySQL & Your Editor', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Set up a local development environment step by step.', 14, 1, 2),
(3, 1, 1, 1, 'PHP Syntax, Variables & Forms', 'text', NULL, 'Core syntax, superglobals and handling form submissions safely.', 20, 0, 3),
(4, 1, 1, 2, 'Designing a Normalized Database', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Tables, keys, relationships and indexes explained with a real schema.', 18, 0, 1),
(5, 1, 1, 2, 'PDO Prepared Statements Deep Dive', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Connect with PDO and query safely using prepared statements only.', 22, 0, 2),
(6, 1, 1, 3, 'Authentication: Hashing, Sessions & CSRF', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Build a secure login system with password_hash and CSRF tokens.', 26, 0, 1),
(7, 1, 3, 4, 'What Is UX? Principles That Matter', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'The mindset and principles behind great product experiences.', 12, 1, 1),
(8, 1, 3, 5, 'Figma Essentials: Frames, Auto-Layout, Components', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Hands-on Figma workflow used by professional product designers.', 30, 0, 1);

INSERT INTO resources (tenant_id, course_id, lesson_id, title, file_path, file_type, file_size, uploaded_by) VALUES
(1, 1, 5, 'PDO Cheat Sheet (PDF)', 'uploads/resources/pdo-cheat-sheet.pdf', 'pdf', 245760, 3),
(1, 1, 1, 'Course Slides — Module 1 (ZIP)', 'uploads/resources/module1-slides.zip', 'zip', 1835008, 3),
(1, 3, 8, 'Figma Practice File (FIG)', 'uploads/resources/figma-practice.fig', 'fig', 524288, 4);

INSERT INTO enrollments (tenant_id, student_id, course_id, progress_percent, status, enrolled_at) VALUES
(1, 1, 1, 37.50, 'active', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 1, 3, 12.50, 'active', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 2, 4, 100.00, 'completed', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(1, 2, 1, 62.50, 'active', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(1, 3, 1, 25.00, 'active', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 4, 5, 10.00, 'active', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO lesson_progress (tenant_id, student_id, course_id, lesson_id, status, completed_at) VALUES
(1, 1, 1, 1, 'completed', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 1, 1, 2, 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 1, 1, 3, 'completed', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 2, 1, 1, 'completed', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(1, 2, 1, 2, 'completed', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, 2, 1, 3, 'completed', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(1, 2, 1, 4, 'completed', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 2, 1, 5, 'completed', DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO quizzes (id, tenant_id, course_id, lesson_id, title, description, pass_percent, time_limit_minutes, status, created_by) VALUES
(1, 1, 1, 5, 'PDO & Databases Check', 'Quick check on PDO and prepared statements.', 70, 10, 'published', 3);

INSERT INTO questions (id, tenant_id, quiz_id, question, type, points, sort_order) VALUES
(1, 1, 1, 'Why should you use prepared statements?', 'single', 2, 1),
(2, 1, 1, 'Which PDO attribute throws exceptions on errors?', 'single', 2, 2),
(3, 1, 1, 'password_hash() produces a hash that includes a random salt.', 'true_false', 1, 3);

INSERT INTO answers (question_id, answer_text, is_correct) VALUES
(1, 'They make queries faster to type', 0),
(1, 'They separate SQL from data, preventing SQL injection', 1),
(1, 'They remove the need for a database password', 0),
(2, 'PDO::ATTR_DEFAULT_FETCH_MODE', 0),
(2, 'PDO::ATTR_ERRMODE set to ERRMODE_EXCEPTION', 1),
(2, 'PDO::ATTR_EMULATE_PREPARES', 0),
(3, 'True', 1),
(3, 'False', 0);

INSERT INTO quiz_attempts (tenant_id, quiz_id, student_id, score, total_points, percent, passed, submitted_at) VALUES
(1, 1, 2, 5, 5, 100.00, 1, DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO assignments (id, tenant_id, course_id, created_by, title, instructions, due_date, max_points) VALUES
(1, 1, 1, 3, 'Build a Secure Login Form', 'Create a login page using PDO prepared statements, password_verify(), CSRF protection and login rate limiting. Submit your code summary and a short explanation of the security choices you made.', DATE_ADD(NOW(), INTERVAL 3 DAY), 100),
(2, 1, 3, 4, 'Redesign a Checkout Flow', 'Pick any e-commerce checkout and redesign it in Figma applying the UX principles from Module 1. Submit your Figma notes and rationale.', DATE_ADD(NOW(), INTERVAL 5 DAY), 100);

INSERT INTO submissions (tenant_id, assignment_id, student_id, content, status, grade, feedback, graded_by, submitted_at, graded_at) VALUES
(1, 1, 2, 'Implemented login with PDO, bcrypt verification, CSRF tokens and a 5-attempt lockout. Code attached in the academy repo.', 'graded', 92.00, 'Excellent work — clean prepared statements and correct lockout logic.', 3, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 1, 1, 'Login form built with prepared statements and session regeneration. Rate limiting implemented via a failed_attempts counter.', 'submitted', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 HOUR), NULL);

INSERT INTO certificates (tenant_id, student_id, course_id, certificate_code, issued_at) VALUES
(1, 2, 4, 'LURNI-2026-DM-1002', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO reviews (tenant_id, course_id, student_id, rating, comment, created_at) VALUES
(1, 1, 2, 5, 'The PDO section alone was worth the price. Clear, practical and project-based.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 1, 3, 5, 'Great bootcamp structure — every lesson builds on the last.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 3, 1, 5, 'Sara explains design thinking better than any course I have taken before.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 4, 2, 4, 'Solid marketing course with actionable templates.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 5, 4, 5, 'Finally a data science course that starts from zero and stays practical.', DATE_SUB(NOW(), INTERVAL 12 HOUR));

INSERT INTO support_tickets (tenant_id, user_id, subject, message, priority, status) VALUES
(1, 2, 'How do I raise the storage limit?', 'Our academy is growing and we expect to upload many video resources this term. Can we raise storage before upgrading?', 'medium', 'open');

INSERT INTO notifications (tenant_id, user_id, title, body, link) VALUES
(NULL, 1, 'New academy registered', 'Demo Academy started on the Professional plan.', '/super-admin/tenants.php'),
(1, 2, 'Welcome to Lurni Academy Cloud', 'Your academy workspace is ready. Add instructors, categories and your first course to get started.', '/admin/dashboard.php'),
(1, 5, 'Assignment graded', 'Your Data Science assignment was graded.', '/student/grades.php'),
(NULL, NULL, 'Platform update', 'Question banks and certificate verification are now live for all academies.', '');

INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body) VALUES
(1, 3, 5, 'Welcome to the PHP Bootcamp', 'Hi Ali — welcome aboard! Start with the roadmap lesson and reach out any time you get stuck.');

INSERT INTO activity_logs (tenant_id, user_id, description) VALUES
(1, 2, 'Academy registered: Demo Academy on the Professional plan'),
(1, 3, 'Course published: PHP & MySQL Bootcamp'),
(1, 5, 'Student enrolled in UI/UX Design Fundamentals with Figma');

INSERT INTO contact_messages (name, email, subject, message) VALUES
('Bright Tutors', 'hello@brighttutors.example', 'Demo request', 'We run a tutoring center with 12 teachers and would like a product demo this week.');

-- ---------------------------------------------------------------------
-- SEEDS: UPGRADE ADDITIONS (migrations 001-004 demo data)
-- ---------------------------------------------------------------------
-- Blog: the 6 posts previously hardcoded in blog.php, now in the database
-- (tenant 1, author = academy admin user 2, all published).
INSERT INTO blog_posts (tenant_id, author_id, title, slug, category, excerpt, content, status, published_at, created_at) VALUES
(1, 2, 'How Demo Academy reached 1,000 learners in 90 days', 'how-demo-academy-reached-1000-learners-in-90-days', 'Growth', 'The exact playbook: categories, featured courses and certificate incentives that turned a small academy into a growing online school.', 'Ninety days ago Demo Academy had a handful of courses and a trickle of sign-ups. The team started by organising the catalogue into clear categories, featuring the strongest course on the homepage, and making sure every published course had a proper thumbnail, subtitle and outcome-focused description.

The second lever was trust: every completed course ends with a verifiable certificate, and learners were encouraged to share theirs. Referrals from certificate shares, combined with a simple welcome coupon, turned steady growth into 1,000 enrolled learners before the end of the quarter.', 'published', '2026-11-02 09:00:00', '2026-11-02 09:00:00'),
(1, 2, 'Designing quizzes students actually finish', 'designing-quizzes-students-actually-finish', 'Teaching', 'Question banks, pass marks and instant feedback that keep completion rates high without dumbing anything down.', 'Most abandoned quizzes fail for the same reasons: they are too long, the pass mark feels arbitrary, and students get no feedback until long after they have moved on. Short, focused checks at the end of a lesson — five to ten questions that test exactly what was just taught — complete at far higher rates.

Set a pass mark learners can reason about, show instant feedback after each attempt, and allow a retake once the student has reviewed the lesson. Rigor stays; frustration goes.', 'published', '2026-10-26 09:00:00', '2026-10-26 09:00:00'),
(1, 2, 'From classroom to cloud: a training center migration story', 'from-classroom-to-cloud-a-training-center-migration-story', 'Stories', 'How a 12-teacher center moved its entire catalog online in two weeks — and what they would do differently.', 'When a 12-teacher training center decided to move online, the catalogue was the easy part: courses, modules and lessons mapped neatly onto the new structure. The harder work was people — getting every teacher to record, upload and re-check their material in a consistent format.

Two weeks later the full catalog was live. Their advice to other centers: migrate one department at a time, keep the old timetable running in parallel for the first cohort, and spend more time than you expect on student onboarding emails.', 'published', '2026-10-18 09:00:00', '2026-10-18 09:00:00'),
(1, 2, 'Certificates that employers actually trust', 'certificates-that-employers-actually-trust', 'Careers', 'Unique verification codes, real assessment and transparent criteria: the three ingredients of a credible certificate.', 'A certificate is only worth something if an employer can check it in seconds. That means a unique verification code on every certificate, a public verification page, and criteria that are stated plainly: which course, which assessments, and what score was required to pass.

Academies that publish their criteria and keep assessment records behind every certificate find their graduates get callbacks more often — because the credential answers the employer''s first question before it is even asked.', 'published', '2026-10-09 09:00:00', '2026-10-09 09:00:00'),
(1, 2, 'The academy metrics dashboard: 7 numbers that matter', 'the-academy-metrics-dashboard-7-numbers-that-matter', 'Analytics', 'MRR is for platforms; academies live on enrollment velocity, completion rate and review scores. Here is how to read them.', 'It is easy to drown in analytics. For an academy, seven numbers do most of the work: new enrollments per week, activation (first lesson started), lesson completion rate, quiz pass rate, course completion rate, average review score, and repeat enrollments.

Read them together, not in isolation. A course with high enrollments and low completion is a marketing success and a teaching problem; a course with modest enrollments, high completion and five-star reviews is the one to feature next.', 'published', '2026-09-30 09:00:00', '2026-09-30 09:00:00'),
(1, 2, 'Pricing your first online course', 'pricing-your-first-online-course', 'Business', 'Anchor pricing, trials and coupons — a pragmatic guide for instructors publishing their first paid course.', 'New instructors usually price too low, fearing nobody will pay. A better approach is anchor pricing: set the honest full price, show a launch price beneath it, and let early students feel — and be — genuinely rewarded for trusting you first.

Support the launch with a time-limited coupon rather than a permanent discount, and watch completion, not just sales. A course bought cheaply and never finished helps nobody; a fairly priced course that learners complete becomes your best advertisement.', 'published', '2026-09-21 09:00:00', '2026-09-21 09:00:00');

-- Course announcement for course 1 (author = its instructor, user 3,
-- via instructors id 1)
INSERT INTO course_announcements (tenant_id, course_id, author_id, title, body, created_at) VALUES
(1, 1, 3, 'Welcome to the PHP & MySQL Bootcamp — new PDO resources added', 'Welcome aboard! I have just added a PDO cheat sheet to the resources for the ''PDO Prepared Statements Deep Dive'' lesson. Work through Module 1 this week and bring your questions to the discussion thread.', DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Discussion on course 1: question by the demo student (user 5 / Ali Hassan,
-- students id 1) on lesson 5, answered by the instructor (user 3)
INSERT INTO discussions (id, tenant_id, course_id, lesson_id, user_id, parent_id, body, status, created_at) VALUES
(1, 1, 1, 5, 5, NULL, 'In the PDO lesson, should I use emulated prepares or real prepares for a production app? My local setup seems to emulate by default.', 'published', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 1, 1, 5, 3, 1, 'Great question — always use real prepares in production: set PDO::ATTR_EMULATE_PREPARES to false and PDO::ATTR_ERRMODE to ERRMODE_EXCEPTION, exactly as shown in the lesson. Emulation can silently fall back to quoting in edge cases.', 'published', DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Demo student (students id 1) bookmark + private note
INSERT INTO lesson_bookmarks (tenant_id, student_id, course_id, lesson_id, created_at) VALUES
(1, 1, 1, 4, DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO lesson_notes (tenant_id, student_id, course_id, lesson_id, note, created_at) VALUES
(1, 1, 1, 5, 'Remember: always set PDO::ATTR_ERRMODE to ERRMODE_EXCEPTION and use prepared statements for every query with user input — no string concatenation.', DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Per-question answers for the seeded quiz attempt (attempt id 1, quiz 1,
-- students id 2): all three answers correct, matching its stored score
-- of 5 / 5 points (questions are worth 2 + 2 + 1). Selected answer ids
-- refer to the answers table rows inserted above (2, 5 and 7 are the
-- is_correct rows for questions 1, 2 and 3 respectively).
INSERT INTO quiz_attempt_answers (attempt_id, question_id, selected_answer_ids, is_correct, points_awarded, created_at) VALUES
(1, 1, '2', 1, 2.00, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1, 2, '5', 1, 2.00, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(1, 3, '7', 1, 1.00, DATE_SUB(NOW(), INTERVAL 7 DAY));

-- One verified COURSE payment: demo student user 5 (Ali Hassan) bought
-- course 1 (PHP & MySQL Bootcamp, price 49.00 — matches his active
-- enrollment); verified by the tenant admin (user 2)
INSERT INTO payments (id, tenant_id, subscription_id, user_id, kind, course_id, amount, method, reference, status, verified_by, verified_at) VALUES
(2, 1, NULL, 5, 'course', 1, 49.00, 'manual', 'BANK-TRX-1002', 'completed', 2, DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO invoices (tenant_id, subscription_id, payment_id, invoice_number, description, amount, status, issued_at, due_at, paid_at) VALUES
(1, NULL, 2, 'INV-2026-0002', 'Course purchase: PHP & MySQL Bootcamp: Build Real Web Apps', 49.00, 'paid', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY));

UPDATE payments SET invoice_id = (SELECT id FROM invoices WHERE invoice_number = 'INV-2026-0002') WHERE id = 2;

-- Upgrade tweak: the demo assignment (id 1) allows resubmission
UPDATE assignments SET allow_resubmission = 1 WHERE id = 1;
