<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Assignments'; $active = 'assignments';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();

if (is_post()) {
    verify_csrf();
    if (post('action') === 'submit') {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $own = $pdo->prepare('SELECT a.* FROM assignments a JOIN enrollments en ON en.course_id=a.course_id AND en.student_id=? WHERE a.id=? AND a.tenant_id=?');
        $own->execute([$sid, $aid, $tid]); $asg = $own->fetch() ?: null;
        if ($asg) {
            $filePath = null; $status = 'submitted';
            if (!empty($_FILES['file']['name'])) {
                $up = handle_upload('file', 'submissions/' . date('Y/m'));
                if ($up['ok']) { $filePath = $up['path']; }
                else { flash('error', 'Upload failed: ' . implode(' ', $up['errors'])); redirect('/student/assignments.php'); }
            }
            if ($asg['due_date'] && strtotime($asg['due_date']) < time()) $status = 'late';
            $existing = $pdo->prepare('SELECT id, status, attempt_no FROM submissions WHERE assignment_id=? AND student_id=? AND tenant_id=? ORDER BY attempt_no DESC, id DESC LIMIT 1');
            $existing->execute([$aid, $sid, $tid]); $ex = $existing->fetch();
            if ($ex && $ex['status'] === 'graded') {
                // Graded work changes only as a fresh attempt, and only when
                // the assignment allows resubmission.
                if (empty($asg['allow_resubmission'])) { flash('error', 'This submission is already graded and this assignment does not allow resubmission.'); redirect('/student/assignments.php'); }
                $pdo->prepare('INSERT INTO submissions(tenant_id,assignment_id,student_id,content,file_path,status,attempt_no) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$tid, $aid, $sid, post('content'), $filePath, $status, (int)$ex['attempt_no'] + 1]);
                flash('success', 'Resubmitted as attempt ' . ((int)$ex['attempt_no'] + 1) . '.' . ($status === 'late' ? ' It was marked late.' : ''));
            } elseif ($ex) {
                $pdo->prepare('UPDATE submissions SET content=?, file_path=COALESCE(?,file_path), status=?, submitted_at=NOW() WHERE id=?')
                    ->execute([post('content'), $filePath, $status, (int)$ex['id']]);
                flash('success', 'Submission updated.');
            } else {
                $pdo->prepare('INSERT INTO submissions(tenant_id,assignment_id,student_id,content,file_path,status,attempt_no) VALUES(?,?,?,?,?,?,1)')
                    ->execute([$tid, $aid, $sid, post('content'), $filePath, $status]);
                flash('success', 'Assignment submitted.' . ($status === 'late' ? ' It was marked late.' : ''));
            }
            log_activity($pdo, 'Submitted assignment #' . $aid, $tid);
        }
    }
    redirect('/student/assignments.php');
}

$s = $pdo->prepare("SELECT a.*, c.title AS course_title, s.id AS sub_id, s.status AS sub_status, s.grade, s.feedback, s.submitted_at, s.attempt_no
    FROM assignments a JOIN courses c ON c.id=a.course_id
    JOIN enrollments en ON en.course_id=a.course_id AND en.student_id=?
    LEFT JOIN submissions s ON s.id=(SELECT s2.id FROM submissions s2 WHERE s2.assignment_id=a.id AND s2.student_id=? ORDER BY s2.attempt_no DESC, s2.id DESC LIMIT 1)
    WHERE a.tenant_id=? ORDER BY (s.id IS NOT NULL), a.due_date IS NULL, a.due_date");
$s->execute([$sid, $sid, $tid]); $rows = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
<?php foreach ($rows as $a): $overdue = $a['due_date'] && strtotime($a['due_date']) < time() && !$a['sub_id']; ?>
  <div class="col-xl-6"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start">
      <div><strong><?= e($a['title']) ?></strong><br><small class="text-muted"><?= e($a['course_title']) ?> · max <?= (int)$a['max_points'] ?> pts · due <?= $a['due_date'] ? e(date('M j, Y H:i', strtotime($a['due_date']))) : 'any time' ?></small></div>
      <?php if ($a['sub_id']): ?><span class="badge bg-<?= $a['sub_status'] === 'graded' ? 'success' : ($a['sub_status'] === 'late' ? 'danger' : 'primary') ?>-subtle text-<?= $a['sub_status'] === 'graded' ? 'success' : ($a['sub_status'] === 'late' ? 'danger' : 'primary') ?>"><?= e($a['sub_status']) ?></span>
      <?php elseif ($overdue): ?><span class="badge bg-danger-subtle text-danger">Overdue</span>
      <?php else: ?><span class="badge bg-warning-subtle text-warning">Pending</span><?php endif; ?>
    </div>
    <?php if ($a['instructions']): ?><p class="small text-muted mt-2 mb-2"><?= nl2br(e($a['instructions'])) ?></p><?php endif; ?>
    <?php if ($a['sub_status'] === 'graded'): ?>
      <div class="alert alert-success py-2 mb-2"><strong>Grade: <?= rtrim(rtrim(number_format((float)$a['grade'], 2), '0'), '.') ?> / <?= (int)$a['max_points'] ?></strong> <small class="text-muted">(attempt <?= (int)($a['attempt_no'] ?? 1) ?>)</small><?php if ($a['feedback']): ?><br><span class="small"><?= e($a['feedback']) ?></span><?php endif; ?></div>
      <?php if (!empty($a['allow_resubmission'])): ?>
      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#sub<?= (int)$a['id'] ?>">Resubmit (new attempt)</button>
      <div class="collapse mt-3" id="sub<?= (int)$a['id'] ?>">
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
          <input type="hidden" name="action" value="submit"><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
          <div class="mb-2"><label class="form-label">Your answer / notes</label><textarea class="form-control" name="content" rows="3" placeholder="Paste your work, a link, or notes for your instructor…"></textarea></div>
          <div class="mb-2"><label class="form-label">Attach a file (optional)</label><input class="form-control" type="file" name="file"></div>
          <button class="btn btn-gradient">Submit new attempt</button>
        </form>
      </div>
      <?php endif; ?>
    <?php elseif (!$a['sub_id'] || $a['sub_status'] !== 'graded'): ?>
      <button class="btn btn-sm <?= $a['sub_id'] ? 'btn-outline-primary' : 'btn-gradient' ?>" data-bs-toggle="collapse" data-bs-target="#sub<?= (int)$a['id'] ?>"><?= $a['sub_id'] ? 'Update submission' : 'Submit work' ?></button>
      <div class="collapse mt-3" id="sub<?= (int)$a['id'] ?>">
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
          <input type="hidden" name="action" value="submit"><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
          <div class="mb-2"><label class="form-label">Your answer / notes</label><textarea class="form-control" name="content" rows="3" placeholder="Paste your work, a link, or notes for your instructor…"></textarea></div>
          <div class="mb-2"><label class="form-label">Attach a file (optional)</label><input class="form-control" type="file" name="file"></div>
          <button class="btn btn-gradient"><?= $a['sub_id'] ? 'Update submission' : 'Submit assignment' ?></button>
        </form>
      </div>
    <?php endif; ?>
    <?php if ($a['sub_id']): ?><div class="small text-muted mt-2">Submitted <?= e(date('M j, Y H:i', strtotime($a['submitted_at']))) ?></div><?php endif; ?>
  </div></div></div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="col-12"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">No assignments yet — they appear here when your instructors set them.</p></div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
