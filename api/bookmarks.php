<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// /api/bookmarks.php — lesson bookmarks for the signed-in student.
// POST (form or JSON): lesson_id            → toggle bookmark, JSON result.
// GET ?course_id=N                            → JSON list of bookmarked lesson ids.
header('Content-Type: application/json');
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$sid = current_student_id($pdo);
if (!$sid) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Student profile not found.']); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $s = $pdo->prepare('SELECT lesson_id FROM lesson_bookmarks WHERE student_id=? AND tenant_id=? AND course_id=? ORDER BY created_at DESC');
    $s->execute([$sid, $tid, $courseId]);
    echo json_encode(['ok' => true, 'bookmarks' => array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN))]);
    exit;
}
verify_csrf();
$lessonId = (int)($_POST['lesson_id'] ?? 0);
$s = $pdo->prepare('SELECT id, course_id FROM lessons WHERE id=? AND tenant_id=?');
$s->execute([$lessonId, $tid]); $lesson = $s->fetch() ?: null;
if (!$lesson) { http_response_code(404); echo json_encode(['ok' => false, 'message' => 'Lesson not found.']); exit; }
$courseId = (int)$lesson['course_id'];
$s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=? AND status IN ('active','completed')");
$s->execute([$sid, $courseId, $tid]);
if ((int)$s->fetchColumn() === 0) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Enroll in the course first.']); exit; }

$s = $pdo->prepare('SELECT id FROM lesson_bookmarks WHERE student_id=? AND lesson_id=?');
$s->execute([$sid, $lessonId]);
if ($s->fetch()) {
    $pdo->prepare('DELETE FROM lesson_bookmarks WHERE student_id=? AND lesson_id=?')->execute([$sid, $lessonId]);
    echo json_encode(['ok' => true, 'bookmarked' => false, 'message' => 'Bookmark removed.']);
} else {
    $pdo->prepare('INSERT INTO lesson_bookmarks(tenant_id,student_id,course_id,lesson_id,created_at) VALUES(?,?,?,?,NOW())')
        ->execute([$tid, $sid, $courseId, $lessonId]);
    echo json_encode(['ok' => true, 'bookmarked' => true, 'message' => 'Lesson bookmarked.']);
}
