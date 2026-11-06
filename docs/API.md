# API Notes — Lurni Academy Cloud

Lurni is a classic server-rendered PHP application. The small endpoint set
under `api/` covers the actions the student portal performs without a full
page form. All endpoints require an authenticated session cookie and a valid
CSRF token (form field `_csrf`); there is no public unauthenticated API.

## POST /api/enroll.php
Enroll the signed-in **student** in a published course of their tenant.
- Form fields: `course_id` (int), `_csrf`.
- Behaviour: idempotent — re-enrolling an already-enrolled student simply
  reactivates a cancelled enrollment. Redirects to
  `/student/course.php?id=<course_id>` with a flash message.

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
- Form fields: `quiz_id` (int), `_csrf`, `answers[<question_id>] = <answer_id>`.
- Behaviour: scores the attempt against the stored `answers.is_correct`
  flags (correct answers are never sent to the browser), records a row in
  `quiz_attempts` with score/total/percent/passed, and redirects to
  `/student/take-quiz.php?id=<quiz_id>&done=1` which renders the result.

## GET /api/search.php?q=<term>
Course search suggestions for the dashboard topbar.
- Requires any signed-in user with a tenant. `q` must be ≥ 2 characters.
- Returns JSON: `{"results": [{"id": 3, "title": "...", "level": "beginner",
  "rating": 4.8, "url": "/student/browse.php?q=..."}]}` — published courses
  of the caller's tenant only, max 8.

## Conventions
- All endpoints enforce role (`require_role`) and tenant activity
  (`check_tenant_active`) before touching data.
- Errors are reported via flash messages (form posts) or `{"ok": false}`
  (JSON) — never with stack traces when `APP_DEBUG=false`.
