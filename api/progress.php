<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// POST /api/progress.php — mark a lesson complete for the signed-in student,
// recompute enrollment progress, auto-issue a certificate at 100%.
// Supports JSON (fetch) and plain form posts (redirect back).
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();

$wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$respond = function (bool $ok, string $message, array $extra = []) use ($wantsJson) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message] + $extra);
        exit;
    }
    flash($ok ? 'success' : 'error', $message);
    $back = (string)($_POST['redirect'] ?? '/student/my-learning.php');
    if (!str_starts_with($back, '/')) $back = '/student/my-learning.php';
    redirect($back);
};

verify_csrf();
$lessonId = (int)($_POST['lesson_id'] ?? 0);
$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$s = $pdo->prepare('SELECT id, course_id, title FROM lessons WHERE id=? AND tenant_id=?');
$s->execute([$lessonId, $tid]); $lesson = $s->fetch() ?: null;
if (!$sid || !$lesson) $respond(false, 'Lesson not found.');
$courseId = (int)$lesson['course_id'];

$s = $pdo->prepare("SELECT id FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=? AND status IN ('active','completed')");
$s->execute([$sid, $courseId, $tid]); $enrollmentId = (int)$s->fetchColumn();
if (!$enrollmentId) $respond(false, 'You are not enrolled in this course.');

$pdo->prepare("INSERT INTO lesson_progress(tenant_id,student_id,course_id,lesson_id,status,completed_at)
    VALUES(?,?,?,?, 'completed', NOW())
    ON DUPLICATE KEY UPDATE status='completed', completed_at=NOW()")
    ->execute([$tid, $sid, $courseId, $lessonId]);

$total = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id=? AND tenant_id=?');
$total->execute([$courseId, $tid]); $totalLessons = (int)$total->fetchColumn();
$doneQ = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id=? AND course_id=? AND tenant_id=? AND status='completed'");
$doneQ->execute([$sid, $courseId, $tid]); $doneLessons = (int)$doneQ->fetchColumn();
$percent = $totalLessons ? round($doneLessons / $totalLessons * 100, 2) : 0.0;

$completed = $percent >= 100;
$pdo->prepare('UPDATE enrollments SET progress_percent=?, last_accessed_at=NOW()' . ($completed ? ", status='completed', completed_at=NOW()" : '') . ' WHERE id=?')
    ->execute([$percent, $enrollmentId]);

$certIssued = false;
if ($completed) {
    $exists = $pdo->prepare('SELECT COUNT(*) FROM certificates WHERE student_id=? AND course_id=? AND tenant_id=? AND revoked_at IS NULL');
    $exists->execute([$sid, $courseId, $tid]);
    if ((int)$exists->fetchColumn() === 0) {
        $s = $pdo->prepare('SELECT title FROM courses WHERE id=?');
        $s->execute([$courseId]); $courseTitle = (string)$s->fetchColumn();
        $code = 'LURNI-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare('INSERT INTO certificates(tenant_id,student_id,course_id,certificate_code,title) VALUES(?,?,?,?,?)')
            ->execute([$tid, $sid, $courseId, $code, 'Certificate of Completion — ' . $courseTitle]);
        notify_user($pdo, $tid, (int)$me['id'], 'Certificate earned', 'Congratulations! You completed "' . $courseTitle . '" and earned a certificate.', '/student/certificates.php');
        $certIssued = true;
    }
}
log_activity($pdo, 'lesson_completed', 'Completed lesson: ' . $lesson['title'], $tid, (int)$me['id']);
$respond(true, $certIssued ? 'Course complete — your certificate has been issued!' : 'Lesson marked complete.', [
    'progress_percent' => $percent,
    'certificate_issued' => $certIssued,
]);
