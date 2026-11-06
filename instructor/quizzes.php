<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['instructor']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Quizzes & Questions'; $active = 'quizzes';
require_permission($pdo, 'quizzes.manage');

$s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $ins = $s->fetch();
if (!$ins) { http_response_code(403); exit('Instructor profile not found.'); }
$iid = (int)$ins['id'];

$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? AND instructor_id=? ORDER BY title');
$courses->execute([$tid, $iid]); $courses = $courses->fetchAll();

function own_quiz(PDO $pdo, int $tid, int $iid, int $quizId): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM quizzes qz JOIN courses c ON c.id=qz.course_id WHERE qz.id=? AND qz.tenant_id=? AND c.instructor_id=?');
    $s->execute([$quizId, $tid, $iid]);
    return (int)$s->fetchColumn() > 0;
}

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'quiz_save') {
        $id = (int)($_POST['id'] ?? 0);
        $cid = (int)($_POST['course_id'] ?? 0);
        $own = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE id=? AND tenant_id=? AND instructor_id=?');
        $own->execute([$cid, $tid, $iid]);
        if ((int)$own->fetchColumn() === 0) { flash('error', 'Choose one of your own courses.'); redirect('/instructor/quizzes.php'); }
        if ($id && own_quiz($pdo, $tid, $iid, $id)) {
            $pdo->prepare('UPDATE quizzes SET course_id=?, title=?, description=?, pass_percent=?, time_limit_minutes=?, max_attempts=?, status=? WHERE id=? AND tenant_id=?')
                ->execute([$cid, post('title'), post('description'), max(1, min(100, (int)($_POST['pass_percent'] ?? 70))), (int)($_POST['time_limit_minutes'] ?? 0), max(0, (int)($_POST['max_attempts'] ?? 0)), post('status') === 'draft' ? 'draft' : 'published', $id, $tid]);
            flash('success', 'Quiz updated.');
        } elseif (!$id) {
            $pdo->prepare('INSERT INTO quizzes(tenant_id,course_id,title,description,pass_percent,time_limit_minutes,max_attempts,status,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$tid, $cid, post('title'), post('description'), max(1, min(100, (int)($_POST['pass_percent'] ?? 70))), (int)($_POST['time_limit_minutes'] ?? 0), max(0, (int)($_POST['max_attempts'] ?? 0)), post('status') === 'draft' ? 'draft' : 'published', $me['id']]);
            flash('success', 'Quiz created. Now add questions below.');
        }
    } elseif ($action === 'quiz_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (own_quiz($pdo, $tid, $iid, $id)) { $pdo->prepare('DELETE FROM quizzes WHERE id=? AND tenant_id=?')->execute([$id, $tid]); flash('success', 'Quiz deleted.'); }
    } elseif ($action === 'question_save') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        if (own_quiz($pdo, $tid, $iid, $quizId)) {
            $qtype = in_array(post('qtype'), ['single', 'multiple', 'true_false'], true) ? post('qtype') : 'single';
            $pdo->prepare('INSERT INTO questions(tenant_id,quiz_id,question,type,points,sort_order) VALUES(?,?,?,?,?,?)')
                ->execute([$tid, $quizId, post('question'), $qtype, max(1, (int)($_POST['points'] ?? 1)), 0]);
            $qid = (int)$pdo->lastInsertId();
            if ($qtype === 'true_false') {
                $correct = post('tf_correct') === 'false' ? 1 : 0; // options: True, False
                foreach (['True', 'False'] as $i => $opt) {
                    $pdo->prepare('INSERT INTO answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$qid, $opt, $i === $correct ? 1 : 0]);
                }
            } else {
                // single: 'correct' holds one index; multiple: 'correct_multi[]' holds several.
                $correctSet = $qtype === 'multiple'
                    ? array_map('intval', (array)($_POST['correct_multi'] ?? []))
                    : [(int)($_POST['correct'] ?? 0)];
                foreach (($_POST['options'] ?? []) as $i => $opt) {
                    $opt = trim((string)$opt);
                    if ($opt === '') continue;
                    $pdo->prepare('INSERT INTO answers(question_id,answer_text,is_correct) VALUES(?,?,?)')->execute([$qid, $opt, in_array($i, $correctSet, true) ? 1 : 0]);
                }
            }
            flash('success', 'Question added.');
        }
    } elseif ($action === 'attempt_feedback') {
        $attemptId = (int)($_POST['attempt_id'] ?? 0);
        $s = $pdo->prepare('SELECT qa.id FROM quiz_attempts qa JOIN quizzes qz ON qz.id=qa.quiz_id JOIN courses c ON c.id=qz.course_id WHERE qa.id=? AND qa.tenant_id=? AND c.instructor_id=?');
        $s->execute([$attemptId, $tid, $iid]);
        if ($s->fetch()) {
            $pdo->prepare('UPDATE quiz_attempts SET feedback=?, feedback_by=? WHERE id=? AND tenant_id=?')->execute([post('feedback'), $me['id'], $attemptId, $tid]);
            flash('success', 'Feedback saved — the student can see it on their quiz result.');
        }
    } elseif ($action === 'question_delete') {
        $qid = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('SELECT quiz_id FROM questions WHERE id=? AND tenant_id=?');
        $s->execute([$qid, $tid]); $quizId = (int)$s->fetchColumn();
        if ($quizId && own_quiz($pdo, $tid, $iid, $quizId)) { $pdo->prepare('DELETE FROM questions WHERE id=? AND tenant_id=?')->execute([$qid, $tid]); flash('success', 'Question deleted.'); }
    }
    redirect('/instructor/quizzes.php' . ((int)($_POST['quiz_id'] ?? 0) ? '?quiz_id=' . (int)$_POST['quiz_id'] : ''));
}

$s = $pdo->prepare("SELECT qz.*, c.title AS course_title, (SELECT COUNT(*) FROM questions q WHERE q.quiz_id=qz.id) AS qc,
    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id=qz.id) AS ac
    FROM quizzes qz JOIN courses c ON c.id=qz.course_id WHERE qz.tenant_id=? AND c.instructor_id=? ORDER BY qz.id DESC");
$s->execute([$tid, $iid]); $quizzes = $s->fetchAll();
$quizId = (int)($_GET['quiz_id'] ?? ($quizzes[0]['id'] ?? 0));
$questions = [];
if ($quizId && own_quiz($pdo, $tid, $iid, $quizId)) {
    $s = $pdo->prepare('SELECT * FROM questions WHERE tenant_id=? AND quiz_id=? ORDER BY sort_order, id');
    $s->execute([$tid, $quizId]); $questions = $s->fetchAll();
    foreach ($questions as &$qq) { $a = $pdo->prepare('SELECT * FROM answers WHERE question_id=? ORDER BY id'); $a->execute([$qq['id']]); $qq['options'] = $a->fetchAll(); }
    unset($qq);
}
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0) && own_quiz($pdo, $tid, $iid, $eid)) { $s = $pdo->prepare('SELECT * FROM quizzes WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Quiz</th><th>Course</th><th>Questions</th><th>Attempts</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($quizzes as $q): ?>
      <tr class="<?= $quizId === (int)$q['id'] ? 'table-primary' : '' ?>"><td><a href="?quiz_id=<?= (int)$q['id'] ?>"><strong><?= e($q['title']) ?></strong></a><br><small class="text-muted">Pass <?= (int)$q['pass_percent'] ?>%</small></td>
        <td><small><?= e($q['course_title']) ?></small></td><td><?= (int)$q['qc'] ?></td><td><?= (int)$q['ac'] ?></td>
        <td><span class="badge bg-<?= $q['status'] === 'published' ? 'success' : 'secondary' ?>-subtle text-<?= $q['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($q['status']) ?></span></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$q['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete quiz?')"><?= csrf_field() ?><input type="hidden" name="action" value="quiz_delete"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$quizzes): ?><tr><td colspan="6" class="text-center text-muted py-4">No quizzes yet.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <?php if ($quizId): ?>
    <div class="card"><div class="card-header bg-white fw-bold">Questions in this quiz</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($questions as $i => $qq): ?>
        <li class="list-group-item"><div class="d-flex justify-content-between"><strong>Q<?= $i + 1 ?>. <?= e($qq['question']) ?></strong>
          <form method="post" onsubmit="return confirm('Delete question?')"><?= csrf_field() ?><input type="hidden" name="action" value="question_delete"><input type="hidden" name="quiz_id" value="<?= $quizId ?>"><input type="hidden" name="id" value="<?= (int)$qq['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div>
          <ul class="small mb-0 mt-1"><?php foreach ($qq['options'] as $o): ?><li class="<?= (int)$o['is_correct'] ? 'text-success fw-semibold' : 'text-muted' ?>"><?= e($o['answer_text']) ?><?= (int)$o['is_correct'] ? ' ✓' : '' ?></li><?php endforeach; ?></ul></li>
      <?php endforeach; ?>
      <?php if (!$questions): ?><li class="list-group-item text-muted">No questions yet — add the first one.</li><?php endif; ?>
      </ul></div>
    <?php endif; ?>
  </div>
  <div class="col-xl-5">
    <div class="card mb-3"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit quiz' : 'Create quiz' ?></div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="quiz_save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Course</label><select class="form-select" name="course_id" required>
          <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['course_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-4 mb-2"><label class="form-label">Pass %</label><input class="form-control" type="number" min="1" max="100" name="pass_percent" value="<?= e($edit['pass_percent'] ?? '70') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Time limit (min)</label><input class="form-control" type="number" min="0" name="time_limit_minutes" value="<?= e($edit['time_limit_minutes'] ?? '0') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Max attempts (0 = ∞)</label><input class="form-control" type="number" min="0" name="max_attempts" value="<?= e($edit['max_attempts'] ?? '0') ?>"></div>
        </div>
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="published" <?= ($edit['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= ($edit['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option></select></div>
        <button class="btn btn-primary w-100"><?= $edit ? 'Update quiz' : 'Create quiz' ?></button>
        <?php if ($edit): ?><a class="btn btn-link w-100" href="/instructor/quizzes.php">Cancel</a><?php endif; ?>
      </form></div></div>
    <?php if ($quizId): ?>
    <div class="card"><div class="card-header bg-white fw-bold">Add question</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="question_save"><input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <div class="mb-2"><label class="form-label">Question</label><textarea class="form-control" name="question" rows="2" required></textarea></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select js-question-type" name="qtype">
            <option value="single">Multiple choice (one answer)</option><option value="multiple">Multiple answers</option><option value="true_false">True / False</option></select></div>
          <div class="col-6 mb-2"><label class="form-label">Points</label><input class="form-control" type="number" min="1" name="points" value="1"></div>
        </div>
        <div class="js-options-block">
          <label class="form-label">Options — mark the correct answer(s)</label>
          <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="input-group mb-2"><span class="input-group-text"><input class="form-check-input mt-0 js-correct-single" type="radio" name="correct" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> aria-label="Correct option"><input class="form-check-input mt-0 js-correct-multi d-none" type="checkbox" name="correct_multi[]" value="<?= $i ?>" aria-label="Correct option"></span>
          <input class="form-control" name="options[]" placeholder="Option <?= $i + 1 ?>"></div>
          <?php endfor; ?>
        </div>
        <div class="js-tf-block d-none mb-2"><label class="form-label">Correct answer</label>
          <select class="form-select" name="tf_correct"><option value="true">True</option><option value="false">False</option></select></div>
        <button class="btn btn-outline-primary w-100">Add question</button>
      </form></div></div>
    <?php endif; ?>
    <?php if ($quizId): ?>
    <?php
      $s = $pdo->prepare("SELECT qa.*, u.name AS student_name FROM quiz_attempts qa
        JOIN students st ON st.id=qa.student_id JOIN users u ON u.id=st.user_id
        WHERE qa.quiz_id=? AND qa.tenant_id=? ORDER BY qa.submitted_at DESC LIMIT 15");
      $s->execute([$quizId, $tid]); $quizAttempts = $s->fetchAll();
    ?>
    <div class="card mt-3"><div class="card-header bg-white fw-bold">Recent attempts &amp; feedback</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($quizAttempts as $a): ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between"><strong><?= e($a['student_name']) ?></strong>
          <span class="badge bg-<?= (int)$a['passed'] ? 'success' : 'danger' ?>-subtle text-<?= (int)$a['passed'] ? 'success' : 'danger' ?>"><?= number_format((float)$a['percent'], 0) ?>%</span></div>
          <small class="text-muted"><?= e($a['submitted_at'] ?? '') ?></small>
          <form method="post" class="mt-2 d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="attempt_feedback"><input type="hidden" name="attempt_id" value="<?= (int)$a['id'] ?>">
            <input class="form-control form-control-sm" name="feedback" placeholder="Feedback for this attempt…" value="<?= e($a['feedback'] ?? '') ?>">
            <button class="btn btn-sm btn-outline-primary">Save</button></form>
        </li>
      <?php endforeach; ?>
      <?php if (!$quizAttempts): ?><li class="list-group-item text-muted">No attempts yet.</li><?php endif; ?>
      </ul></div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
