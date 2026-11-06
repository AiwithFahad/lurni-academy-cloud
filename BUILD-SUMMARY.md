# Build Summary — Lurni Academy Cloud (LMS SaaS)

Built: Friday, 6 November 2026 · Location: `~/workspace/saas-products-batch2/lurni-academy-cloud/`

## Totals
- **Total files:** 126 (including this summary)
- **PHP files:** 91 (≈6,600 lines) — **lint: 91/91 pass `php -l`, 0 failures** (PHP 8.5.8)
- **database.sql:** 39 tables, 802 lines, with FKs, unique constraints, indexes, `tenant_id` isolation and full demo seeds
- **vercel-demo/:** 7 files, 764 lines (static HTML/CSS/JS showcase)
- No git, GitHub or Vercel commands were run — files only.

## Brand palette (from reference image-8.png)
- Primary blue `#2563EB`, accent purple `#7C3AED`, white surfaces, soft blue→violet gradients, rounded course cards.

## What was built
- **Public website (14 pages):** Home (hero + course search, trusted-universities strip, categories, featured courses, stats, How It Works, instructors, benefits, testimonials, success stories, dashboard preview, pricing, blog, FAQ, newsletter, contact), Courses, Course Details, Categories, Instructors, Instructor Details, About, Pricing (monthly/yearly toggle), Blog + post, Contact, Privacy, Terms.
- **Auth (7 pages):** login (rate limiting: 5 fails / 15-min lockout), logout, organization registration with onboarding (tenant + admin + subscription + invoice), forgot/reset password (hashed 1-hour tokens), email verification, invitation activation. Bcrypt hashing, CSRF on every form, secure sessions, RBAC via roles/permissions.
- **Super Admin (13 pages):** live-computed dashboard (tenants, active/suspended, users, instructors, subscriptions, revenue, **MRR/ARR**, transactions, Chart.js analytics), tenants (suspend/reactivate/cancel, CSV), plans CRUD, coupons CRUD, subscriptions, **payment verification flow** (pending → completed only on verify; extends subscription, applies plan switches, counts coupon redemptions — revenue counts verified payments only), invoices, users, support tickets, announcements (broadcast), platform settings, activity/audit logs.
- **Academy Admin (21 pages):** dashboard + charts, students, instructors (invitations), categories, courses, modules, lessons, resources (storage-limit-aware), quizzes, question bank, assignments, grading, enrollments, progress, certificates (issue at 100%, verify-by-code, revoke), reviews moderation, payments + printable invoices, subscription management (upgrade/downgrade/cancel/renew, coupons, usage bars), notifications + support tickets, reports + CSV exports, website settings/branding.
- **Instructor portal (8 pages):** dashboard, own-course CRUD (ownership enforced in SQL), lessons/modules, quizzes + questions, assignments, grading (own courses only), performance analytics, messages.
- **Student portal (11 pages):** dashboard, course browse/search, my learning (+ reviews), lesson player with progress tracking, quizzes list, quiz taking with server-side grading, assignments with file-upload submission, progress, grades, certificates list + printable certificate page, messages.
- **API (4 endpoints):** enroll, progress (JSON + form; auto-issues certificate at 100%), quiz-submit (server-side scoring), search (tenant-scoped JSON).
- **Billing rules enforced:** Free/Starter/Professional/Business/Enterprise plans, monthly+yearly, trials, coupons (WELCOME10, LAUNCH25), automatic expiry + grace period, plan limits for students/instructors/courses/storage enforced server-side, subscription usage tracking.
- **Docs:** README.md (features, install, DB setup, demo credentials, deployment), docs/SECURITY.md, docs/API.md, docs/DEPLOYMENT.md, .env.example (placeholders only), install.php one-time installer.

## Demo credentials (all password: `password`, bcrypt-hashed in seeds)
| Role | Email | Portal |
|---|---|---|
| Super Admin | super@lurni.test | /super-admin/ |
| Academy Admin | admin@demo.com | /admin/ |
| Instructor | instructor@demo.com | /instructor/ |
| Student | student@demo.com | /student/ |

Coupons: `WELCOME10` (10% off), `LAUNCH25` (25% off).

Seed data: Demo Academy tenant on Professional plan, 8 users, 5 categories, 6 published courses with modules/lessons/resources, enrollments with progress, quiz + attempt, assignments + graded submission, 1 certificate, 5 reviews, support ticket, notifications, messages, 1 verified payment + paid invoice.

## Deployment note
The PHP application requires PHP/MySQL hosting (see docs/DEPLOYMENT.md). The `vercel-demo/` folder is the static showcase intended for Vercel — it is not the PHP app.
