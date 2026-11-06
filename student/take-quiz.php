<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Take Quiz'; $active = 'quizzes';

$sid = current_student_id($pdo);
$quizId = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare("SELECT qz.*, c.title AS course_title FROM quizzes qz JOIN courses c ON c.id=qz.course_id
    JOIN enrollments en ON en.course_id=qz.course_id AND en.student_id=?
    WHERE qz.id=? AND qz.tenant_id=? AND qz.status='published' AND en.status IN ('active','completed')");
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
$s = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND tenant_id=?');
$s->execute([$quizId, $sid, $tid]); $attemptCount = (int)$s->fetchColumn();
$maxAttempts = (int)($quiz['max_attempts'] ?? 0);
$attemptsLeft = $maxAttempts > 0 ? max(0, $maxAttempts - $attemptCount) : null;

$lastAttempt = null;
if (isset($_GET['done'])) {
    $s = $pdo->prepare('SELECT * FROM quiz_attempts WHERE quiz_id=? AND student_id=? AND tenant_id=? ORDER BY id DESC LIMIT 1');
    $s->execute([$quizId, $sid, $tid]); $lastAttempt = $s->fetch() ?: null;
}
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-3">
  <h2 class="h4 fw-bold"><?= e($quiz['title']) ?></h2>
  <p class="text-muted mb-0"><?= e($quiz['course_title']) ?> · <?= count($questions) ?> questions · pass mark <?= (int)$quiz['pass_percent'] ?>%<?php if ($attemptsLeft !== null): ?> · <?= $attemptsLeft ?> of <?= $maxAttempts ?> attempts left<?php endif; ?></p>
  <?php if ($quiz['description']): ?><p class="text-muted"><?= e($quiz['description']) ?></p><?php endif; ?>
</div>

<?php if ($lastAttempt): ?>
<div class="card mb-3 border-<?= (int)$lastAttempt['passed'] ? 'success' : 'danger' ?>">
  <div class="card-body text-center py-4">
    <div class="display-6 fw-bold text-<?= (int)$lastAttempt['passed'] ? 'success' : 'danger' ?>"><?= number_format((float)$lastAttempt['percent'], 0) ?>%</div>
    <p class="mb-1">You scored <?= rtrim(rtrim(number_format((float)$lastAttempt['score'], 2), '0'), '.') ?> of <?= rtrim(rtrim(number_format((float)$lastAttempt['total_points'], 2), '0'), '.') ?> points.</p>
    <span class="badge bg-<?= (int)$lastAttempt['passed'] ? 'success' : 'danger' ?> fs-6"><?= (int)$lastAttempt['passed'] ? 'Passed 🎉' : 'Not passed — review the lessons and try again' ?></span>
    <?php if (!empty($lastAttempt['feedback'])): ?>
    <div class="alert alert-info text-start mt-3 mb-0"><strong><i class="bi bi-chat-left-text"></i> Instructor feedback:</strong> <?= e($lastAttempt['feedback']) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($attemptsLeft === 0): ?>
<div class="alert alert-warning">You have used all <?= $maxAttempts ?> attempts for this quiz. Ask your instructor if you need another try.</div>
<?php else: ?>
<form method="post" action="/api/quiz-submit.php" class="card" id="quizForm">
  <?php if ((int)$quiz['time_limit_minutes'] > 0): ?>
  <div class="card-header d-flex justify-content-between align-items-center bg-white">
    <span class="fw-semibold"><i class="bi bi-stopwatch"></i> Time limit <?= (int)$quiz['time_limit_minutes'] ?> minutes</span>
    <span class="badge bg-dark fs-6 js-quiz-timer" data-minutes="<?= (int)$quiz['time_limit_minutes'] ?>" data-form="quizForm">--:--</span>
  </div>
  <?php endif; ?>
<div class="card-body">
  <?= csrf_field() ?><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
  <input type="hidden" name="started_at" value="<?= date('Y-m-d H:i:s') ?>">
  <?php foreach ($questions as $i => $qq): $isMulti = ($qq['type'] ?? 'single') === 'multiple'; ?>
  <fieldset class="mb-4">
    <legend class="h6 fw-bold">Q<?= $i + 1 ?>. <?= e($qq['question']) ?> <span class="badge bg-primary-subtle text-primary"><?= (int)$qq['points'] ?> pts</span><?php if ($isMulti): ?> <span class="badge bg-warning-subtle text-warning-emphasis">Select all that apply</span><?php endif; ?></legend>
    <?php if (($qq['type'] ?? '') === 'true_false'): ?>
    <input type="hidden" name="tf_<?= (int)$qq['id'] ?>" value="1">
    <?php endif; ?>
    <?php foreach ($qq['options'] as $o): ?>
    <div class="form-check">
      <input class="form-check-input" type="<?= $isMulti ? 'checkbox' : 'radio' ?>" name="answers[<?= (int)$qq['id'] ?>]<?= $isMulti ? '[]' : '' ?>" id="a<?= (int)$o['id'] ?>" value="<?= (int)$o['id'] ?>">
      <label class="form-check-label" for="a<?= (int)$o['id'] ?>"><?= e($o['answer_text']) ?></label>
    </div>
    <?php endforeach; ?>
  </fieldset>
  <?php endforeach; ?>
  <?php if (!$questions): ?><p class="text-muted">This quiz has no questions yet.</p>
  <?php else: ?><button class="btn btn-gradient btn-lg" type="submit" onclick="return confirm('Submit your answers for grading?')">Submit answers</button><?php endif; ?>
  <a class="btn btn-link" href="/student/quizzes.php">Back to quizzes</a>
</div></form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
