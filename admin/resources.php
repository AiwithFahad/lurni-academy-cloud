<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Resources'; $active = 'resources';
require_permission($pdo, 'lessons.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'upload') {
        try {
            $path = handle_upload($_FILES['file'] ?? [], 'resources', ['pdf', 'zip', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'mp4', 'png', 'jpg'], MAX_UPLOAD_MB);
            if (!$path) throw new RuntimeException('Please choose a file.');
            $size = (int)($_FILES['file']['size'] ?? 0);
            // Storage plan limit check (after upload, using live byte totals).
            if ($err = limit_error($pdo, $tid, 'storage_mb')) { @unlink(dirname(__DIR__) . '/' . $path); throw new RuntimeException($err); }
            $pdo->prepare('INSERT INTO resources(tenant_id,course_id,lesson_id,title,file_path,file_type,file_size,uploaded_by) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$tid, (int)($_POST['course_id'] ?? 0) ?: null, (int)($_POST['lesson_id'] ?? 0) ?: null,
                           post('title') ?: basename((string)($_FILES['file']['name'] ?? 'file')), $path,
                           strtolower(pathinfo((string)($_FILES['file']['name'] ?? ''), PATHINFO_EXTENSION)), $size, $me['id']]);
            sync_subscription_usage($pdo, $tid);
            flash('success', 'Resource uploaded.');
        } catch (Throwable $ex) { flash('error', $ex->getMessage()); }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('SELECT file_path FROM resources WHERE id=? AND tenant_id=?');
        $s->execute([$id, $tid]); $path = $s->fetchColumn();
        if ($path) { @unlink(dirname(__DIR__) . '/' . $path); $pdo->prepare('DELETE FROM resources WHERE id=? AND tenant_id=?')->execute([$id, $tid]); sync_subscription_usage($pdo, $tid); flash('success', 'Resource deleted.'); }
    }
    redirect('/admin/resources.php');
}

$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();
$s = $pdo->prepare("SELECT r.*, c.title AS course_title, l.title AS lesson_title FROM resources r
    LEFT JOIN courses c ON c.id=r.course_id LEFT JOIN lessons l ON l.id=r.lesson_id
    WHERE r.tenant_id=? ORDER BY r.created_at DESC");
$s->execute([$tid]); $rows = $s->fetchAll();
$usedMb = usage_count($pdo, $tid, 'storage_mb');
$sub = current_subscription($pdo, $tid);
require __DIR__ . '/../includes/header.php';
?>
<div class="alert alert-light border"><i class="bi bi-hdd me-1"></i>Storage used: <strong><?= number_format($usedMb) ?> MB</strong>
<?php if ($sub): ?> of <?= (int)$sub['storage_mb'] < 0 ? 'unlimited' : number_format((int)$sub['storage_mb']) . ' MB' ?> on the <?= e($sub['plan_name']) ?> plan.<?php endif; ?></div>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Resource</th><th>Course / Lesson</th><th>Type</th><th>Size</th><th>Uploaded</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr><td><i class="bi bi-file-earmark-text text-primary me-1"></i><strong><?= e($r['title']) ?></strong></td>
      <td><small><?= e($r['course_title'] ?? '—') ?><?= $r['lesson_title'] ? '<br>' . e($r['lesson_title']) : '' ?></small></td>
      <td><span class="badge bg-secondary-subtle text-secondary text-uppercase"><?= e($r['file_type'] ?? '') ?></span></td>
      <td><small><?= number_format(((int)$r['file_size']) / 1048576, 2) ?> MB</small></td>
      <td><small><?= e(time_ago($r['created_at'])) ?></small></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/<?= e($r['file_path']) ?>" download><i class="bi bi-download"></i></a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this resource file?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No resources uploaded yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold">Upload resource</div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" placeholder="Defaults to file name"></div>
      <div class="mb-2"><label class="form-label">Course (optional)</label><select class="form-select" name="course_id"><option value="">—</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">File</label><input class="form-control" type="file" name="file" required>
      <div class="form-text">PDF, ZIP, Office docs, MP4 or images · max <?= MAX_UPLOAD_MB ?>MB · stored under a randomized name.</div></div>
      <button class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i>Upload</button>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
