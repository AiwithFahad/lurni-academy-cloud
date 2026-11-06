<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Platform Courses'; $active = 'courses';

// Platform-wide course oversight: approve / reject courses across all
// institutions and control visibility. Public listings only show courses
// with approval_status='approved'.
if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'approve' || $action === 'reject') {
        $pdo->prepare('UPDATE courses SET approval_status=? WHERE id=?')->execute([$action === 'approve' ? 'approved' : 'rejected', $id]);
        audit_log($pdo, 'course_' . $action, 'courses', $id);
        flash('success', 'Course ' . ($action === 'approve' ? 'approved' : 'rejected') . '.');
    }
    redirect('/super-admin/courses.php');
}

$tenantF = (int)($_GET['tenant'] ?? 0);
$approvalF = getp('approval');
$where = ['1=1']; $args = [];
if ($tenantF) { $where[] = 'c.tenant_id=?'; $args[] = $tenantF; }
if (in_array($approvalF, ['pending', 'approved', 'rejected'], true)) { $where[] = 'c.approval_status=?'; $args[] = $approvalF; }
$s = $pdo->prepare("SELECT c.*, t.name AS tenant_name, u.name AS instructor_name,
    (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners
    FROM courses c JOIN tenants t ON t.id=c.tenant_id
    LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
    WHERE " . implode(' AND ', $where) . " ORDER BY (c.approval_status='pending') DESC, c.id DESC LIMIT 200");
$s->execute($args); $rows = $s->fetchAll();
$tenants = $pdo->query('SELECT id, name FROM tenants ORDER BY name')->fetchAll();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE approval_status='pending'")->fetchColumn();
require __DIR__ . '/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <div><h2 class="h4 fw-bold mb-0">Platform courses</h2>
  <p class="text-muted mb-0"><?= count($rows) ?> courses · <?= $pendingCount ?> awaiting approval</p></div>
  <form class="d-flex gap-2" method="get">
    <select class="form-select" name="tenant" onchange="this.form.submit()"><option value="0">All institutions</option>
      <?php foreach ($tenants as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $tenantF === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="approval" onchange="this.form.submit()"><option value="">All approvals</option>
      <?php foreach (['pending', 'approved', 'rejected'] as $st): ?><option value="<?= $st ?>" <?= $approvalF === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select>
  </form>
</div>

<div class="card mb-3"><div class="card-body py-2">
  <input class="form-control" type="search" placeholder="Live search courses, institutions, instructors…" data-live-search="#platformCourses">
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0" id="platformCourses">
  <thead><tr><th>Course</th><th>Institution</th><th>Instructor</th><th>Price</th><th>Learners</th><th>Status</th><th>Approval</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $c): ?>
  <tr>
    <td><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="table-thumb" alt=""> <strong><?= e($c['title']) ?></strong><br><small class="text-muted"><?= e($c['visibility']) ?> · <?= e($c['level']) ?></small></td>
    <td><small><?= e($c['tenant_name']) ?></small></td>
    <td><small><?= e($c['instructor_name'] ?? '—') ?></small></td>
    <td><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?></td>
    <td><?= (int)$c['learners'] ?></td>
    <td><span class="badge bg-<?= $c['status'] === 'published' ? 'success' : 'secondary' ?>-subtle text-<?= $c['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($c['status']) ?></span></td>
    <td><span class="badge bg-<?= $c['approval_status'] === 'approved' ? 'primary' : ($c['approval_status'] === 'pending' ? 'warning text-dark' : 'danger') ?>-subtle text-<?= $c['approval_status'] === 'approved' ? 'primary' : ($c['approval_status'] === 'pending' ? 'warning-emphasis' : 'danger') ?>"><?= e($c['approval_status']) ?></span></td>
    <td class="text-end text-nowrap">
      <?php if ($c['approval_status'] !== 'approved'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-success">Approve</button></form><?php endif; ?>
      <?php if ($c['approval_status'] === 'pending'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Reject</button></form><?php endif; ?>
    </td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No courses match.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/footer.php'; ?>
