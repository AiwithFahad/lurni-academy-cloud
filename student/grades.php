<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Grades'; $active = 'grades';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT a.*, qz.title, c.title AS course_title FROM quiz_attempts a
    JOIN quizzes qz ON qz.id=a.quiz_id JOIN courses c ON c.id=qz.course_id
    WHERE a.student_id=? AND a.tenant_id=? ORDER BY a.submitted_at DESC");
$s->execute([$sid, $tid]); $quizRows = $s->fetchAll();
$s = $pdo->prepare("SELECT s.*, a.title AS assignment_title, a.max_points, c.title AS course_title FROM submissions s
    JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id
    WHERE s.student_id=? AND s.tenant_id=? ORDER BY s.submitted_at DESC");
$s->execute([$sid, $tid]); $subRows = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-6"><div class="card"><div class="card-header bg-white fw-bold">Quiz results</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Quiz</th><th>Score</th><th>Result</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($quizRows as $a): ?>
      <tr><td><strong><?= e($a['title']) ?></strong><br><small class="text-muted"><?= e($a['course_title']) ?></small></td>
        <td><?= rtrim(rtrim(number_format((float)$a['score'], 2), '0'), '.') ?> / <?= rtrim(rtrim(number_format((float)$a['total_points'], 2), '0'), '.') ?> (<?= number_format((float)$a['percent'], 0) ?>%)</td>
        <td><span class="badge bg-<?= (int)$a['passed'] ? 'success' : 'danger' ?>-subtle text-<?= (int)$a['passed'] ? 'success' : 'danger' ?>"><?= (int)$a['passed'] ? 'Passed' : 'Not passed' ?></span></td>
        <td><small><?= e(date('M j, Y', strtotime($a['submitted_at']))) ?></small></td></tr>
      <?php endforeach; ?>
      <?php if (!$quizRows): ?><tr><td colspan="4" class="text-center text-muted py-3">No quiz attempts yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-6"><div class="card"><div class="card-header bg-white fw-bold">Assignment grades</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Assignment</th><th>Grade</th><th>Status</th><th>Feedback</th></tr></thead><tbody>
      <?php foreach ($subRows as $r): ?>
      <tr><td><strong><?= e($r['assignment_title']) ?></strong><br><small class="text-muted"><?= e($r['course_title']) ?></small></td>
        <td><?= $r['grade'] !== null ? '<strong>' . rtrim(rtrim(number_format((float)$r['grade'], 2), '0'), '.') . '</strong> / ' . (int)$r['max_points'] : '—' ?></td>
        <td><span class="badge bg-<?= $r['status'] === 'graded' ? 'success' : 'secondary' ?>-subtle text-<?= $r['status'] === 'graded' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
        <td><small><?= e($r['feedback'] ?? '') ?></small></td></tr>
      <?php endforeach; ?>
      <?php if (!$subRows): ?><tr><td colspan="4" class="text-center text-muted py-3">No submissions yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
