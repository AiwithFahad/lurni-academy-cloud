<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// POST /api/enroll.php — enroll the signed-in student in a course of their
// tenant. Free self-enrollment; redirects back to the course page.
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
verify_csrf();

$courseId = (int)($_POST['course_id'] ?? 0);
$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT id, title FROM courses WHERE id=? AND tenant_id=? AND status='published'");
$s->execute([$courseId, $tid]); $course = $s->fetch() ?: null;
if (!$sid || !$course) { flash('error', 'Course not found.'); redirect('/student/browse.php'); }

$s = $pdo->prepare('SELECT id, status FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=?');
$s->execute([$sid, $courseId, $tid]); $existing = $s->fetch() ?: null;
if ($existing) {
    if ($existing['status'] !== 'active' && $existing['status'] !== 'completed') {
        $pdo->prepare("UPDATE enrollments SET status='active' WHERE id=?")->execute([(int)$existing['id']]);
    }
    flash('success', 'You are already enrolled in "' . $course['title'] . '".');
} else {
    $pdo->prepare("INSERT INTO enrollments(tenant_id,student_id,course_id,status,source) VALUES(?,?,?,'active','self')")
        ->execute([$tid, $sid, $courseId]);
    log_activity($pdo, 'enrolled', 'Enrolled in course: ' . $course['title'], $tid, $me['id']);
    flash('success', 'Enrolled! "' . $course['title'] . '" is now in My Learning.');
}
redirect('/student/course.php?id=' . $courseId);
