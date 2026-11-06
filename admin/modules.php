<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Course Modules'; $active = 'modules';
require_permission($pdo, 'courses.manage');

$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();
$courseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $cid = (int)($_POST['course_id'] ?? 0);
    // Tenant-aware guard: the course must belong to this academy.
    $own = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE id=? AND tenant_id=?');
    $own->execute([$cid, $tid]);
    if ((int)$own->fetchColumn() === 0) { flash('error', 'Course not found in your academy.'); redirect('/admin/modules.php'); }
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE course_modules SET title=?, description=?, sort_order=? WHERE id=? AND tenant_id=?')
                ->execute([post('title'), post('description'), (int)($_POST['sort_order'] ?? 0), $id, $tid]);
            flash('success', 'Module updated.');
        } else {
            $pdo->prepare('INSERT INTO course_modules(tenant_id,course_id,title,description,sort_order) VALUES(?,?,?,?,?)')
                ->execute([$tid, $cid, post('title'), post('description'), (int)($_POST['sort_order'] ?? 0)]);
            flash('success', 'Module added.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM course_modules WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Module deleted. Its lessons were kept and unassigned from the module.');
    }
    redirect('/admin/modules.php?course_id=' . $cid);
}

$rows = [];
if ($courseId) {
    $s = $pdo->prepare("SELECT m.*, (SELECT COUNT(*) FROM lessons l WHERE l.module_id=m.id) AS lesson_count
        FROM course_modules m WHERE m.tenant_id=? AND m.course_id=? ORDER BY m.sort_order, m.id");
    $s->execute([$tid, $courseId]); $rows = $s->fetchAll();
}
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM course_modules WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-8"><label class="form-label">Course</label>
      <select class="form-select" name="course_id" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><a class="btn btn-outline-primary w-100" href="/admin/lessons.php?course_id=<?= $courseId ?>"><i class="bi bi-play-btn me-1"></i>Manage lessons of this course</a></div>
  </form>
</div></div>
<div class="row g-3">
  <div class="col-xl-8">
    <?php foreach ($rows as $m): ?>
    <div class="card mb-2"><div class="card-body d-flex justify-content-between align-items-center py-3">
      <div><span class="badge bg-primary-subtle text-primary me-2">#<?= (int)$m['sort_order'] ?></span><strong><?= e($m['title']) ?></strong>
      <small class="text-muted d-block"><?= e($m['description'] ?? '') ?> · <?= (int)$m['lesson_count'] ?> lessons</small></div>
      <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="?course_id=<?= $courseId ?>&edit=<?= (int)$m['id'] ?>">Edit</a>
      <form method="post" onsubmit="return confirm('Delete this module?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div>
    </div></div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">No modules for this course yet.</p></div><?php endif; ?>
  </div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit module' : 'Add module' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? '0') ?>"></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update module' : 'Add module' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="?course_id=<?= $courseId ?>">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
