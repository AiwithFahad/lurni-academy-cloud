<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['instructor']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Lessons & Modules'; $active = 'lessons';
require_permission($pdo, 'teaching.courses');

$s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $ins = $s->fetch();
if (!$ins) { http_response_code(403); exit('Instructor profile not found.'); }
$iid = (int)$ins['id'];

$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? AND instructor_id=? ORDER BY title');
$courses->execute([$tid, $iid]); $courses = $courses->fetchAll();
$courseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

function own_course(PDO $pdo, int $tid, int $iid, int $cid): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE id=? AND tenant_id=? AND instructor_id=?');
    $s->execute([$cid, $tid, $iid]);
    return (int)$s->fetchColumn() > 0;
}

if (is_post()) {
    verify_csrf();
    $cid = (int)($_POST['course_id'] ?? 0);
    if (!own_course($pdo, $tid, $iid, $cid)) { flash('error', 'You can only edit content of your own courses.'); redirect('/instructor/lessons.php'); }
    $action = post('action');
    if ($action === 'module_save') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare('UPDATE course_modules SET title=?, sort_order=? WHERE id=? AND tenant_id=?')->execute([post('title'), (int)($_POST['sort_order'] ?? 0), $id, $tid]);
        else $pdo->prepare('INSERT INTO course_modules(tenant_id,course_id,title,sort_order) VALUES(?,?,?,?)')->execute([$tid, $cid, post('title'), (int)($_POST['sort_order'] ?? 0)]);
        flash('success', 'Module saved.');
    } elseif ($action === 'module_delete') {
        $pdo->prepare('DELETE FROM course_modules WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Module deleted. Lessons kept and unassigned.');
    } elseif ($action === 'lesson_save') {
        $id = (int)($_POST['id'] ?? 0);
        $type = in_array(post('type'), ['video', 'text', 'quiz', 'assignment', 'resource'], true) ? post('type') : 'video';
        $data = [$cid, (int)($_POST['module_id'] ?? 0) ?: null, post('title'), $type, post('video_url') ?: null, post('content'),
                 (int)($_POST['duration_minutes'] ?? 0), isset($_POST['is_preview']) ? 1 : 0, (int)($_POST['sort_order'] ?? 0)];
        if ($id) {
            $pdo->prepare('UPDATE lessons SET course_id=?, module_id=?, title=?, type=?, video_url=?, content=?, duration_minutes=?, is_preview=?, sort_order=? WHERE id=? AND tenant_id=?')
                ->execute([...$data, $id, $tid]);
            flash('success', 'Lesson updated.');
        } else {
            $pdo->prepare('INSERT INTO lessons(tenant_id,course_id,module_id,title,type,video_url,content,duration_minutes,is_preview,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([$tid, ...$data]);
            flash('success', 'Lesson added.');
        }
    } elseif ($action === 'lesson_delete') {
        $pdo->prepare('DELETE FROM lessons WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Lesson deleted.');
    } elseif ($action === 'resource_upload') {
        // Course materials: downloadable files attached to the course
        // (or to one of its lessons), shown to enrolled students.
        try {
            $path = handle_upload($_FILES['file'] ?? [], 'resources', ['pdf', 'zip', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'mp4', 'png', 'jpg'], MAX_UPLOAD_MB);
            if (!$path) throw new RuntimeException('Please choose a file.');
            if ($err = limit_error($pdo, $tid, 'storage_mb')) { @unlink(dirname(__DIR__) . '/' . $path); throw new RuntimeException($err); }
            $size = (int)($_FILES['file']['size'] ?? 0);
            $ext = strtolower(pathinfo((string)($_FILES['file']['name'] ?? ''), PATHINFO_EXTENSION));
            $pdo->prepare('INSERT INTO resources(tenant_id,course_id,lesson_id,title,file_path,file_type,file_size,uploaded_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$tid, $cid, (int)($_POST['lesson_id'] ?? 0) ?: null, post('title') ?: (string)($_FILES['file']['name'] ?? 'Resource'), $path, $ext, $size, $me['id']]);
            flash('success', 'Material uploaded.');
        } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
    } elseif ($action === 'resource_delete') {
        $rid = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('SELECT file_path FROM resources WHERE id=? AND tenant_id=? AND course_id=?');
        $s->execute([$rid, $tid, $cid]); $fp = $s->fetchColumn();
        if ($fp) {
            $pdo->prepare('DELETE FROM resources WHERE id=? AND tenant_id=?')->execute([$rid, $tid]);
            $abs = __DIR__ . '/../' . ltrim((string)$fp, '/');
            if (is_file($abs)) @unlink($abs);
            flash('success', 'Material deleted.');
        }
    }
    redirect('/instructor/lessons.php?course_id=' . $cid);
}

$modules = []; $lessons = []; $courseResources = [];
if ($courseId && own_course($pdo, $tid, $iid, $courseId)) {
    $s = $pdo->prepare('SELECT * FROM course_modules WHERE tenant_id=? AND course_id=? ORDER BY sort_order, id');
    $s->execute([$tid, $courseId]); $modules = $s->fetchAll();
    $s = $pdo->prepare('SELECT l.*, m.title AS module_title FROM lessons l LEFT JOIN course_modules m ON m.id=l.module_id WHERE l.tenant_id=? AND l.course_id=? ORDER BY m.sort_order, l.sort_order, l.id');
    $s->execute([$tid, $courseId]); $lessons = $s->fetchAll();
    $s = $pdo->prepare('SELECT * FROM resources WHERE tenant_id=? AND course_id=? ORDER BY created_at DESC');
    $s->execute([$tid, $courseId]); $courseResources = $s->fetchAll();
}
$editLesson = null; $editModule = null;
if ($eid = (int)($_GET['edit_lesson'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM lessons WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $editLesson = $s->fetch() ?: null; }
if ($eid = (int)($_GET['edit_module'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM course_modules WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $editModule = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-12"><label class="form-label">Course</label>
      <select class="form-select" name="course_id" onchange="this.form.submit()">
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
      </select></div>
  </form>
</div></div>
<?php if (!$courses): ?><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">Create a course first, then add its modules and lessons here.</p></div>
<?php else: ?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3"><div class="card-header bg-white fw-bold">Modules</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($modules as $m): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><span class="badge bg-primary-subtle text-primary me-2">#<?= (int)$m['sort_order'] ?></span><?= e($m['title']) ?></span>
        <span><a class="btn btn-sm btn-outline-primary" href="?course_id=<?= $courseId ?>&edit_module=<?= (int)$m['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete module?')"><?= csrf_field() ?><input type="hidden" name="action" value="module_delete"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></span></li>
      <?php endforeach; ?>
      <?php if (!$modules): ?><li class="list-group-item text-muted">No modules yet.</li><?php endif; ?>
      </ul></div>
    <div class="card"><div class="card-header bg-white fw-bold">Lessons</div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Lesson</th><th>Module</th><th>Type</th><th>Duration</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php foreach ($lessons as $l): ?>
        <tr><td><?= (int)$l['sort_order'] ?></td><td><strong><?= e($l['title']) ?></strong><?= (int)$l['is_preview'] ? ' <span class="badge bg-success-subtle text-success">preview</span>' : '' ?></td>
          <td><small><?= e($l['module_title'] ?? '—') ?></small></td><td><span class="badge bg-primary-subtle text-primary text-capitalize"><?= e($l['type']) ?></span></td>
          <td><?= (int)$l['duration_minutes'] ?> min</td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?course_id=<?= $courseId ?>&edit_lesson=<?= (int)$l['id'] ?>">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete lesson?')"><?= csrf_field() ?><input type="hidden" name="action" value="lesson_delete"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$lessons): ?><tr><td colspan="6" class="text-center text-muted py-4">No lessons yet for this course.</td></tr><?php endif; ?>
        </tbody></table></div></div>
  </div>
  <div class="col-xl-5">
    <div class="card mb-3"><div class="card-header bg-white fw-bold"><?= $editModule ? 'Edit module' : 'Add module' ?></div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="module_save"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)($editModule['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($editModule['title'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= e($editModule['sort_order'] ?? '0') ?>"></div>
        <button class="btn btn-outline-primary w-100"><?= $editModule ? 'Update module' : 'Add module' ?></button>
      </form></div></div>
    <div class="card"><div class="card-header bg-white fw-bold"><?= $editLesson ? 'Edit lesson' : 'Add lesson' ?></div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="lesson_save"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)($editLesson['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($editLesson['title'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Module</label><select class="form-select" name="module_id"><option value="">No module</option>
            <?php foreach ($modules as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($editLesson['module_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select" name="type"><?php foreach (['video', 'text', 'quiz', 'assignment', 'resource'] as $tp): ?><option value="<?= $tp ?>" <?= ($editLesson['type'] ?? 'video') === $tp ? 'selected' : '' ?>><?= ucfirst($tp) ?></option><?php endforeach; ?></select></div>
          <div class="col-6 mb-2"><label class="form-label">Duration (min)</label><input class="form-control" type="number" min="0" name="duration_minutes" value="<?= e($editLesson['duration_minutes'] ?? '10') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= e($editLesson['sort_order'] ?? '0') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Video URL (embed)</label><input class="form-control" name="video_url" value="<?= e($editLesson['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/embed/…"></div>
        <div class="mb-2"><label class="form-label">Content / notes</label><textarea class="form-control" name="content" rows="3"><?= e($editLesson['content'] ?? '') ?></textarea></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_preview" id="ilp" <?= !empty($editLesson['is_preview']) ? 'checked' : '' ?>><label class="form-check-label" for="ilp">Free preview</label></div>
        <button class="btn btn-primary w-100"><?= $editLesson ? 'Update lesson' : 'Add lesson' ?></button>
        <?php if ($editLesson): ?><a class="btn btn-link w-100" href="?course_id=<?= $courseId ?>">Cancel</a><?php endif; ?>
      </form></div></div>
    <div class="card mt-3"><div class="card-header bg-white fw-bold">Course materials</div><div class="card-body">
      <ul class="list-group list-group-flush mb-3">
      <?php foreach ($courseResources as $r): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
          <span><i class="bi bi-file-earmark-arrow-down text-primary me-1"></i><?= e($r['title']) ?> <small class="text-muted">(<?= e($r['file_type'] ?? '') ?> · <?= number_format(((int)$r['file_size']) / 1048576, 2) ?> MB)</small></span>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this material?')"><?= csrf_field() ?><input type="hidden" name="action" value="resource_delete"><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></li>
      <?php endforeach; ?>
      <?php if (!$courseResources): ?><li class="list-group-item text-muted px-0">No downloadable materials yet.</li><?php endif; ?>
      </ul>
      <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="resource_upload"><input type="hidden" name="course_id" value="<?= $courseId ?>">
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" placeholder="e.g. Week 1 slides (PDF)"></div>
        <div class="mb-2"><label class="form-label">Attach to lesson (optional)</label><select class="form-select" name="lesson_id"><option value="">Whole course</option>
          <?php foreach ($lessons as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['title']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">File</label><input class="form-control" type="file" name="file" required></div>
        <button class="btn btn-outline-primary w-100"><i class="bi bi-upload"></i> Upload material</button>
      </form></div></div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
