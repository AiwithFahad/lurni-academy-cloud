<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['instructor']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Grading'; $active = 'grading';
require_permission($pdo, 'grading.manage');

$s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $ins = $s->fetch();
if (!$ins) { http_response_code(403); exit('Instructor profile not found.'); }
$iid = (int)$ins['id'];

if (is_post()) {
    verify_csrf();
    if (post('action') === 'grade') {
        $sid = (int)($_POST['submission_id'] ?? 0);
        // Ownership: the submission must belong to one of this instructor's courses.
        $s = $pdo->prepare('SELECT s.*, a.max_points, a.title, st.user_id FROM submissions s
            JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id JOIN students st ON st.id=s.student_id
            WHERE s.id=? AND s.tenant_id=? AND c.instructor_id=?');
        $s->execute([$sid, $tid, $iid]); $sub = $s->fetch();
        if ($sub) {
            $grade = max(0, (float)($_POST['grade'] ?? 0));
            if ($grade > (float)$sub['max_points']) { flash('error', 'Grade cannot exceed ' . (int)$sub['max_points'] . ' points.'); redirect('/instructor/grading.php'); }
            $pdo->prepare("UPDATE submissions SET grade=?, feedback=?, status='graded', graded_by=?, graded_at=NOW() WHERE id=? AND tenant_id=?")
                ->execute([$grade, post('feedback'), $me['id'], $sid, $tid]);
            notify_user($pdo, $tid, (int)$sub['user_id'], 'Assignment graded', 'Your submission for "' . $sub['title'] . '" was graded: ' . $grade . '/' . (int)$sub['max_points'] . '.', '/student/grades.php');
            flash('success', 'Graded and the student was notified.');
        }
    }
    redirect('/instructor/grading.php' . ((int)($_POST['assignment_id'] ?? 0) ? '?assignment_id=' . (int)$_POST['assignment_id'] : ''));
}

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$where = ['s.tenant_id=?', 'c.instructor_id=?']; $args = [$tid, $iid];
if ($assignmentId) { $where[] = 's.assignment_id=?'; $args[] = $assignmentId; }
$s = $pdo->prepare("SELECT s.*, a.title AS assignment_title, a.max_points, c.title AS course_title, u.name AS student_name
    FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id
    JOIN students st ON st.id=s.student_id JOIN users u ON u.id=st.user_id
    WHERE " . implode(' AND ', $where) . " ORDER BY (s.status='graded'), s.submitted_at DESC LIMIT 60");
$s->execute($args); $rows = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Student</th><th>Assignment</th><th>Submitted</th><th>Status</th><th>Grade</th><th class="text-end">Grade it</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
  <tr><td><span class="avatar-circle sm"><?= e(initials($r['student_name'])) ?></span> <strong><?= e($r['student_name']) ?></strong></td>
    <td><?= e($r['assignment_title']) ?><br><small class="text-muted"><?= e($r['course_title']) ?></small></td>
    <td><small><?= e(date('M j, Y H:i', strtotime($r['submitted_at']))) ?></small></td>
    <td><span class="badge bg-<?= $r['status'] === 'graded' ? 'success' : ($r['status'] === 'late' ? 'danger' : 'warning') ?>-subtle text-<?= $r['status'] === 'graded' ? 'success' : ($r['status'] === 'late' ? 'danger' : 'warning') ?>"><?= e($r['status']) ?></span></td>
    <td><?= $r['grade'] !== null ? '<strong>' . rtrim(rtrim(number_format((float)$r['grade'], 2), '0'), '.') . '</strong> / ' . (int)$r['max_points'] : '—' ?></td>
    <td class="text-end"><button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#grade<?= (int)$r['id'] ?>"><?= $r['status'] === 'graded' ? 'Re-grade' : 'Grade' ?></button></td></tr>
  <tr class="collapse-row"><td colspan="6" class="p-0 border-0"><div class="collapse" id="grade<?= (int)$r['id'] ?>">
    <div class="p-3 bg-light">
      <p class="small mb-2"><strong>Submission:</strong> <?= nl2br(e($r['content'] ?? '')) ?><?php if ($r['file_path']): ?> · <a href="/<?= e($r['file_path']) ?>" download>Download attached file</a><?php endif; ?></p>
      <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?>
        <input type="hidden" name="action" value="grade"><input type="hidden" name="submission_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="assignment_id" value="<?= (int)$r['assignment_id'] ?>">
        <div class="col-md-2"><label class="form-label">Grade (max <?= (int)$r['max_points'] ?>)</label><input class="form-control" type="number" step="0.5" min="0" max="<?= (int)$r['max_points'] ?>" name="grade" required value="<?= e($r['grade'] ?? '') ?>"></div>
        <div class="col-md-8"><label class="form-label">Feedback</label><input class="form-control" name="feedback" value="<?= e($r['feedback'] ?? '') ?>" placeholder="Feedback for the student…"></div>
        <div class="col-md-2"><button class="btn btn-success w-100">Save grade</button></div>
      </form>
    </div></div></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No submissions yet for your courses.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
