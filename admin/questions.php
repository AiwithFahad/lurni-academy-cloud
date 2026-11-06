<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Question Banks'; $active = 'questions';
require_permission($pdo, 'quizzes.manage');

$quizzes = $pdo->prepare('SELECT qz.id, qz.title, c.title AS course_title FROM quizzes qz JOIN courses c ON c.id=qz.course_id WHERE qz.tenant_id=? ORDER BY qz.id DESC');
$quizzes->execute([$tid]); $quizzes = $quizzes->fetchAll();
$quizId = (int)($_GET['quiz_id'] ?? ($quizzes[0]['id'] ?? 0));

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $qid = (int)($_POST['quiz_id'] ?? 0);
    $own = $pdo->prepare('SELECT COUNT(*) FROM quizzes WHERE id=? AND tenant_id=?');
    $own->execute([$qid, $tid]);
    if ((int)$own->fetchColumn() === 0) { flash('error', 'Quiz not found in your academy.'); redirect('/admin/questions.php'); }
    if ($action === 'save') {
        $questionId = (int)($_POST['id'] ?? 0);
        $type = in_array(post('type'), ['single', 'multiple', 'true_false'], true) ? post('type') : 'single';
        if ($questionId) {
            $pdo->prepare('UPDATE questions SET question=?, type=?, points=?, sort_order=? WHERE id=? AND tenant_id=?')
                ->execute([post('question'), $type, max(1, (int)($_POST['points'] ?? 1)), (int)($_POST['sort_order'] ?? 0), $questionId, $tid]);
            $pdo->prepare('DELETE FROM answers WHERE question_id=?')->execute([$questionId]);
        } else {
            $pdo->prepare('INSERT INTO questions(tenant_id,quiz_id,question,type,points,sort_order) VALUES(?,?,?,?,?,?)')
                ->execute([$tid, $qid, post('question'), $type, max(1, (int)($_POST['points'] ?? 1)), (int)($_POST['sort_order'] ?? 0)]);
            $questionId = (int)$pdo->lastInsertId();
        }
        $correct = (int)($_POST['correct'] ?? 0);
        foreach (($_POST['options'] ?? []) as $i => $opt) {
            $opt = trim((string)$opt);
            if ($opt === '') continue;
            $pdo->prepare('INSERT INTO answers(question_id,answer_text,is_correct) VALUES(?,?,?)')
                ->execute([$questionId, $opt, $i === $correct ? 1 : 0]);
        }
        flash('success', 'Question saved to the bank.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM questions WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Question deleted.');
    }
    redirect('/admin/questions.php?quiz_id=' . $qid);
}

$rows = [];
if ($quizId) {
    $s = $pdo->prepare('SELECT * FROM questions WHERE tenant_id=? AND quiz_id=? ORDER BY sort_order, id');
    $s->execute([$tid, $quizId]); $rows = $s->fetchAll();
    foreach ($rows as &$r) {
        $a = $pdo->prepare('SELECT * FROM answers WHERE question_id=? ORDER BY id');
        $a->execute([$r['id']]); $r['options'] = $a->fetchAll();
    }
    unset($r);
}
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) {
    $s = $pdo->prepare('SELECT * FROM questions WHERE id=? AND tenant_id=?');
    $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null;
    if ($edit) { $a = $pdo->prepare('SELECT * FROM answers WHERE question_id=? ORDER BY id'); $a->execute([$eid]); $edit['options'] = $a->fetchAll(); }
}
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-10"><label class="form-label">Question bank (quiz)</label>
      <select class="form-select" name="quiz_id" onchange="this.form.submit()">
        <?php foreach ($quizzes as $qz): ?><option value="<?= (int)$qz['id'] ?>" <?= $quizId === (int)$qz['id'] ? 'selected' : '' ?>><?= e($qz['title']) ?> — <?= e($qz['course_title']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><span class="btn btn-light w-100 disabled"><?= count($rows) ?> questions</span></div>
  </form>
</div></div>
<div class="row g-3">
  <div class="col-xl-7">
    <?php foreach ($rows as $i => $r): ?>
    <div class="card mb-2"><div class="card-body py-3">
      <div class="d-flex justify-content-between gap-2">
        <strong>Q<?= $i + 1 ?>. <?= e($r['question']) ?></strong>
        <span class="text-nowrap"><span class="badge bg-primary-subtle text-primary"><?= (int)$r['points'] ?> pts</span>
        <a class="btn btn-sm btn-outline-primary" href="?quiz_id=<?= $quizId ?>&edit=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete question?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="quiz_id" value="<?= $quizId ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></span>
      </div>
      <ul class="mb-0 mt-2 small">
        <?php foreach ($r['options'] as $o): ?><li class="<?= (int)$o['is_correct'] ? 'text-success fw-semibold' : 'text-muted' ?>"><?= e($o['answer_text']) ?><?= (int)$o['is_correct'] ? ' ✓' : '' ?></li><?php endforeach; ?>
      </ul>
    </div></div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">This question bank is empty.</p></div><?php endif; ?>
  </div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit question' : 'Add question' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="quiz_id" value="<?= $quizId ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Question</label><textarea class="form-control" name="question" rows="2" required><?= e($edit['question'] ?? '') ?></textarea></div>
      <div class="row g-2">
        <div class="col-5 mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><?php foreach (['single' => 'Single choice', 'multiple' => 'Multiple choice', 'true_false' => 'True / False'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['type'] ?? 'single') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-3 mb-2"><label class="form-label">Points</label><input class="form-control" type="number" min="1" name="points" value="<?= e($edit['points'] ?? '1') ?>"></div>
        <div class="col-4 mb-2"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? '0') ?>"></div>
      </div>
      <label class="form-label">Answer options — select the correct one</label>
      <?php for ($i = 0; $i < 4; $i++): $o = $edit['options'][$i] ?? null; ?>
      <div class="input-group mb-2">
        <span class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct" value="<?= $i ?>" <?= $o && (int)$o['is_correct'] ? 'checked' : ($i === 0 && !$edit ? 'checked' : '') ?> aria-label="Mark option <?= $i + 1 ?> correct"></span>
        <input class="form-control" name="options[]" placeholder="Option <?= $i + 1 ?>" value="<?= e($o['answer_text'] ?? '') ?>">
      </div>
      <?php endfor; ?>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update question' : 'Add question' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="?quiz_id=<?= $quizId ?>">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
