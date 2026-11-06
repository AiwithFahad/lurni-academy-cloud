<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Take Quiz'; $active = 'quizzes';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$quizId = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare("SELECT qz.*, c.title AS course_title FROM quizzes qz JOIN courses c ON c.id=qz.course_id
    JOIN enrollments en ON en.course_id=qz.course_id AND en.student_id=?
    WHERE qz.id=? AND qz.tenant_id=? AND qz.status='published'");
$s->execute([$sid, $quizId, $tid]); $quiz = $s->fetch() ?: null;
if (!$quiz) { http_response_code(404); exit('Quiz not available. Enroll in the course first.'); }

$questions = [];
$s = $pdo->prepare('SELECT * FROM questions WHERE quiz_id=? AND tenant_id=? ORDER BY sort_order, id');
$s->execute([$quizId, $tid]);
foreach ($s->fetchAll() as $qq) {
    $a = $pdo->prepare('SELECT id, answer_text FROM answers WHERE question_id=? ORDER BY id');
    $a->execute([$qq['id']]); $qq['options'] = $a->fetchAll();
    $questions[] = $qq;
}
$lastAttempt = null;
if (isset($_GET['done'])) {
    $s = $pdo->prepare('SELECT * FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND tenant_id=? ORDER BY id DESC LIMIT 1');
    $s->execute([$quizId, $sid, $tid]); $lastAttempt = $s->fetch() ?: null;
}
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-3">
  <h2 class="h4 fw-bold"><?= e($quiz['title']) ?></h2>
  <p class="text-muted mb-0"><?= e($quiz['course_title']) ?> · <?= count($questions) ?> questions · pass mark <?= (int)$quiz['pass_percent'] ?>%<?= (int)$quiz['time_limit_minutes'] ? ' · suggested time ' . (int)$quiz['time_limit_minutes'] . ' minutes' : '' ?></p>
  <?php if ($quiz['description']): ?><p class="text-muted"><?= e($quiz['description']) ?></p><?php endif; ?>
</div>

<?php if ($lastAttempt): ?>
<div class="card mb-3 border-<?= (int)$lastAttempt['passed'] ? 'success' : 'danger' ?>">
  <div class="card-body text-center py-4">
    <div class="display-6 fw-bold text-<?= (int)$lastAttempt['passed'] ? 'success' : 'danger' ?>"><?= number_format((float)$lastAttempt['percent'], 0) ?>%</div>
    <p class="mb-1">You scored <?= rtrim(rtrim(number_format((float)$lastAttempt['score'], 2), '0'), '.') ?> of <?= rtrim(rtrim(number_format((float)$lastAttempt['total_points'], 2), '0'), '.') ?> points.</p>
    <span class="badge bg-<?= (int)$lastAttempt['passed'] ? 'success' : 'danger' ?> fs-6"><?= (int)$lastAttempt['passed'] ? 'Passed 🎉' : 'Not passed — review the lessons and try again' ?></span>
  </div>
</div>
<?php endif; ?>

<form method="post" action="/api/quiz-submit.php" class="card"><div class="card-body">
  <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
  <?php foreach ($questions as $i => $qq): ?>
  <fieldset class="mb-4">
    <legend class="h6 fw-bold">Q<?= $i + 1 ?>. <?= e($qq['question']) ?> <span class="badge bg-primary-subtle text-primary"><?= (int)$qq['points'] ?> pts</span></legend>
    <?php foreach ($qq['options'] as $o): ?>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="answers[<?= (int)$qq['id'] ?>]" id="a<?= (int)$o['id'] ?>" value="<?= (int)$o['id'] ?>">
      <label class="form-check-label" for="a<?= (int)$o['id'] ?>"><?= e($o['answer_text']) ?></label>
    </div>
    <?php endforeach; ?>
  </fieldset>
  <?php endforeach; ?>
  <?php if (!$questions): ?><p class="text-muted">This quiz has no questions yet.</p>
  <?php else: ?><button class="btn btn-gradient btn-lg" type="submit" onclick="return confirm('Submit your answers for grading?')">Submit answers</button><?php endif; ?>
  <a class="btn btn-link" href="/student/quizzes.php">Back to quizzes</a>
</div></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
