<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Quizzes'; $active = 'quizzes';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();

$s = $pdo->prepare("SELECT qz.*, c.title AS course_title,
    (SELECT COUNT(*) FROM questions q WHERE q.quiz_id=qz.id) AS qc,
    (SELECT MAX(a.percent) FROM quiz_attempts a WHERE a.quiz_id=qz.id AND a.student_id=?) AS best,
    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id=qz.id AND a.student_id=?) AS attempts
    FROM quizzes qz JOIN courses c ON c.id=qz.course_id
    JOIN enrollments en ON en.course_id=qz.course_id AND en.student_id=?
    WHERE qz.tenant_id=? AND qz.status='published' ORDER BY qz.id DESC");
$s->execute([$sid, $sid, $sid, $tid]); $quizzes = $s->fetchAll();
$s = $pdo->prepare("SELECT a.*, qz.title FROM quiz_attempts a JOIN quizzes qz ON qz.id=a.quiz_id
    WHERE a.student_id=? AND a.tenant_id=? ORDER BY a.submitted_at DESC LIMIT 10");
$s->execute([$sid, $tid]); $attempts = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7">
    <?php foreach ($quizzes as $q): ?>
    <div class="card mb-2"><div class="card-body d-flex justify-content-between align-items-center py-3">
      <div><strong><?= e($q['title']) ?></strong><br><small class="text-muted"><?= e($q['course_title']) ?> · <?= (int)$q['qc'] ?> questions · pass <?= (int)$q['pass_percent'] ?>%<?= (int)$q['time_limit_minutes'] ? ' · ' . (int)$q['time_limit_minutes'] . ' min' : '' ?></small>
      <?php if ((int)$q['attempts']): ?><br><small class="text-success">Best score: <?= number_format((float)$q['best'], 0) ?>% · <?= (int)$q['attempts'] ?> attempt(s)</small><?php endif; ?></div>
      <a class="btn btn-gradient" href="/student/take-quiz.php?id=<?= (int)$q['id'] ?>"><?= (int)$q['attempts'] ? 'Retake' : 'Start quiz' ?></a>
    </div></div>
    <?php endforeach; ?>
    <?php if (!$quizzes): ?><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">No quizzes available yet. Enroll in a course to unlock its quizzes.</p></div><?php endif; ?>
  </div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">My recent attempts</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($attempts as $a): ?>
      <li class="list-group-item d-flex justify-content-between"><span><?= e($a['title']) ?><br><small class="text-muted"><?= e(date('M j, Y H:i', strtotime($a['submitted_at']))) ?></small></span>
      <span class="text-end"><strong><?= number_format((float)$a['percent'], 0) ?>%</strong><br>
      <span class="badge bg-<?= (int)$a['passed'] ? 'success' : 'danger' ?>-subtle text-<?= (int)$a['passed'] ? 'success' : 'danger' ?>"><?= (int)$a['passed'] ? 'Passed' : 'Not passed' ?></span></span></li>
    <?php endforeach; ?>
    <?php if (!$attempts): ?><li class="list-group-item text-muted">No attempts yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
