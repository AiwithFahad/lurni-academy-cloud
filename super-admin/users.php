<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'User Management'; $active = 'users';

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = post('action');
    if ($id && $id !== (int)$me['id']) {
        if ($action === 'suspend') $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$id]);
        if ($action === 'activate') $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
        audit_log($pdo, 'user_' . $action, 'users', $id);
        flash('success', 'User updated.');
    } else {
        flash('error', 'You cannot change your own account here.');
    }
    redirect('/super-admin/users.php');
}
$q = getp('q'); $role = getp('role');
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%"]); }
if (in_array($role, ['super_admin', 'admin', 'instructor', 'student'], true)) { $where[] = 'u.role=?'; $args[] = $role; }
$s = $pdo->prepare('SELECT COUNT(*) FROM users u WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 12);
$s = $pdo->prepare("SELECT u.*, t.name AS tenant_name FROM users u LEFT JOIN tenants t ON t.id=u.tenant_id
    WHERE " . implode(' AND ', $where) . " ORDER BY u.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="card">
  <div class="card-header bg-white"><form class="row g-2" method="get">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name or email…"></div>
    <div class="col-md-3"><select class="form-select" name="role"><option value="">All roles</option>
      <?php foreach (['super_admin', 'admin', 'instructor', 'student'] as $r): ?><option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $r)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    <div class="col-md-2"><span class="btn btn-light w-100 disabled"><?= $total ?> users</span></div>
  </form></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>User</th><th>Tenant</th><th>Role</th><th>Status</th><th>Verified</th><th>Last login</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $u): ?>
    <tr><td><span class="avatar-circle sm"><?= e(initials($u['name'])) ?></span> <strong><?= e($u['name']) ?></strong><br><small class="text-muted ms-1"><?= e($u['email']) ?></small></td>
      <td><?= e($u['tenant_name'] ?? 'Platform') ?></td>
      <td><span class="badge bg-primary-subtle text-primary text-capitalize"><?= e(str_replace('_', ' ', $u['role'])) ?></span></td>
      <td><span class="badge bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'pending' ? 'warning' : 'danger') ?>-subtle text-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e($u['status']) ?></span></td>
      <td><?= $u['email_verified_at'] ? '<i class="bi bi-patch-check text-success"></i>' : '<span class="text-muted">No</span>' ?></td>
      <td><small><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : 'Never' ?></small></td>
      <td class="text-end"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <?php if ($u['status'] === 'active'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend">Suspend</button><?php else: ?><button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button><?php endif; ?>
      </form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No users match.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card-footer bg-white"><?= pagination_html($pg, '?' . http_build_query(['q' => $q, 'role' => $role])) ?></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
