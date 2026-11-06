<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Activity & Audit Logs'; $active = 'logs';
$tab = getp('tab', 'activity') === 'audit' ? 'audit' : 'activity';
$activity = $pdo->query("SELECT a.*, t.name AS tenant_name, u.name AS user_name FROM activity_logs a
    LEFT JOIN tenants t ON t.id=a.tenant_id LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 60")->fetchAll();
$audit = $pdo->query("SELECT a.*, t.name AS tenant_name, u.name AS user_name FROM audit_logs a
    LEFT JOIN tenants t ON t.id=a.tenant_id LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 60")->fetchAll();
require __DIR__ . '/header.php';
?>
<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $tab === 'activity' ? 'active' : '' ?>" href="?tab=activity">Activity logs</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab === 'audit' ? 'active' : '' ?>" href="?tab=audit">Audit logs</a></li>
</ul>
<?php if ($tab === 'activity'): ?>
<div class="card"><ul class="list-group list-group-flush">
  <?php foreach ($activity as $a): ?>
  <li class="list-group-item d-flex justify-content-between gap-3"><span><i class="bi bi-activity text-primary me-2"></i><?= e($a['description']) ?> <small class="text-muted">— <?= e($a['tenant_name'] ?? 'Platform') ?><?= $a['user_name'] ? ' · ' . e($a['user_name']) : '' ?></small></span><small class="text-muted text-nowrap"><?= e(date('M j, H:i', strtotime($a['created_at']))) ?></small></li>
  <?php endforeach; ?>
  <?php if (!$activity): ?><li class="list-group-item text-muted">No activity recorded yet.</li><?php endif; ?>
</ul></div>
<?php else: ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Action</th><th>Entity</th><th>User</th><th>Tenant</th><th>IP</th><th>When</th></tr></thead><tbody>
  <?php foreach ($audit as $a): ?>
  <tr><td><code><?= e($a['action']) ?></code></td><td><?= e($a['entity'] ?? '') ?> <?= $a['entity_id'] ? '#' . (int)$a['entity_id'] : '' ?></td>
    <td><?= e($a['user_name'] ?? 'System') ?></td><td><?= e($a['tenant_name'] ?? 'Platform') ?></td><td><small><?= e($a['ip_address'] ?? '') ?></small></td>
    <td><small><?= e(date('M j, Y H:i', strtotime($a['created_at']))) ?></small></td></tr>
  <?php endforeach; ?>
  <?php if (!$audit): ?><tr><td colspan="6" class="text-center text-muted py-4">No audit entries yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
