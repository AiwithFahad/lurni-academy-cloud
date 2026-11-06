<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Subscription & Billing'; $active = 'subscription';
require_permission($pdo, 'subscription.manage');

$sub = current_subscription($pdo, $tid);
sync_subscription_usage($pdo, $tid);

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'choose_plan' && $sub) {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $cycle = post('billing_cycle') === 'yearly' ? 'yearly' : 'monthly';
        $s = $pdo->prepare('SELECT * FROM subscription_plans WHERE id=? AND is_active=1');
        $s->execute([$planId]); $plan = $s->fetch();
        if ($plan) {
            $price = $cycle === 'yearly' ? (float)$plan['price_yearly'] : (float)$plan['price_monthly'];
            $discount = 0.0; $couponCode = null;
            $couponInput = strtoupper(post('coupon'));
            if ($couponInput !== '' && $price > 0) {
                $c = $pdo->prepare('SELECT * FROM coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at > NOW()) AND (max_uses=0 OR times_used < max_uses) AND min_amount <= ?');
                $c->execute([$couponInput, $price]); $coupon = $c->fetch();
                if ($coupon) {
                    $discount = $coupon['discount_type'] === 'percent' ? round($price * (float)$coupon['discount_value'] / 100, 2) : min($price, (float)$coupon['discount_value']);
                    $couponCode = $coupon['code'];
                } else {
                    flash('error', 'Coupon "' . $couponInput . '" is not valid for this plan.'); redirect('/admin/subscription.php');
                }
            }
            $payable = max(0, $price - $discount);
            if ($payable <= 0) {
                // Free plan (or fully discounted): switch immediately, no payment needed.
                $pdo->prepare('UPDATE subscriptions SET plan_id=?, billing_cycle=?, amount=0, status="active", starts_at=NOW(), trial_ends_at=NULL,
                    current_period_end=DATE_ADD(NOW(), INTERVAL 1 ' . ($cycle === 'yearly' ? 'YEAR' : 'MONTH') . ') WHERE id=?')
                    ->execute([$planId, $cycle, $sub['id']]);
                create_invoice($pdo, $tid, (int)$sub['id'], null, 0, $plan['name'] . ' plan (' . $cycle . ') — free');
                log_activity($pdo, 'Switched to the ' . $plan['name'] . ' plan (' . $cycle . ')');
                flash('success', 'You are now on the ' . $plan['name'] . ' plan.');
            } else {
                // Paid plan: record the payment as PENDING. It only takes effect after
                // verified confirmation in the Super Admin payments queue.
                $pdo->beginTransaction();
                $invId = create_invoice($pdo, $tid, (int)$sub['id'], null, $payable, $plan['name'] . ' plan (' . $cycle . ')' . ($couponCode ? ' — coupon ' . $couponCode : ''));
                $pdo->prepare('INSERT INTO payments(tenant_id,subscription_id,plan_id,billing_cycle,invoice_id,user_id,amount,discount_amount,coupon_code,method,reference,status,created_at)
                               VALUES(?,?,?,?,?,?,?,?,?,?,"manual",?,"pending",NOW())')
                    ->execute([$tid, $sub['id'], $planId, $cycle, $invId, $me['id'], $payable, $discount, $couponCode, post('reference') ?: 'Awaiting transfer']);
                $payId = (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE invoices SET payment_id=? WHERE id=?')->execute([$payId, $invId]);
                $pdo->commit();
                audit_log($pdo, 'plan_change_requested', 'payments', $payId);
                flash('success', 'Plan change recorded as a pending payment of ' . money($payable) . '. Your ' . $plan['name'] . ' plan activates as soon as the payment is verified by the platform team.');
            }
        }
    } elseif ($action === 'record_payment' && $sub) {
        // Manual payment recording against the current plan (renewals).
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount > 0) {
            $invId = create_invoice($pdo, $tid, (int)$sub['id'], null, $amount, $sub['plan_name'] . ' plan renewal (' . $sub['billing_cycle'] . ')');
            $pdo->prepare('INSERT INTO payments(tenant_id,subscription_id,plan_id,billing_cycle,invoice_id,user_id,amount,method,reference,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,"pending",NOW())')
                ->execute([$tid, $sub['id'], $sub['plan_id'], $sub['billing_cycle'], $invId, $me['id'], $amount, post('method', 'manual'), post('reference')]);
            $payId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE invoices SET payment_id=? WHERE id=?')->execute([$payId, $invId]);
            flash('success', 'Payment of ' . money($amount) . ' recorded as pending verification.');
        }
    } elseif ($action === 'cancel' && $sub) {
        $pdo->prepare("UPDATE subscriptions SET status='cancelled', cancelled_at=NOW() WHERE id=?")->execute([$sub['id']]);
        audit_log($pdo, 'subscription_cancelled', 'subscriptions', (int)$sub['id']);
        flash('success', 'Subscription cancelled. Access continues until the end of the current period plus the grace period.');
    } elseif ($action === 'renew' && $sub) {
        $pdo->prepare("UPDATE subscriptions SET status='active', cancelled_at=NULL WHERE id=?")->execute([$sub['id']]);
        flash('success', 'Subscription reactivated. Record a payment below to extend the paid period.');
    }
    redirect('/admin/subscription.php');
}

$plans = $pdo->query('SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY sort_order')->fetchAll();
$sub = $sub ?: current_subscription($pdo, $tid);
$usageRows = ['students' => 'Students', 'instructors' => 'Instructors', 'courses' => 'Courses', 'storage_mb' => 'Storage (MB)'];
require __DIR__ . '/../includes/header.php';
?>
<?php if ($sub): ?>
<div class="row g-3 mb-4">
  <div class="col-xl-5"><div class="money-card h-100"><small>Current plan</small>
    <div class="money-value"><?= e($sub['plan_name']) ?></div>
    <small class="opacity-75 text-capitalize"><?= e($sub['billing_cycle']) ?> billing · status: <?= e($sub['status']) ?>
    <?= $sub['current_period_end'] ? ' · period ends ' . e(date('M j, Y', strtotime($sub['current_period_end']))) : '' ?>
    <?= $sub['trial_ends_at'] ? ' · trial ends ' . e(date('M j, Y', strtotime($sub['trial_ends_at']))) : '' ?></small>
    <div class="mt-3 d-flex gap-2">
      <?php if ($sub['status'] !== 'cancelled'): ?>
      <form method="post" onsubmit="return confirm('Cancel this subscription?')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-light btn-sm">Cancel subscription</button></form>
      <?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="renew"><button class="btn btn-light btn-sm">Reactivate subscription</button></form>
      <?php endif; ?>
    </div></div></div>
  <div class="col-xl-7"><div class="card h-100"><div class="card-header bg-white fw-bold">Plan usage (live from your data)</div><div class="card-body">
    <?php foreach ($usageRows as $metric => $label): $used = usage_count($pdo, $tid, $metric);
      $limit = $metric === 'storage_mb' ? (int)$sub['storage_mb'] : (int)$sub['max_' . $metric];
      $pct = $limit > 0 ? min(100, (int)round($used / $limit * 100)) : 0; ?>
    <div class="mb-3"><div class="d-flex justify-content-between small"><span><?= $label ?></span><span><?= number_format($used) ?> / <?= $limit < 0 ? '∞' : number_format($limit) ?></span></div>
      <div class="progress progress-slim"><div class="progress-bar <?= $pct >= 90 ? 'bg-danger' : '' ?>" style="width:<?= $limit < 0 ? 8 : $pct ?>%"></div></div></div>
    <?php endforeach; ?>
    <form method="post" class="row g-2 align-items-end mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="record_payment">
      <div class="col-md-3"><label class="form-label small">Record payment $</label><input class="form-control" type="number" step="0.01" min="0" name="amount" value="<?= e($sub['amount']) ?>"></div>
      <div class="col-md-3"><label class="form-label small">Method</label><select class="form-select" name="method"><option value="manual">Manual</option><option value="bank_transfer">Bank transfer</option><option value="card">Card</option><option value="paypal">PayPal</option></select></div>
      <div class="col-md-4"><label class="form-label small">Reference</label><input class="form-control" name="reference" placeholder="Transfer / transaction reference"></div>
      <div class="col-md-2"><button class="btn btn-outline-primary w-100">Record</button></div>
      <p class="text-muted small mb-0">Recorded payments stay <em>pending</em> until verified — they never mark themselves paid.</p>
    </form>
  </div></div></div>
</div>
<?php endif; ?>

<h2 class="h5 fw-bold mb-3">Upgrade, downgrade or switch billing</h2>
<div class="row g-3">
  <?php foreach ($plans as $p): $current = $sub && (int)$sub['plan_id'] === (int)$p['id']; $feats = json_decode((string)($p['features'] ?? '[]'), true) ?: []; ?>
  <div class="col-md-6 col-xl-<?= count($plans) > 4 ? '2' : '3' ?>">
    <article class="pricing-card h-100 <?= $current ? 'popular' : '' ?>">
      <?php if ($current): ?><span class="popular-badge">Current plan</span><?php endif; ?>
      <h3 class="h6 fw-bold"><?= e($p['name']) ?></h3>
      <div class="price-line"><span class="price-amount"><?= money($p['price_monthly']) ?></span><span class="text-muted">/month</span></div>
      <p class="text-muted small"><?= e($p['description'] ?? '') ?></p>
      <ul class="check-list small">
        <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_students'] < 0 ? 'Unlimited' : number_format((int)$p['max_students']) ?> students</li>
        <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_courses'] < 0 ? 'Unlimited' : (int)$p['max_courses'] ?> courses</li>
        <?php foreach (array_slice($feats, 0, 2) as $f): ?><li><i class="bi bi-check-circle-fill"></i> <?= e($f) ?></li><?php endforeach; ?>
      </ul>
      <?php if (!$current): ?>
      <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="choose_plan"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
        <select class="form-select form-select-sm mb-2" name="billing_cycle"><option value="monthly">Monthly — <?= money($p['price_monthly']) ?></option><option value="yearly">Yearly — <?= money($p['price_yearly']) ?></option></select>
        <input class="form-control form-control-sm mb-2" name="coupon" placeholder="Coupon code (try WELCOME10)">
        <button class="btn btn-gradient w-100 btn-sm" onclick="return confirm('Request this plan change? Paid changes activate after payment verification.')"><?= (float)$p['price_monthly'] <= 0 ? 'Switch to Free' : 'Request ' . e($p['name']) ?></button>
      </form>
      <?php else: ?><div class="alert alert-success py-2 small mt-3 mb-0">This is your active plan.</div><?php endif; ?>
    </article>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
