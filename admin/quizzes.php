<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Quizzes'; $active = 'quizzes';
require_permission($pdo, 'quizzes.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [$tid, (int)($_POST['course_id'] ?? 0), post('title'), post('description'),
                 max(1, min(100, (int)($_POST['pass_percent'] ?? 70))), (int)($_POST['time_limit_minutes'] ?? 0),
                 post('status') === 'draft' ? 'draft' : 'published', $me['id']];
        if ($id) {
            $pdo->prepare('UPDATE quizzes SET course_id=?, title=?, description=?, pass_percent=?, time_limit_minutes=?, status=? WHERE id=? AND tenant_id=?')
                ->execute([$data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $id, $tid]);
            flash('success', 'Quiz updated.');
        } else {
            $pdo->prepare('INSERT INTO quizzes(tenant_id,course_id,title,description,pass_percent,time_limit_minutes,status,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute($data);
            flash('success', 'Quiz created. Add questions from the Question Banks page.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM quizzes WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Quiz deleted (questions and attempts removed).');
    }
    redirect('/admin/quizzes.php');
}
$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();
$s = $pdo->prepare("SELECT qz.*, c.title AS course_title,
    (SELECT COUNT(*) FROM questions q WHERE q.quiz_id=qz.id) AS question_count,
    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id=qz.id) AS attempt_count,
    (SELECT COALESCE(AVG(a.percent),0) FROM quiz_attempts a WHERE a.quiz_id=qz.id) AS avg_percent
    FROM quizzes qz JOIN courses c ON c.id=qz.course_id WHERE qz.tenant_id=? ORDER BY qz.id DESC");
$s->execute([$tid]); $rows = $s->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM quizzes WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Quiz</th><th>Course</th><th>Questions</th><th>Attempts</th><th>Avg score</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr><td><strong><?= e($r['title']) ?></strong><br><small class="text-muted">Pass <?= (int)$r['pass_percent'] ?>%<?= (int)$r['time_limit_minutes'] ? ' · ' . (int)$r['time_limit_minutes'] . ' min limit' : '' ?></small></td>
      <td><small><?= e($r['course_title']) ?></small></td><td><?= (int)$r['question_count'] ?></td><td><?= (int)$r['attempt_count'] ?></td>
      <td><?= (int)$r['attempt_count'] ? number_format((float)$r['avg_percent'], 0) . '%' : '—' ?></td>
      <td><span class="badge bg-<?= $r['status'] === 'published' ? 'success' : 'secondary' ?>-subtle text-<?= $r['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="/admin/questions.php?quiz_id=<?= (int)$r['id'] ?>">Questions</a>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this quiz?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No quizzes yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit quiz' : 'Create quiz' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Course</label><select class="form-select" name="course_id" required>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['course_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Pass %</label><input class="form-control" type="number" min="1" max="100" name="pass_percent" value="<?= e($edit['pass_percent'] ?? '70') ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Time limit (min, 0=none)</label><input class="form-control" type="number" min="0" name="time_limit_minutes" value="<?= e($edit['time_limit_minutes'] ?? '0') ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="published" <?= ($edit['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= ($edit['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option></select></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update quiz' : 'Create quiz' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/admin/quizzes.php">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
