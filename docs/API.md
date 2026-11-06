# API Notes — Lurni Academy Cloud

Lurni is a classic server-rendered PHP application. The small endpoint set
under `api/` covers the actions the portals perform without a full page
form. All endpoints require an authenticated session cookie and a valid
CSRF token (form field `_csrf`); there is no public unauthenticated API.

## POST /api/enroll.php
Enroll the signed-in **student** in a published, approved course of their
tenant. Free courses only — paid courses are routed to the purchase flow.
- Form fields: `course_id` (int), `_csrf`.
- Behaviour: idempotent — re-enrolling an already-enrolled student simply
  reactivates a cancelled enrollment. Redirects to
  `/student/course.php?id=<course_id>` with a flash message.

## POST /api/purchase.php
Start a paid-course purchase for the signed-in student.
- Form fields: `course_id` (int), optional `coupon_code`, `_csrf`.
- Behaviour: validates the coupon (`coupons` table), then creates a
  **pending** payment (`kind='course'`), an **unpaid** invoice and a
  `pending` enrollment. Nothing is marked paid here: a tenant admin must
  verify the payment in `/admin/payments.php`, which flips the payment to
  `completed`, the invoice to `paid` and the enrollment to `active`.
  Redirects to `/student/payments.php`.

## POST /api/progress.php
Mark a lesson complete for the signed-in student.
- Form fields: `lesson_id` (int), `_csrf`, optional `redirect` (local path).
- Behaviour: upserts `lesson_progress`, recomputes the enrollment's
  `progress_percent`, marks the enrollment `completed` at 100%, and
  auto-issues a certificate (once per course) + notification.
- Response: if the request sends `Accept: application/json` or
  `X-Requested-With: XMLHttpRequest`, returns JSON:
  `{"ok": true, "message": "...", "progress_percent": 62.5, "certificate_issued": false}`.
  Otherwise redirects back with a flash message.

## POST /api/quiz-submit.php
Grade a quiz attempt server-side.
- Form fields: `quiz_id` (int), `_csrf`, `started_at` (optional),
  `answers[<question_id>] = <answer_id>` for single-choice / true-false,
  `answers[<question_id>][] = <answer_id>` for multiple-answer questions.
- Behaviour: scores the attempt against the stored `answers.is_correct`
  flags (correct answers are never sent to the browser). Multiple-answer
  questions earn proportional credit (correct picks minus wrong picks,
  floored at 0) and count as fully correct only for the exact set. Records
  a row in `quiz_attempts` (score/total/percent/passed) **plus one
  `quiz_attempt_answers` detail row per question**, enforces the quiz's
  `max_attempts` limit when set, and redirects to
  `/student/take-quiz.php?id=<quiz_id>&done=1` which renders the result.

## GET /api/analytics.php?from=Y-m-d&to=Y-m-d
Role-aware JSON aggregates for the Chart.js dashboards. Every figure is
computed live from the caller's tenant (or their own records); the date
range filters all series and the "in range" counters.
- **admin**: total/active students, instructors, published + draft
  courses, pending approvals, enrollments (total + in range), completion
  rate, verified revenue (range + total), pending payments, certificates.
  Series: monthly enrollments, monthly revenue, cumulative student
  growth, completion % per course, enrollments per category; plus
  `instructor_performance`, `popular_courses`, `recent_enrollments`.
- **instructor**: own courses only — learners, average progress, verified
  course revenue, pending grading, quiz attempts; monthly enrollment and
  revenue series for their courses; learners per course.
- **super_admin**: platform totals (institutions, users, students,
  courses, subscriptions, MRR estimate), platform revenue, pending
  approvals; monthly revenue/enrollment/institution series, plan mix,
  `top_tenants`.
- **student**: own enrolled/completed courses, lessons completed,
  certificates, quiz average, assignments due; weekly activity series
  (lessons completed per week, last 8 weeks) and per-course progress.

## POST|GET /api/bookmarks.php
Lesson bookmarks for the signed-in student (enrollment required).
- POST form fields: `lesson_id`, `_csrf` → toggles; JSON
  `{"ok": true, "bookmarked": true|false, "message": "..."}`.
- GET `?course_id=<id>` → `{"ok": true, "bookmarks": [<lesson_id>, ...]}`.

## GET|POST /api/notes.php
Private per-lesson notes for the signed-in student (enrollment required).
- GET `?lesson_id=<id>` → `{"ok": true, "note": "...", "updated_at": ...}`.
- POST form fields: `lesson_id`, `note` (≤ 5000 chars), `_csrf` → upserts
  into `lesson_notes`; an empty note deletes the row.

## GET|POST /api/discussion.php
Course discussion / Q&A. Students must be enrolled; instructors must own
the course; tenant admins may moderate any course in their tenant.
- GET `?course_id=<id>[&lesson_id=<id>]` → published threads with nested
  `replies`.
- POST form fields: `course_id`, `body`, optional `lesson_id`,
  `parent_id` (reply to a top-level post), `_csrf`. A new top-level post
  notifies the course instructor (in-app + queued email).

## GET /api/search.php?q=<term>
Course search suggestions for the dashboard topbar.
- Requires any signed-in user with a tenant. `q` must be ≥ 2 characters.
- Returns JSON: `{"results": [{"id": 3, "title": "...", "level": "beginner",
  "rating": 4.8, "url": "/student/browse.php?q=..."}]}` — published,
  approved courses of the caller's tenant only, max 8.

## Email notifications
`queue_email()` / `queue_email_to_user()` (in `includes/functions.php`)
insert into the `email_queue` table with status `queued`. A mail worker /
cron sender delivers them and flips rows to `sent`/`failed` — request
paths never send mail directly and never claim delivery.

## Conventions
- All endpoints enforce role (`require_role`) and tenant activity
  (`check_tenant_active`) before touching data.
- Errors are reported via flash messages (form posts) or `{"ok": false}`
  (JSON) — never with stack traces when `APP_DEBUG=false`.
