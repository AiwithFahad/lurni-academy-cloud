<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// /api/discussion.php — course Q&A / discussion threads.
// GET  ?course_id=N[&lesson_id=M] → JSON thread list (published only), oldest first,
//                                   replies nested under their parent post.
// POST course_id, body[, lesson_id][, parent_id] → create a post/reply (JSON).
// Access: students must be enrolled (or the lesson must be a free preview for
// reading); instructors of the course and tenant admins may also post.
header('Content-Type: application/json');
$me = require_role(['student', 'instructor', 'admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();

$courseId = (int)($_REQUEST['course_id'] ?? 0);
$s = $pdo->prepare('SELECT id FROM courses WHERE id=? AND tenant_id=?');
$s->execute([$courseId, $tid]);
if (!$s->fetch()) { http_response_code(404); echo json_encode(['ok' => false, 'message' => 'Course not found.']); exit; }

// Authorization: enrollment (student) / course ownership (instructor) / admin.
$canPost = false; $canRead = false;
if ($me['role'] === 'admin') { $canRead = $canPost = true; }
elseif ($me['role'] === 'instructor') {
    $ins = current_instructor($pdo);
    if ($ins) {
        $s = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE id=? AND tenant_id=? AND instructor_id=?');
        $s->execute([$courseId, $tid, (int)$ins['id']]);
        $canRead = $canPost = (int)$s->fetchColumn() > 0;
    }
} else {
    $sid = current_student_id($pdo);
    if ($sid) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=? AND status IN ('active','completed')");
        $s->execute([$sid, $courseId, $tid]);
        $canRead = $canPost = (int)$s->fetchColumn() > 0;
    }
}
if (!$canRead) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Enroll in the course to join the discussion.']); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $lessonId = (int)($_GET['lesson_id'] ?? 0);
    $sql = "SELECT d.id, d.parent_id, d.lesson_id, d.body, d.created_at, u.name AS author_name, u.role AS author_role
            FROM discussions d JOIN users u ON u.id=d.user_id
            WHERE d.course_id=? AND d.tenant_id=? AND d.status='published'";
    $args = [$courseId, $tid];
    if ($lessonId) { $sql .= ' AND (d.lesson_id=? OR d.lesson_id IS NULL)'; $args[] = $lessonId; }
    $sql .= ' ORDER BY d.created_at ASC LIMIT 200';
    $s = $pdo->prepare($sql); $s->execute($args);
    $rows = $s->fetchAll();
    $byId = []; $roots = [];
    foreach ($rows as $r) { $r['replies'] = []; $byId[(int)$r['id']] = $r; }
    foreach ($byId as $id => $r) {
        if ($r['parent_id'] && isset($byId[(int)$r['parent_id']])) $byId[(int)$r['parent_id']]['replies'][] = &$byId[$id];
        elseif (!$r['parent_id']) $roots[] = &$byId[$id];
    }
    echo json_encode(['ok' => true, 'threads' => $roots]);
    exit;
}
if (!$canPost) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'You cannot post in this course.']); exit; }
verify_csrf();
$body = trim((string)($_POST['body'] ?? ''));
if ($body === '' || mb_strlen($body) > 4000) { http_response_code(422); echo json_encode(['ok' => false, 'message' => 'Write a message between 1 and 4000 characters.']); exit; }
$lessonId = (int)($_POST['lesson_id'] ?? 0) ?: null;
$parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
if ($parentId) {
    $s = $pdo->prepare('SELECT id FROM discussions WHERE id=? AND course_id=? AND tenant_id=? AND parent_id IS NULL');
    $s->execute([$parentId, $courseId, $tid]);
    if (!$s->fetch()) { http_response_code(422); echo json_encode(['ok' => false, 'message' => 'You can only reply to a top-level post.']); exit; }
}
$pdo->prepare("INSERT INTO discussions(tenant_id,course_id,lesson_id,user_id,parent_id,body,status,created_at) VALUES(?,?,?,?,?,?, 'published', NOW())")
    ->execute([$tid, $courseId, $lessonId, (int)$me['id'], $parentId, $body]);
$newId = (int)$pdo->lastInsertId();
// Notify the course instructor about new questions (not about their own posts).
if (!$parentId) {
    $s = $pdo->prepare('SELECT i.user_id FROM courses c JOIN instructors i ON i.id=c.instructor_id WHERE c.id=? AND c.tenant_id=?');
    $s->execute([$courseId, $tid]); $insUser = $s->fetchColumn();
    if ($insUser && (int)$insUser !== (int)$me['id']) {
        notify_user($pdo, $tid, (int)$insUser, 'New course question', $me['name'] . ' asked a question in your course discussion.', '/student/course.php?id=' . $courseId);
        queue_email_to_user($pdo, (int)$insUser, 'New question in your course', $me['name'] . " asked:\n\n" . $body);
    }
}
log_activity($pdo, 'Posted in course discussion #' . $courseId);
echo json_encode(['ok' => true, 'id' => $newId, 'message' => 'Posted.']);
