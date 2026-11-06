<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['instructor']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Courses'; $active = 'courses';
require_permission($pdo, 'teaching.courses');

$s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $ins = $s->fetch();
if (!$ins) { http_response_code(403); exit('Instructor profile not found.'); }
$iid = (int)$ins['id'];
$thumbPresets = ['/assets/img/course-php.svg', '/assets/img/course-js.svg', '/assets/img/course-design.svg', '/assets/img/course-marketing.svg', '/assets/img/course-data.svg', '/assets/img/course-english.svg', '/assets/img/course-default.svg'];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id && ($err = limit_error($pdo, $tid, 'courses'))) { flash('error', $err); redirect('/instructor/courses.php'); }
        $thumb = post('thumbnail', '/assets/img/course-default.svg');
        if (!empty($_FILES['thumb_file']['name'])) {
            try { $up = handle_upload($_FILES['thumb_file'], 'thumbs', ['png', 'jpg', 'jpeg', 'svg', 'webp'], 3); if ($up) $thumb = '/' . $up; }
            catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect('/instructor/courses.php'); }
        }
        $visibility = in_array(post('visibility'), ['public', 'unlisted', 'private'], true) ? post('visibility') : 'public';
        $data = [$tid, $iid, (int)($_POST['category_id'] ?? 0) ?: null, post('title'), slugify(post('title')),
                 post('subtitle'), post('description'), $thumb,
                 in_array(post('level'), ['beginner', 'intermediate', 'advanced', 'all_levels'], true) ? post('level') : 'beginner',
                 (float)($_POST['price'] ?? 0), ($_POST['old_price'] ?? '') === '' ? null : (float)$_POST['old_price'],
                 post('language', 'English'), in_array(post('status'), ['draft', 'published', 'archived'], true) ? post('status') : 'draft',
                 isset($_POST['is_featured']) ? 1 : 0,
                 post('objectives'), post('requirements'), max(0, (int)($_POST['duration_minutes'] ?? 0)), $visibility];
        if ($id) {
            // Ownership check: instructors may only edit their own courses.
            $pdo->prepare('UPDATE courses SET category_id=?, title=?, subtitle=?, description=?, thumbnail=?, level=?, price=?, old_price=?, language=?, status=?, is_featured=?, objectives=?, requirements=?, duration_minutes=?, visibility=? WHERE id=? AND tenant_id=? AND instructor_id=?')
                ->execute([$data[2], $data[3], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $id, $tid, $iid]);
            flash('success', 'Course updated.');
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE tenant_id=? AND slug=?');
            $chk->execute([$tid, $data[4]]);
            if ((int)$chk->fetchColumn() > 0) $data[4] .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            // New instructor courses go through academy approval before they
            // appear in public listings (approval_status='pending').
            $pdo->prepare("INSERT INTO courses(tenant_id,instructor_id,category_id,title,slug,subtitle,description,thumbnail,level,price,old_price,language,status,is_featured,objectives,requirements,duration_minutes,visibility,approval_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')")
                ->execute($data);
            log_activity($pdo, 'Instructor course created: ' . post('title'));
            flash('success', 'Course created and submitted for academy approval. Add modules and lessons from the Lessons page.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM courses WHERE id=? AND tenant_id=? AND instructor_id=?')->execute([(int)($_POST['id'] ?? 0), $tid, $iid]);
        flash('success', 'Course deleted.');
    }
    redirect('/instructor/courses.php');
}

$s = $pdo->prepare("SELECT c.*, cat.name AS category_name,
    (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count,
    (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learner_count
    FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id
    WHERE c.tenant_id=? AND c.instructor_id=? ORDER BY c.id DESC");
$s->execute([$tid, $iid]); $rows = $s->fetchAll();
$categoryOpts = $pdo->prepare('SELECT id, name FROM categories WHERE tenant_id=? ORDER BY name');
$categoryOpts->execute([$tid]); $categoryOpts = $categoryOpts->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM courses WHERE id=? AND tenant_id=? AND instructor_id=?'); $s->execute([$eid, $tid, $iid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Course</th><th>Price</th><th>Lessons</th><th>Learners</th><th>Rating</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $c): ?>
    <tr><td><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="table-thumb" alt=""> <strong><?= e($c['title']) ?></strong><br><small class="text-muted"><?= e($c['category_name'] ?? 'Uncategorized') ?></small></td>
      <td><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?></td><td><?= (int)$c['lesson_count'] ?></td><td><?= (int)$c['learner_count'] ?></td>
      <td><span class="text-warning">★ <?= number_format((float)$c['rating'], 1) ?></span></td>
      <td><span class="badge bg-<?= $c['status'] === 'published' ? 'success' : 'secondary' ?>-subtle text-<?= $c['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($c['status']) ?></span>
        <span class="badge bg-<?= ($c['approval_status'] ?? 'approved') === 'approved' ? 'primary' : (($c['approval_status'] ?? '') === 'pending' ? 'warning text-dark' : 'danger') ?>-subtle text-<?= ($c['approval_status'] ?? 'approved') === 'approved' ? 'primary' : (($c['approval_status'] ?? '') === 'pending' ? 'warning-emphasis' : 'danger') ?>"><?= e($c['approval_status'] ?? 'approved') ?></span></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="/instructor/lessons.php?course_id=<?= (int)$c['id'] ?>">Content</a>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this course and its content?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No courses yet — create your first one.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit course' : 'Create course' ?></div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Subtitle</label><input class="form-control" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="">Uncategorized</option>
          <?php foreach ($categoryOpts as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($edit['category_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Level</label><select class="form-select" name="level"><?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'all_levels' => 'All levels'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['level'] ?? 'beginner') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-4 mb-2"><label class="form-label">Price $</label><input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= e($edit['price'] ?? '0') ?>"></div>
        <div class="col-4 mb-2"><label class="form-label">Old price $</label><input class="form-control" type="number" step="0.01" min="0" name="old_price" value="<?= e($edit['old_price'] ?? '') ?>"></div>
        <div class="col-4 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['draft', 'published', 'archived'] as $st): ?><option value="<?= $st ?>" <?= ($edit['status'] ?? 'draft') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-2"><label class="form-label">What students will learn (objectives)</label><textarea class="form-control" name="objectives" rows="2" placeholder="One objective per line"><?= e($edit['objectives'] ?? '') ?></textarea></div>
      <div class="mb-2"><label class="form-label">Requirements</label><textarea class="form-control" name="requirements" rows="2" placeholder="One requirement per line"><?= e($edit['requirements'] ?? '') ?></textarea></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Duration (min)</label><input class="form-control" type="number" min="0" name="duration_minutes" value="<?= e($edit['duration_minutes'] ?? '0') ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Visibility</label><select class="form-select" name="visibility"><?php foreach (['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['visibility'] ?? 'public') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-2"><label class="form-label">Thumbnail</label>
        <select class="form-select mb-1" name="thumbnail"><?php foreach ($thumbPresets as $tp): ?><option value="<?= $tp ?>" <?= ($edit['thumbnail'] ?? '') === $tp ? 'selected' : '' ?>><?= basename($tp) ?></option><?php endforeach; ?></select>
        <input class="form-control" type="file" name="thumb_file" accept=".png,.jpg,.jpeg,.svg,.webp"><div class="form-text">Upload your own thumbnail to override the preset.</div></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_featured" id="icf" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="icf">Request featured placement</label></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update course' : 'Create course' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/instructor/courses.php">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
