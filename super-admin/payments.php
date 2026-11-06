<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Payments'; $active = 'payments';

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = post('action');
    $s = $pdo->prepare('SELECT * FROM payments WHERE id=?');
    $s->execute([$id]); $pay = $s->fetch();
    if ($pay && $action === 'verify' && $pay['status'] === 'pending') {
        // Verified confirmation received => only NOW may the payment count as paid.
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE payments SET status='completed', verified_by=?, verified_at=NOW() WHERE id=?")->execute([$me['id'], $id]);
        if ($pay['invoice_id']) {
            $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW(), payment_id=? WHERE id=?")->execute([$id, $pay['invoice_id']]);
        }
        if ($pay['subscription_id']) {
            // Activate / extend the subscription for its billing cycle.
            $sub = $pdo->prepare('SELECT billing_cycle FROM subscriptions WHERE id=?');
            $sub->execute([$pay['subscription_id']]); $cycle = $sub->fetchColumn() ?: 'monthly';
            if (!empty($pay['billing_cycle'])) $cycle = $pay['billing_cycle'];
            $interval = $cycle === 'yearly' ? '1 YEAR' : '1 MONTH';
            $pdo->prepare("UPDATE subscriptions SET status='active', starts_at=COALESCE(starts_at, NOW()),
                current_period_end=DATE_ADD(GREATEST(COALESCE(current_period_end, NOW()), NOW()), INTERVAL $interval), trial_ends_at=NULL
                WHERE id=?")->execute([$pay['subscription_id']]);
            // Plan change requested with this payment (upgrade/downgrade) applies on verification.
            if (!empty($pay['plan_id'])) {
                $pdo->prepare('UPDATE subscriptions SET plan_id=?, billing_cycle=?, amount=? WHERE id=?')
                    ->execute([$pay['plan_id'], $cycle, (float)$pay['amount'] + (float)$pay['discount_amount'], $pay['subscription_id']]);
            }
        }
        if ($pay['coupon_code']) {
            $c = $pdo->prepare('SELECT id FROM coupons WHERE code=?');
            $c->execute([$pay['coupon_code']]); $cid = $c->fetchColumn();
            if ($cid) {
                $pdo->prepare('UPDATE coupons SET times_used=times_used+1 WHERE id=?')->execute([$cid]);
                $pdo->prepare('INSERT INTO coupon_redemptions(coupon_id,tenant_id,payment_id,created_at) VALUES(?,?,?,NOW())')->execute([$cid, $pay['tenant_id'], $id]);
            }
        }
        $pdo->commit();
        audit_log($pdo, 'payment_verified', 'payments', $id);
        log_activity($pdo, 'Payment #' . $id . ' verified (' . money($pay['amount']) . ') for tenant #' . $pay['tenant_id']);
        notify_user($pdo, (int)$pay['tenant_id'], null, 'Payment verified', 'Your payment of ' . money($pay['amount']) . ' has been verified and your subscription updated.', '/admin/subscription.php');
        flash('success', 'Payment verified and marked completed. Subscription and invoice updated.');
    } elseif ($pay && $action === 'fail' && $pay['status'] === 'pending') {
        $pdo->prepare("UPDATE payments SET status='failed' WHERE id=?")->execute([$id]);
        flash('success', 'Payment marked failed.');
    } elseif ($pay && $action === 'refund' && $pay['status'] === 'completed') {
        $pdo->prepare("UPDATE payments SET status='refunded' WHERE id=?")->execute([$id]);
        audit_log($pdo, 'payment_refunded', 'payments', $id);
        flash('success', 'Payment marked refunded.');
    }
    redirect('/super-admin/payments.php');
}

$status = getp('status');
$where = ['1=1']; $args = [];
if (in_array($status, ['pending', 'completed', 'failed', 'refunded'], true)) { $where[] = 'pay.status=?'; $args[] = $status; }
$s = $pdo->prepare('SELECT COUNT(*) FROM payments pay WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 12);
$s = $pdo->prepare("SELECT pay.*, t.name AS tenant_name, u.name AS payer_name FROM payments pay
    JOIN tenants t ON t.id=pay.tenant_id LEFT JOIN users u ON u.id=pay.user_id
    WHERE " . implode(' AND ', $where) . " ORDER BY pay.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="alert alert-info"><i class="bi bi-shield-check me-1"></i><strong>Billing rule:</strong> payments recorded by tenants start as <em>pending</em>. They are only marked <em>completed</em> after verified payment confirmation here — never automatically.</div>
<div class="card">
  <div class="card-header bg-white"><div class="d-flex flex-wrap gap-2">
    <a class="filter-chip <?= $status === '' ? 'active' : '' ?>" href="?">All</a>
    <?php foreach (['pending', 'completed', 'failed', 'refunded'] as $st): ?><a class="filter-chip <?= $status === $st ? 'active' : '' ?>" href="?status=<?= $st ?>"><?= ucfirst($st) ?></a><?php endforeach; ?>
  </div></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><th>Tenant</th><th>Amount</th><th>Method / Ref</th><th>Coupon</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $p): ?>
    <tr><td><?= (int)$p['id'] ?></td>
      <td><strong><?= e($p['tenant_name']) ?></strong><br><small class="text-muted"><?= e($p['payer_name'] ?? '') ?></small></td>
      <td class="fw-semibold"><?= money($p['amount']) ?><?php if ((float)$p['discount_amount'] > 0): ?><br><small class="text-success">−<?= money($p['discount_amount']) ?> discount</small><?php endif; ?></td>
      <td class="text-capitalize"><?= e($p['method']) ?><br><small class="text-muted"><?= e($p['reference'] ?? '') ?></small></td>
      <td><?= $p['coupon_code'] ? '<code>' . e($p['coupon_code']) . '</code>' : '—' ?></td>
      <td><span class="badge bg-<?= match($p['status']) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>-subtle text-<?= match($p['status']) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>"><?= e($p['status']) ?></span>
        <?php if ($p['verified_at']): ?><br><small class="text-muted">verified <?= e(time_ago($p['verified_at'])) ?></small><?php endif; ?></td>
      <td><small><?= e(date('M j, Y', strtotime($p['created_at']))) ?></small></td>
      <td class="text-end"><form method="post" class="d-inline" onsubmit="return confirm('Apply this payment action?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <?php if ($p['status'] === 'pending'): ?><button class="btn btn-sm btn-success" name="action" value="verify"><i class="bi bi-check-lg"></i> Verify paid</button> <button class="btn btn-sm btn-outline-danger" name="action" value="fail">Mark failed</button><?php endif; ?>
        <?php if ($p['status'] === 'completed'): ?><button class="btn btn-sm btn-outline-secondary" name="action" value="refund">Refund</button><?php endif; ?>
      </form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No payments found.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> payments</small><?= pagination_html($pg, '?status=' . urlencode($status)) ?></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
