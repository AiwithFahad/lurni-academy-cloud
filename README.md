# Lurni Academy Cloud — Learning Management System (LMS) SaaS

A complete, multi-tenant LMS SaaS platform for academies, schools, training
centers and online course businesses. Built with **Core PHP 8 + MySQL (PDO)**
— no frameworks — with Bootstrap 5 + Chart.js on the front end.

Brand: blue `#2563EB` + purple `#7C3AED` + white, soft gradients, course cards.

## Features

**Public website** — hero with course search, trusted-universities strip,
course categories, featured courses, platform statistics, How It Works,
top instructors, learning benefits, testimonials, success stories, dashboard
preview, subscription pricing (monthly/yearly), blog, FAQ, newsletter and
contact forms.

**Super Admin** — platform dashboard with real computed stats (tenants,
active/suspended, users, instructors, active/expired subscriptions, revenue,
MRR, ARR, transactions), subscription plan CRUD, coupon management, tenant
management (suspend/reactivate/cancel), user management, payment
verification, invoices, support tickets, announcements (broadcast), platform
settings and activity/audit logs. Revenue chart + plan analytics.

**Academy Admin** — analytics dashboard with charts, Students, Instructors
(invitation links), Categories, Courses, Modules, Lessons, Resources
(storage-limit aware uploads), Quizzes & Question Bank, Assignments,
Grading, Enrollments, Student Progress, Certificates (issue at 100%, verify
by code, revoke), Reviews & Ratings moderation, Payments & printable
invoices, Subscription management (upgrade/downgrade/cancel/renew, coupons,
usage bars), Notifications + support tickets, Reports with CSV export, and
Website Settings (branding, logo, contact info).

**Instructor portal** — own-courses CRUD with thumbnails, modules & lessons,
material uploads, quizzes + questions (single / multiple-answer /
true-false, attempt limits), assignments with resubmission rules, submission
grading with feedback, quiz-attempt feedback, course performance + verified
course-revenue analytics, messaging students and admins. New instructor
courses are submitted for academy approval before they list publicly.

**Student portal** — course catalog with search/filter, free enrollment and
paid-course purchases (coupon support; enrollment activates only after the
academy verifies the payment), lesson player (video/text) with previous /
next navigation, complete-and-advance, bookmarks and per-lesson notes,
course announcements and discussion / Q&A, progress tracking, downloadable
resources, timed quizzes with a JavaScript countdown and instant
server-side grading, assignment submission with file upload and
resubmission, a learning calendar, payment history with invoices, progress
dashboard, grades, and printable certificates that are issued automatically
at 100% course completion — each with a unique code verifiable publicly at
`/verify-certificate.php`.

**Analytics API** — `GET /api/analytics.php?from=…&to=…` returns real,
role-scoped aggregates (students, instructors, courses, enrollments,
completion rate, verified revenue, pending payments, certificates) and
Chart.js series (monthly enrollments, revenue, student growth, completion,
popular categories, instructor performance) with real date filtering.
Dashboards in all four portals are wired to it. See `docs/API.md`.

**Blog** — posts are managed in the Academy Admin (`/admin/blog.php`) and
rendered from the `blog_posts` table on the public site.

**Email architecture** — notifications that need email are inserted into
the `email_queue` table by `queue_email()` helpers for asynchronous
delivery by a mail worker; nothing is marked sent from the request path.

**SaaS billing** — Free / Starter / Professional / Business / Enterprise
plans, monthly + yearly cycles, free trials, coupons (percent/fixed,
expiry, usage caps), invoices, manual payment recording with Super Admin
verification (a payment is never marked successful without verified
confirmation), automatic expiry with a grace period, and hard plan limits
for students, instructors, courses and storage — all enforced server-side.

**Security** — bcrypt password hashing, CSRF tokens on every form, secure
session cookies, login rate limiting (5 attempts / 15 min), RBAC
(roles/permissions), tenant isolation on every table and query, PDO prepared
statements everywhere, upload allow-lists. See `docs/SECURITY.md`.

## Install

Requirements: PHP 8.1+ (`pdo_mysql`, `mbstring`, `fileinfo`), MySQL 8 /
MariaDB 10.4+.

```bash
# 1. Configure
cp .env.example .env   # set DB_HOST, DB_NAME, DB_USER, DB_PASS, APP_URL

# 2. Create the database and import schema + demo seeds
#    Option A (browser): open https://your-domain/install.php once, then delete it
#    Option B (CLI):     php install.php
#    Option C (manual):  mysql -u root -p -e "CREATE DATABASE lurni_academy;"
#                        mysql -u root -p lurni_academy < database.sql
#
#    Existing installs from the original 39-table schema upgrade in place:
#    apply the numbered, non-destructive migrations in order —
#      mysql -u root -p lurni_academy < database/migrations/001_fix_schema_drift.sql
#      mysql -u root -p lurni_academy < database/migrations/002_course_and_assessment_fields.sql
#      mysql -u root -p lurni_academy < database/migrations/003_learning_engagement.sql
#      mysql -u root -p lurni_academy < database/migrations/004_commerce_content_notifications.sql
#    (fresh installs do not need them — database.sql is already the final schema.)

# 3. Make uploads/ and storage/ writable by the web server
chmod -R 775 uploads storage
```

## Demo credentials

All demo accounts use the password **`password`**:

| Role | Email | URL |
|---|---|---|
| Super Admin | `super@lurni.test` | `/super-admin/` |
| Academy Admin | `admin@demo.com` | `/admin/` |
| Instructor | `instructor@demo.com` | `/instructor/` |
| Student | `student@demo.com` | `/student/` |

Coupon codes: `WELCOME10` (10% off), `LAUNCH25` (25% off).

The seed data includes the "Demo Academy" tenant on the Professional plan,
6 published courses with modules, lessons, resources, a quiz with questions,
assignments, enrollments with progress, a graded submission, a certificate
and reviews.

## Project structure

```
index.php, courses.php, course.php, ...   Public marketing website
auth/                                     Login, register (tenant onboarding), reset, verify
super-admin/                              Platform owner console
admin/                                    Academy (tenant) admin console
instructor/                               Instructor portal
student/                                Student portal
api/                                      enroll, purchase, progress, quiz-submit,
                                          search, analytics, bookmarks, notes,
                                          discussion endpoints
includes/                               auth, functions, layouts, messenger
config/                                 config.php (.env loader), database.php (PDO)
assets/                                 CSS, JS, SVG imagery
uploads/, storage/                      User uploads, logs, backups
docs/                                   SECURITY.md, API.md, DEPLOYMENT.md
vercel-demo/                            Static showcase demo for Vercel
database.sql                            Full schema (47 tables) + demo seeds
database/migrations/                    Numbered upgrades for existing installs
verify-certificate.php                  Public certificate verification page
```

## Deployment

- **Production app:** any PHP/MySQL host (VPS, cPanel, Forge/Ploi, Docker).
  See `docs/DEPLOYMENT.md`.
- **Live demo:** the `vercel-demo/` folder is a static replica (landing page
  + dashboards with mock data) that deploys to Vercel as-is.

> Note: Vercel cannot run PHP/MySQL, so the Vercel deployment is the static
> showcase only — the full commercial application runs on PHP/MySQL hosting.

## License

Commercial product — all rights reserved by the author.
