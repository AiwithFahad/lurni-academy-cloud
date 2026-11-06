<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Tenant Management'; $active = 'tenants';

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = post('action');
    if ($id && in_array($action, ['suspend', 'reactivate', 'cancel'], true)) {
        $status = $action === 'suspend' ? 'suspended' : ($action === 'cancel' ? 'cancelled' : 'active');
        $pdo->prepare('UPDATE tenants SET status=? WHERE id=?')->execute([$status, $id]);
        audit_log($pdo, 'tenant_' . $action, 'tenants', $id);
        log_activity($pdo, 'Tenant #' . $id . ' ' . $action . 'd by Super Admin');
        flash('success', 'Tenant updated.');
    }
    redirect('/super-admin/tenants.php');
}

$q = getp('q'); $status = getp('status');
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = '(t.name LIKE ? OR t.slug LIKE ? OR t.email LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%", "%$q%"]); }
if (in_array($status, ['active', 'suspended', 'cancelled'], true)) { $where[] = 't.status=?'; $args[] = $status; }
$s = $pdo->prepare('SELECT COUNT(*) FROM tenants t WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 12);
$s = $pdo->prepare("SELECT t.*,
    (SELECT COUNT(*) FROM users u WHERE u.tenant_id=t.id) AS users_count,
    (SELECT COUNT(*) FROM students st WHERE st.tenant_id=t.id) AS students_count,
    (SELECT COUNT(*) FROM courses c WHERE c.tenant_id=t.id) AS courses_count,
    (SELECT p.name FROM subscriptions sub JOIN subscription_plans p ON p.id=sub.plan_id WHERE sub.tenant_id=t.id ORDER BY sub.id DESC LIMIT 1) AS plan_name,
    (SELECT sub.status FROM subscriptions sub WHERE sub.tenant_id=t.id ORDER BY sub.id DESC LIMIT 1) AS sub_status,
    (SELECT sub.current_period_end FROM subscriptions sub WHERE sub.tenant_id=t.id ORDER BY sub.id DESC LIMIT 1) AS period_end
    FROM tenants t WHERE " . implode(' AND ', $where) . " ORDER BY t.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();

if (getp('export') === 'csv') {
    $s = $pdo->prepare('SELECT id,name,slug,org_type,email,status,created_at FROM tenants t WHERE ' . implode(' AND ', $where));
    $s->execute($args);
    export_csv($s->fetchAll(), 'tenants.csv');
}
require __DIR__ . '/header.php';
?>
<div class="card">
  <div class="card-header bg-white">
    <form class="row g-2 align-items-center" method="get">
      <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name, slug or email…"></div>
      <div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option>
        <?php foreach (['active', 'suspended', 'cancelled'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
      <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="?export=csv&q=<?= e($q) ?>&status=<?= e($status) ?>"><i class="bi bi-download"></i> CSV</a></div>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Academy</th><th>Plan / Subscription</th><th>Users</th><th>Students</th><th>Courses</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
    <tr>
      <td><strong><?= e($t['name']) ?></strong><br><small class="text-muted"><?= e($t['slug']) ?> · <?= e($t['email'] ?? '') ?></small></td>
      <td><?= e($t['plan_name'] ?? '—') ?><br><small class="text-muted"><?= e($t['sub_status'] ?? 'no subscription') ?><?= $t['period_end'] ? ' · ends ' . e(date('M j, Y', strtotime($t['period_end']))) : '' ?></small></td>
      <td><?= (int)$t['users_count'] ?></td><td><?= (int)$t['students_count'] ?></td><td><?= (int)$t['courses_count'] ?></td>
      <td><span class="badge bg-<?= $t['status'] === 'active' ? 'success' : ($t['status'] === 'suspended' ? 'warning' : 'secondary') ?>-subtle text-<?= $t['status'] === 'active' ? 'success' : ($t['status'] === 'suspended' ? 'warning' : 'secondary') ?>"><?= e($t['status']) ?></span></td>
      <td class="text-end">
        <form method="post" class="d-inline" onsubmit="return confirm('Apply this change to the tenant?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <?php if ($t['status'] === 'active'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend">Suspend</button><?php endif; ?>
          <?php if ($t['status'] !== 'active'): ?><button class="btn btn-sm btn-outline-success" name="action" value="reactivate">Reactivate</button><?php endif; ?>
          <?php if ($t['status'] !== 'cancelled'): ?><button class="btn btn-sm btn-outline-danger" name="action" value="cancel">Cancel</button><?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No tenants match.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center"><small class="text-muted"><?= $total ?> tenants</small><?= pagination_html($pg, '?' . http_build_query(['q' => $q, 'status' => $status])) ?></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
