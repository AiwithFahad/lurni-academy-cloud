<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// /api/notes.php — private per-lesson notes for the signed-in student.
// GET  ?lesson_id=N                 → JSON {note}
// POST lesson_id + note (form/JSON) → save (insert or update), JSON result.
header('Content-Type: application/json');
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$sid = current_student_id($pdo);
if (!$sid) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Student profile not found.']); exit; }

$lessonId = (int)($_REQUEST['lesson_id'] ?? 0);
$s = $pdo->prepare('SELECT id, course_id FROM lessons WHERE id=? AND tenant_id=?');
$s->execute([$lessonId, $tid]); $lesson = $s->fetch() ?: null;
if (!$lesson) { http_response_code(404); echo json_encode(['ok' => false, 'message' => 'Lesson not found.']); exit; }
$courseId = (int)$lesson['course_id'];
$s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=? AND status IN ('active','completed')");
$s->execute([$sid, $courseId, $tid]);
if ((int)$s->fetchColumn() === 0) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Enroll in the course first.']); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $s = $pdo->prepare('SELECT note, updated_at FROM lesson_notes WHERE student_id=? AND lesson_id=?');
    $s->execute([$sid, $lessonId]); $row = $s->fetch() ?: null;
    echo json_encode(['ok' => true, 'note' => $row['note'] ?? '', 'updated_at' => $row['updated_at'] ?? null]);
    exit;
}
verify_csrf();
$note = trim((string)($_POST['note'] ?? ''));
if (mb_strlen($note) > 5000) $note = mb_substr($note, 0, 5000);
if ($note === '') {
    $pdo->prepare('DELETE FROM lesson_notes WHERE student_id=? AND lesson_id=?')->execute([$sid, $lessonId]);
    echo json_encode(['ok' => true, 'message' => 'Note cleared.']);
    exit;
}
$pdo->prepare('INSERT INTO lesson_notes(tenant_id,student_id,course_id,lesson_id,note,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())
    ON DUPLICATE KEY UPDATE note=VALUES(note), updated_at=NOW()')
    ->execute([$tid, $sid, $courseId, $lessonId, $note]);
echo json_encode(['ok' => true, 'message' => 'Note saved.']);
