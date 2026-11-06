<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Lessons'; $active = 'lessons';
require_permission($pdo, 'lessons.manage');

$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();
$courseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

function course_owned(PDO $pdo, int $tid, int $cid): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE id=? AND tenant_id=?');
    $s->execute([$cid, $tid]);
    return (int)$s->fetchColumn() > 0;
}

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $cid = (int)($_POST['course_id'] ?? 0);
    if (!course_owned($pdo, $tid, $cid)) { flash('error', 'Course not found in your academy.'); redirect('/admin/lessons.php'); }
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $type = in_array(post('type'), ['video', 'text', 'quiz', 'assignment', 'resource'], true) ? post('type') : 'video';
        $data = [$tid, $cid, (int)($_POST['module_id'] ?? 0) ?: null, post('title'), $type, post('video_url') ?: null,
                 post('content'), (int)($_POST['duration_minutes'] ?? 0), isset($_POST['is_preview']) ? 1 : 0, (int)($_POST['sort_order'] ?? 0)];
        if ($id) {
            $pdo->prepare('UPDATE lessons SET course_id=?, module_id=?, title=?, type=?, video_url=?, content=?, duration_minutes=?, is_preview=?, sort_order=? WHERE id=? AND tenant_id=?')
                ->execute([$cid, $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $id, $tid]);
            flash('success', 'Lesson updated.');
        } else {
            $pdo->prepare('INSERT INTO lessons(tenant_id,course_id,module_id,title,type,video_url,content,duration_minutes,is_preview,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute($data);
            flash('success', 'Lesson added.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM lessons WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Lesson deleted.');
    }
    redirect('/admin/lessons.php?course_id=' . $cid);
}

$modules = [];
if ($courseId) {
    $s = $pdo->prepare('SELECT id, title FROM course_modules WHERE tenant_id=? AND course_id=? ORDER BY sort_order');
    $s->execute([$tid, $courseId]); $modules = $s->fetchAll();
}
$s = $pdo->prepare("SELECT l.*, m.title AS module_title FROM lessons l LEFT JOIN course_modules m ON m.id=l.module_id
    WHERE l.tenant_id=? AND l.course_id=? ORDER BY m.sort_order, l.sort_order, l.id");
$s->execute([$tid, $courseId]); $rows = $s->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM lessons WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-10"><label class="form-label">Course</label>
      <select class="form-select" name="course_id" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><span class="btn btn-light w-100 disabled"><?= count($rows) ?> lessons</span></div>
  </form>
</div></div>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><th>Lesson</th><th>Module</th><th>Type</th><th>Duration</th><th>Preview</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $l): ?>
    <tr><td><?= (int)$l['sort_order'] ?></td>
      <td><strong><?= e($l['title']) ?></strong><?php if ($l['video_url']): ?><br><small class="text-muted"><i class="bi bi-youtube text-danger"></i> video attached</small><?php endif; ?></td>
      <td><small><?= e($l['module_title'] ?? '—') ?></small></td>
      <td><span class="badge bg-primary-subtle text-primary text-capitalize"><?= e($l['type']) ?></span></td>
      <td><?= (int)$l['duration_minutes'] ?> min</td>
      <td><?= (int)$l['is_preview'] ? '<span class="badge bg-success-subtle text-success">Free</span>' : '<span class="text-muted">—</span>' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?course_id=<?= $courseId ?>&edit=<?= (int)$l['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this lesson?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No lessons for this course yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit lesson' : 'Add lesson' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Module</label><select class="form-select" name="module_id"><option value="">No module</option>
          <?php foreach ($modules as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($edit['module_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><?php foreach (['video', 'text', 'quiz', 'assignment', 'resource'] as $tp): ?><option value="<?= $tp ?>" <?= ($edit['type'] ?? 'video') === $tp ? 'selected' : '' ?>><?= ucfirst($tp) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Duration (min)</label><input class="form-control" type="number" min="0" name="duration_minutes" value="<?= e($edit['duration_minutes'] ?? '10') ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? '0') ?>"></div>
      </div>
      <div class="mb-2"><label class="form-label">Video URL (YouTube/Vimeo embed)</label><input class="form-control" name="video_url" value="<?= e($edit['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/embed/…"></div>
      <div class="mb-2"><label class="form-label">Content / notes</label><textarea class="form-control" name="content" rows="3"><?= e($edit['content'] ?? '') ?></textarea></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_preview" id="lp" <?= !empty($edit['is_preview']) ? 'checked' : '' ?>><label class="form-check-label" for="lp">Free preview lesson</label></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update lesson' : 'Add lesson' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="?course_id=<?= $courseId ?>">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
