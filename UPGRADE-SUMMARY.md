# Lurni Academy Cloud — Upgrade Summary

Upgrade of the existing LMS SaaS (no rebuild, no redesign). The blue
`#2563EB` + purple `#7C3AED` + white branding, logo, fonts, course cards,
illustrations, dashboard styling, sidebars and responsive layouts are
unchanged — all work below extends the existing code, schema and design
system. No git, GitHub or Vercel commands were run; files only.

## 1. Dashboard trees (role-separated, permission-enforced, tenant-isolated)

### super-admin/
- `dashboard.php` — NEW canonical path for the platform dashboard (the
  previous `index.php` content); `index.php` is now a thin wrapper that
  requires it, so old links/bookmarks keep working.
- `institutions.php` — NEW canonical tenant-management page (full CRUD:
  search, status filter, suspend/reactivate/cancel, CSV export, plan and
  usage columns); `tenants.php` now redirects here.
- `users.php`, `plans.php`, `subscriptions.php`, `payments.php`,
  `invoices.php`, `coupons.php`, `tickets.php`, `announcements.php`,
  `logs.php`, `settings.php` — existing pages retained.
- `courses.php` — NEW: platform-wide course oversight across all
  institutions with institution + approval filters, live search, and
  approve/reject actions on `courses.approval_status`.
- `reports.php` — NEW: platform analytics (API-driven stat chips + Chart.js
  revenue / enrollments / new-institutions / plan-mix charts with a real
  date-range filter), top-institutions table, revenue + institutions CSV
  exports. Platform refunds on `payments.php` now also write a
  `payment_refunds` record.

### admin/ (tenant)
All required pages exist and are wired to real logic: `dashboard.php`
(now with a live Analytics section — date-range filter, API stat chips,
revenue / student-growth / completion charts, instructor-performance
table), `students.php`, `instructors.php`, `courses.php` (full builder —
see §3 — plus approval queue with approve/reject + approval filter),
`categories.php`, `enrollments.php`, `quizzes.php` (max-attempts setting),
`assignments.php` (resubmission rule), `certificates.php`, `payments.php`
(course-payment verification + refund recording — see §3), `reviews.php`,
`reports.php` (CSV exports), `settings.php`, plus modules, lessons,
resources, questions, grading, progress, notifications, subscription.
- `blog.php` — NEW: blog management (CRUD, draft/publish toggle, cover
  upload, live search) writing to `blog_posts`.

### instructor/
`dashboard.php` (now with a "My teaching analytics" section: own-course
stat chips + enrollment / learners-per-course charts from the API),
`courses.php` (builder fields + approval badge; new courses are created
with `approval_status='pending'`), `lessons.php` (modules/lessons with
ordering **plus course-material uploads** scoped to own courses),
`quizzes.php` (question types single / multiple-answer / true-false,
max attempts, recent attempts with **instructor feedback** form),
`assignments.php` (allow-resubmission rule), `grading.php`,
`performance.php` (now with a **course revenue** table — verified course
payments per own course), `messages.php`. Every instructor query is
scoped by `courses.instructor_id = <own id>` in SQL.

### student/
`dashboard.php` (now with weekly-activity and per-course-progress charts
from the API), `browse.php` (paid courses get a Buy modal with coupon
field; pending purchases show "Awaiting verification"), `my-learning.php`,
`course.php` — the upgraded lesson player (see §3), `quizzes.php`,
`take-quiz.php` (checkboxes for multiple-answer questions, JS countdown
timer with auto-submit, attempts-remaining, instructor feedback display),
`assignments.php` (resubmission as new attempts with attempt numbers),
`grades.php`, `progress.php`, `certificates.php`, `certificate.php`
(printable, now prints the public verification URL), `messages.php`.
- `payments.php` — NEW: own payment history (course purchases +
  subscriptions, invoice numbers, refund totals, verification status).
- `calendar.php` — NEW: month-grid learning calendar built from real
  records (assignment deadlines, submissions, quiz attempts, completed
  lessons, certificates).

Public: `verify-certificate.php` — NEW public verification page
(code lookup → student, course, institution branding/logo, issued date,
valid/revoked state). `blog.php` / `blog-post.php` now render published
posts from `blog_posts` (the original static articles remain as fallback).
Public listings (`index.php`, `courses.php`, `course.php`,
`student/browse.php`, `api/search.php`, `api/enroll.php`) now require
`approval_status='approved'` and respect `visibility`
(public / unlisted / private).

## 2. JSON API endpoints (`api/`)

| Endpoint | Purpose |
|---|---|
| `GET analytics.php?from&to` | Role-aware real aggregates + Chart.js series with date filtering (admin: tenant stats incl. active students, published/draft courses, completion rate, verified revenue, pending payments, certificates, popular courses, instructor performance; instructor: own courses only; super_admin: platform incl. MRR + top tenants; student: own activity + weekly series) |
| `POST purchase.php` | Paid-course purchase → pending payment + unpaid invoice + pending enrollment (+ coupon validation/redemption); never marks anything paid |
| `POST bookmarks.php` / `GET ?course_id` | Toggle / list lesson bookmarks (enrollment-gated) |
| `GET/POST notes.php` | Per-lesson private notes (enrollment-gated) |
| `GET/POST discussion.php` | Course Q&A threads + replies (enrollment / course-ownership gated; instructor notified on new questions) |
| `POST quiz-submit.php` | Upgraded: multiple-answer grading with proportional credit, per-question `quiz_attempt_answers` rows, `max_attempts` enforcement, `started_at` |
| `POST enroll.php` | Upgraded: approval/visibility checks; paid courses route to purchase |
| `POST progress.php`, `GET search.php` | Existing, unchanged behaviour (progress already returned JSON + auto-certificates) |

Shared helpers added in `includes/functions.php`: `current_student_id()`,
`current_instructor()`, `queue_email()` / `queue_email_to_user()` (insert
into `email_queue` for async delivery), `apply_coupon()` /
`record_coupon_redemption()`. A CSRF `<meta>` tag was added to both
dashboard headers for the JS layer.

## 3. Functional completions

- **Course builder**: objectives, requirements, duration, visibility and
  approval status added to both admin and instructor course forms
  (thumbnail upload/presets, category, instructor, pricing, free/paid,
  difficulty, publishing already existed); objectives/requirements render
  on the public course page.
- **Student learning page**: video/embed player, previous/next lesson
  navigation, **Complete & next** (AJAX progress update in place),
  bookmarks, notes panel, resources, quizzes, course announcements
  section, discussion/Q&A with replies.
- **Quiz engine**: MCQ, true/false, multiple-answer; time limits (JS
  countdown + auto-submit); passing marks; server-side auto-scoring;
  attempt limits; result history; instructor feedback on attempts.
- **Assignments**: creation, file submission, deadlines/late flag,
  grading + feedback, **resubmission rules** (`allow_resubmission` +
  `attempt_no`; graded work resubmits only as a new attempt when allowed).
- **Certificates**: unique code, auto-issue at 100%, institution branding,
  **public verification page**.
- **Commerce**: course purchases with coupons, enrollment records,
  payment history + invoices for students, refund recording
  (`payment_refunds`), and the verified-confirmation rule throughout —
  payments become `completed` only when an admin verifies them
  (`admin/payments.php`), which is also what activates the enrollment.
- **Platform SaaS**: institution management/approvals, plans, trials,
  monthly/yearly billing, limits, expiry, platform revenue analytics,
  tickets, settings (existing) + new platform course approvals + reports.
- **Email architecture**: `email_queue` table + queue helpers; events
  (purchase recorded, payment verified, new discussion question) queue
  mail instead of claiming to send it.

## 4. JavaScript layer (`assets/js/app.js`, appended `Lurni` namespace)

Toast notification system; CSRF-aware fetch helpers; AJAX form handling
with modal auto-close (`form.js-ajax-form`); live table search +
column filters (`data-live-search`, `data-live-filter-for`); quiz
countdown timer with auto-submit; lesson-player controls (complete &
advance with in-place progress bar + curriculum update, bookmark toggle,
notes autosave-load/save, discussion posting without reload); Chart.js
analytics loader with date-range inputs (`data-series`, `data-stat`,
`data-analytics-from/to/refresh`); instructor question-type toggle.
No dummy buttons: every control calls a real endpoint or filter.

## 5. Database

Numbered, non-destructive migrations in `database/migrations/`, all
folded into `database.sql` (now **47 tables**, 1,021 lines); demo seeds
and logins unchanged and extended (blog posts, announcement, discussion
thread, bookmark + note, quiz-attempt detail rows, one verified course
payment + paid invoice, demo assignment allows resubmission):

- `001_fix_schema_drift.sql` — columns the PHP already referenced:
  `certificates.title/revoked_at/grade`, `enrollments.source/
  last_accessed_at` + `pending` status, `quiz_attempts.answers_json/
  feedback/feedback_by`.
- `002_course_and_assessment_fields.sql` — course builder fields +
  approval/visibility, `quizzes.max_attempts`,
  `assignments.allow_resubmission`, submissions multi-attempt
  (`attempt_no`, unique key → plain index), `payments.kind/course_id`.
- `003_learning_engagement.sql` — `lesson_bookmarks`, `lesson_notes`,
  `course_announcements`, `discussions`.
- `004_commerce_content_notifications.sql` — `quiz_attempt_answers`,
  `payment_refunds`, `blog_posts`, `email_queue`.

## 6. Vercel demo (`vercel-demo/`)

Design preserved; `app.js` extended so the static showcase mirrors the
upgrade: admin live search + status/category filters and date-range
re-rendering enrollment/revenue charts; student lesson completion with
progress updates, quiz mock with countdown timer and scoring, bookmark
toggles persisted to localStorage; instructor course search/filter and a
working create-course modal (appends a card + toast); Chart.js CDN charts
on all three dashboards; shared toast system. `node --check` passes.
Still a static showcase — no real auth or database (Vercel cannot run
PHP/MySQL).

## 7. Tests performed

- `php -l` (PHP 8.3.13) on **all 104 PHP files: 0 failures**.
- `node --check` on `assets/js/app.js` and `vercel-demo/app.js`: pass.
- Schema cross-checks: every new table/column referenced by the new PHP
  verified present in the folded `database.sql`; migration contents match
  the fold-in; seed references (course/student/attempt ids) verified
  consistent by inspection. A final programmatic pass parsed all 47
  `CREATE TABLE` definitions and validated every `INSERT`/`UPDATE`
  column list in the 16 new/changed PHP files against them — this caught
  and fixed one real mismatch (`tenant_id` added to
  `quiz_attempt_answers` and `payment_refunds` in both migration 004 and
  `database.sql`, matching the PHP inserts and the platform's
  tenant-isolation rule).
- Static review of every new endpoint for role checks, tenant scoping,
  CSRF (`verify_csrf`) and enrollment/ownership gates.

## 8. Known limitations

- **No live MySQL in this environment** (no server binary installed), so
  the app could not be smoke-tested end-to-end against a running
  database. All PHP is lint-clean and all SQL was cross-checked against
  the schema, but the first real deployment should run the standard
  install (`install.php`) and click through the four portals once.
- Email is an **architecture**: messages are queued in `email_queue`; a
  cron/worker with SMTP credentials must be configured to deliver them
  (status flips to `sent`/`failed` there). Nothing in the UI claims mail
  was sent.
- Course payments are recorded against the academy's manual/offline
  payment process (method `manual`) and verified by an admin — there is
  no payment-gateway charge in this codebase, by design (the original
  build used the same verified-manual model for subscriptions).
- The demo assignment/quiz seeds keep their original ids; the new seed
  rows reference them as documented in comments inside `database.sql`.
