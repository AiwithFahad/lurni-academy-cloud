<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Courses'; $active = 'courses';
require_permission($pdo, 'courses.manage');

$thumbPresets = ['/assets/img/course-php.svg', '/assets/img/course-js.svg', '/assets/img/course-design.svg', '/assets/img/course-marketing.svg', '/assets/img/course-data.svg', '/assets/img/course-english.svg', '/assets/img/course-default.svg'];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id && ($err = limit_error($pdo, $tid, 'courses'))) { flash('error', $err); redirect('/admin/courses.php'); }
        $thumb = post('thumbnail', '/assets/img/course-default.svg');
        if (!empty($_FILES['thumb_file']['name'])) {
            try { $up = handle_upload($_FILES['thumb_file'], 'thumbs', ['png', 'jpg', 'jpeg', 'svg', 'webp'], 3); if ($up) $thumb = '/' . $up; }
            catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect('/admin/courses.php'); }
        }
        $slug = slugify(post('title'));
        $visibility = in_array(post('visibility'), ['public', 'unlisted', 'private'], true) ? post('visibility') : 'public';
        $approval = in_array(post('approval_status'), ['pending', 'approved', 'rejected'], true) ? post('approval_status') : 'approved';
        $data = [$tid, (int)($_POST['instructor_id'] ?? 0) ?: null, (int)($_POST['category_id'] ?? 0) ?: null,
                 post('title'), $slug, post('subtitle'), post('description'), $thumb,
                 in_array(post('level'), ['beginner', 'intermediate', 'advanced', 'all_levels'], true) ? post('level') : 'beginner',
                 (float)($_POST['price'] ?? 0), ($_POST['old_price'] ?? '') === '' ? null : (float)$_POST['old_price'],
                 post('language', 'English'), in_array(post('status'), ['draft', 'published', 'archived'], true) ? post('status') : 'draft',
                 isset($_POST['is_featured']) ? 1 : 0,
                 post('objectives'), post('requirements'), max(0, (int)($_POST['duration_minutes'] ?? 0)), $visibility, $approval];
        if ($id) {
            $pdo->prepare('UPDATE courses SET instructor_id=?, category_id=?, title=?, subtitle=?, description=?, thumbnail=?, level=?, price=?, old_price=?, language=?, status=?, is_featured=?, objectives=?, requirements=?, duration_minutes=?, visibility=?, approval_status=? WHERE id=? AND tenant_id=?')
                ->execute([$data[1], $data[2], $data[3], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13], $data[14], $data[15], $data[16], $data[17], $data[18], $id, $tid]);
            flash('success', 'Course updated.');
            audit_log($pdo, 'course_updated', 'courses', $id);
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE tenant_id=? AND slug=?');
            $chk->execute([$tid, $slug]);
            if ((int)$chk->fetchColumn() > 0) $data[4] = $slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            $pdo->prepare('INSERT INTO courses(tenant_id,instructor_id,category_id,title,slug,subtitle,description,thumbnail,level,price,old_price,language,status,is_featured,objectives,requirements,duration_minutes,visibility,approval_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute($data);
            $newId = (int)$pdo->lastInsertId();
            log_activity($pdo, 'Course created: ' . post('title'));
            audit_log($pdo, 'course_created', 'courses', $newId);
            flash('success', 'Course created. Add modules and lessons next.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM courses WHERE id=? AND tenant_id=?')->execute([$id, $tid]);
        audit_log($pdo, 'course_deleted', 'courses', $id);
        flash('success', 'Course deleted (modules, lessons, enrollments removed).');
    } elseif ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $st = in_array(post('status'), ['draft', 'published', 'archived'], true) ? post('status') : 'draft';
        $pdo->prepare('UPDATE courses SET status=? WHERE id=? AND tenant_id=?')->execute([$st, $id, $tid]);
        flash('success', 'Course status set to ' . $st . '.');
    } elseif ($action === 'approve' || $action === 'reject') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare('UPDATE courses SET approval_status=? WHERE id=? AND tenant_id=?')->execute([$newStatus, $id, $tid]);
        $s = $pdo->prepare('SELECT i.user_id, c.title FROM courses c LEFT JOIN instructors i ON i.id=c.instructor_id WHERE c.id=? AND c.tenant_id=?');
        $s->execute([$id, $tid]); $cRow = $s->fetch();
        if ($cRow && $cRow['user_id']) notify_user($pdo, $tid, (int)$cRow['user_id'], 'Course ' . $newStatus, 'Your course "' . $cRow['title'] . '" was ' . $newStatus . ' by the academy admin.', '/instructor/courses.php');
        audit_log($pdo, 'course_' . $newStatus, 'courses', $id);
        flash('success', 'Course ' . $newStatus . '.');
    }
    redirect('/admin/courses.php');
}

$q = getp('q'); $statusF = getp('status'); $approvalF = getp('approval');
$where = ['c.tenant_id=?']; $args = [$tid];
if ($q !== '') { $where[] = '(c.title LIKE ? OR c.subtitle LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%"]); }
if (in_array($statusF, ['draft', 'published', 'archived'], true)) { $where[] = 'c.status=?'; $args[] = $statusF; }
if (in_array($approvalF, ['pending', 'approved', 'rejected'], true)) { $where[] = 'c.approval_status=?'; $args[] = $approvalF; }
$s = $pdo->prepare('SELECT COUNT(*) FROM courses c WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 10);
$s = $pdo->prepare("SELECT c.*, cat.name AS category_name, u.name AS instructor_name,
    (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count,
    (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learner_count
    FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id
    LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
    WHERE " . implode(' AND ', $where) . " ORDER BY c.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();

$instructorOpts = $pdo->prepare('SELECT i.id, u.name FROM instructors i JOIN users u ON u.id=i.user_id WHERE i.tenant_id=? ORDER BY u.name');
$instructorOpts->execute([$tid]); $instructorOpts = $instructorOpts->fetchAll();
$categoryOpts = $pdo->prepare('SELECT id, name FROM categories WHERE tenant_id=? ORDER BY name');
$categoryOpts->execute([$tid]); $categoryOpts = $categoryOpts->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM courses WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="card-header bg-white"><form class="row g-2" method="get">
      <div class="col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search courses…"></div>
      <div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option><?php foreach (['draft', 'published', 'archived'] as $st): ?><option value="<?= $st ?>" <?= $statusF === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><select class="form-select" name="approval"><option value="">All approvals</option><?php foreach (['pending', 'approved', 'rejected'] as $st): ?><option value="<?= $st ?>" <?= $approvalF === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Course</th><th>Instructor</th><th>Price</th><th>Lessons</th><th>Learners</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $c): ?>
      <tr><td><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="table-thumb" alt=""> <strong><?= e($c['title']) ?></strong><br><small class="text-muted"><?= e($c['category_name'] ?? 'Uncategorized') ?><?= (int)$c['is_featured'] ? ' · ★ featured' : '' ?> · <?= e($c['visibility'] ?? 'public') ?></small>
        <?php if (($c['approval_status'] ?? 'approved') !== 'approved'): ?><span class="badge bg-<?= $c['approval_status'] === 'pending' ? 'warning text-dark' : 'danger' ?>-subtle text-<?= $c['approval_status'] === 'pending' ? 'warning-emphasis' : 'danger' ?>"><?= e($c['approval_status']) ?></span>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-success py-0">Approve</button></form>
        <?php if ($c['approval_status'] === 'pending'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger py-0">Reject</button></form><?php endif; ?><?php endif; ?></td>
        <td><small><?= e($c['instructor_name'] ?? '—') ?></small></td>
        <td><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?></td><td><?= (int)$c['lesson_count'] ?></td><td><?= (int)$c['learner_count'] ?></td>
        <td><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <select class="form-select form-select-sm status-select" name="status" onchange="this.form.submit()"><?php foreach (['draft', 'published', 'archived'] as $st): ?><option value="<?= $st ?>" <?= $c['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></form></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="/admin/lessons.php?course_id=<?= (int)$c['id'] ?>">Lessons</a>
          <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this course and its content?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No courses yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> courses</small><?= pagination_html($pg, '?' . http_build_query(['q' => $q, 'status' => $statusF, 'approval' => $approvalF])) ?></div>
  </div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit course' : 'Create course' ?></div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Subtitle</label><input class="form-control" name="subtitle" value="<?= e($edit['subtitle'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Instructor</label><select class="form-select" name="instructor_id"><option value="">Unassigned</option>
          <?php foreach ($instructorOpts as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($edit['instructor_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="">Uncategorized</option>
          <?php foreach ($categoryOpts as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($edit['category_id'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Level</label><select class="form-select" name="level"><?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'all_levels' => 'All levels'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['level'] ?? 'beginner') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['draft', 'published', 'archived'] as $st): ?><option value="<?= $st ?>" <?= ($edit['status'] ?? 'draft') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
        <div class="col-4 mb-2"><label class="form-label">Price $</label><input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= e($edit['price'] ?? '0') ?>"></div>
        <div class="col-4 mb-2"><label class="form-label">Old price $</label><input class="form-control" type="number" step="0.01" min="0" name="old_price" value="<?= e($edit['old_price'] ?? '') ?>"></div>
        <div class="col-4 mb-2"><label class="form-label">Language</label><input class="form-control" name="language" value="<?= e($edit['language'] ?? 'English') ?>"></div>
      </div>
      <div class="mb-2"><label class="form-label">What students will learn (objectives)</label><textarea class="form-control" name="objectives" rows="2" placeholder="One objective per line"><?= e($edit['objectives'] ?? '') ?></textarea></div>
      <div class="mb-2"><label class="form-label">Requirements</label><textarea class="form-control" name="requirements" rows="2" placeholder="One requirement per line"><?= e($edit['requirements'] ?? '') ?></textarea></div>
      <div class="row g-2">
        <div class="col-4 mb-2"><label class="form-label">Duration (min)</label><input class="form-control" type="number" min="0" name="duration_minutes" value="<?= e($edit['duration_minutes'] ?? '0') ?>"></div>
        <div class="col-4 mb-2"><label class="form-label">Visibility</label><select class="form-select" name="visibility"><?php foreach (['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['visibility'] ?? 'public') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-4 mb-2"><label class="form-label">Approval</label><select class="form-select" name="approval_status"><?php foreach (['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'] as $v => $l): ?><option value="<?= $v ?>" <?= ($edit['approval_status'] ?? 'approved') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-2"><label class="form-label">Thumbnail</label>
        <select class="form-select mb-1" name="thumbnail"><?php foreach ($thumbPresets as $tp): ?><option value="<?= $tp ?>" <?= ($edit['thumbnail'] ?? '') === $tp ? 'selected' : '' ?>><?= basename($tp) ?></option><?php endforeach; ?></select>
        <input class="form-control" type="file" name="thumb_file" accept=".png,.jpg,.jpeg,.svg,.webp"><div class="form-text">Upload to override the preset.</div></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_featured" id="cf" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="cf">Featured on homepage</label></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update course' : 'Create course' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/admin/courses.php">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
