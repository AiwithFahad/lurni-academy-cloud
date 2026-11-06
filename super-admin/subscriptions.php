<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Subscriptions'; $active = 'subscriptions';

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = post('action');
    if ($id && $action === 'cancel') {
        $pdo->prepare("UPDATE subscriptions SET status='cancelled', cancelled_at=NOW() WHERE id=?")->execute([$id]);
        audit_log($pdo, 'subscription_cancelled', 'subscriptions', $id);
        flash('success', 'Subscription cancelled.');
    } elseif ($id && $action === 'reactivate') {
        // Reactivation extends the current period by the billing cycle from today.
        $pdo->prepare("UPDATE subscriptions SET status='active', starts_at=NOW(),
            current_period_end=DATE_ADD(NOW(), INTERVAL 1 MONTH), cancelled_at=NULL WHERE id=?")->execute([$id]);
        flash('success', 'Subscription reactivated for a new monthly period. Adjust the period end if a yearly cycle applies.');
    } elseif ($id && $action === 'expire') {
        $pdo->prepare("UPDATE subscriptions SET status='expired' WHERE id=?")->execute([$id]);
        flash('success', 'Subscription marked expired.');
    }
    redirect('/super-admin/subscriptions.php');
}

$status = getp('status');
$where = ['1=1']; $args = [];
if (in_array($status, ['trial', 'active', 'past_due', 'cancelled', 'expired'], true)) { $where[] = 's.status=?'; $args[] = $status; }
$s = $pdo->prepare('SELECT COUNT(*) FROM subscriptions s WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 12);
$s = $pdo->prepare("SELECT s.*, t.name AS tenant_name, p.name AS plan_name FROM subscriptions s
    JOIN tenants t ON t.id=s.tenant_id JOIN subscription_plans p ON p.id=s.plan_id
    WHERE " . implode(' AND ', $where) . " ORDER BY s.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="card">
  <div class="card-header bg-white">
    <div class="d-flex flex-wrap gap-2">
      <a class="filter-chip <?= $status === '' ? 'active' : '' ?>" href="?">All</a>
      <?php foreach (['trial', 'active', 'past_due', 'cancelled', 'expired'] as $st): ?><a class="filter-chip <?= $status === $st ? 'active' : '' ?>" href="?status=<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></a><?php endforeach; ?>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Tenant</th><th>Plan</th><th>Cycle</th><th>Amount</th><th>Status</th><th>Period ends</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr><td><strong><?= e($r['tenant_name']) ?></strong></td><td><?= e($r['plan_name']) ?></td>
      <td class="text-capitalize"><?= e($r['billing_cycle']) ?></td><td><?= money($r['amount']) ?></td>
      <td><span class="badge bg-<?= match($r['status']) { 'active' => 'success', 'trial' => 'info', 'past_due' => 'warning', default => 'secondary' } ?>-subtle text-<?= match($r['status']) { 'active' => 'success', 'trial' => 'info', 'past_due' => 'warning', default => 'secondary' } ?>"><?= e($r['status']) ?></span></td>
      <td><small><?= $r['current_period_end'] ? e(date('M j, Y', strtotime($r['current_period_end']))) : '—' ?></small></td>
      <td class="text-end"><form method="post" class="d-inline" onsubmit="return confirm('Apply this subscription change?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php if (in_array($r['status'], ['active', 'trial', 'past_due'], true)): ?><button class="btn btn-sm btn-outline-danger" name="action" value="cancel">Cancel</button> <button class="btn btn-sm btn-outline-secondary" name="action" value="expire">Expire</button><?php endif; ?>
        <?php if (in_array($r['status'], ['cancelled', 'expired'], true)): ?><button class="btn btn-sm btn-outline-success" name="action" value="reactivate">Reactivate</button><?php endif; ?>
      </form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No subscriptions found.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> subscriptions</small><?= pagination_html($pg, '?status=' . urlencode($status)) ?></div>
</div>
<p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Expiry is automatic: subscriptions past their period end + grace days are marked expired on the next tenant activity (see <code>refresh_subscription_status()</code>).</p>
<?php require __DIR__ . '/footer.php'; ?>
