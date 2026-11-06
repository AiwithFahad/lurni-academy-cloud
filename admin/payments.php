<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
require_permission($pdo, 'payments.view');

// --- Printable invoice (clean output, no app chrome) ---
if ($printId = (int)($_GET['print'] ?? 0)) {
    $s = $pdo->prepare("SELECT i.*, t.name AS tenant_name, t.email AS tenant_email, p.name AS plan_name
        FROM invoices i JOIN tenants t ON t.id=i.tenant_id
        LEFT JOIN subscriptions sub ON sub.id=i.subscription_id LEFT JOIN subscription_plans p ON p.id=sub.plan_id
        WHERE i.id=? AND i.tenant_id=?");
    $s->execute([$printId, $tid]); $inv = $s->fetch();
    if (!$inv) { http_response_code(404); exit('Invoice not found.'); }
    ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title><?= e($inv['invoice_number']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f8fafc}.invoice{max-width:760px;margin:32px auto;background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.08)}@media print{body{background:#fff}.invoice{box-shadow:none;margin:0}}</style></head>
<body><div class="invoice">
  <div class="d-flex justify-content-between"><div><h1 class="h4 fw-bold mb-0">Lurni Academy Cloud</h1><small class="text-muted">Subscription invoice</small></div>
  <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : 'warning' ?> fs-6 align-self-start"><?= strtoupper($inv['status']) ?></span></div>
  <hr>
  <div class="row"><div class="col-6"><strong>Billed to</strong><br><?= e($inv['tenant_name']) ?><br><span class="text-muted"><?= e($inv['tenant_email'] ?? '') ?></span></div>
  <div class="col-6 text-end"><strong><?= e($inv['invoice_number']) ?></strong><br><span class="text-muted">Issued <?= $inv['issued_at'] ? e(date('M j, Y', strtotime($inv['issued_at']))) : '' ?><br>Due <?= $inv['due_at'] ? e(date('M j, Y', strtotime($inv['due_at']))) : '—' ?></span></div></div>
  <table class="table mt-4"><thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
  <tbody><tr><td><?= e($inv['description'] ?? '') ?><?= $inv['plan_name'] ? ' — ' . e($inv['plan_name']) . ' plan' : '' ?></td><td class="text-end fw-bold"><?= money($inv['amount']) ?></td></tr></tbody></table>
  <?php if ($inv['paid_at']): ?><p class="text-muted">Paid on <?= e(date('M j, Y', strtotime($inv['paid_at']))) ?> after verified payment confirmation.</p><?php endif; ?>
  <button class="btn btn-primary d-print-none" onclick="window.print()">Print / Save PDF</button>
  <a class="btn btn-link d-print-none" href="/admin/payments.php">Back to payments</a>
</div></body></html>
    <?php
    exit;
}

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $pid = (int)($_POST['id'] ?? 0);
    $s = $pdo->prepare('SELECT * FROM payments WHERE id=? AND tenant_id=?');
    $s->execute([$pid, $tid]); $pay = $s->fetch() ?: null;
    if ($action === 'verify_course_payment' && $pay && $pay['kind'] === 'course' && $pay['status'] === 'pending') {
        // Verified confirmation: only now does the payment count as paid and
        // the student's pending enrollment activate.
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE payments SET status='completed', verified_by=?, verified_at=NOW() WHERE id=?")->execute([$me['id'], $pid]);
            if ($pay['invoice_id']) $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW() WHERE id=? AND tenant_id=?")->execute([(int)$pay['invoice_id'], $tid]);
            $stu = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
            $stu->execute([(int)$pay['user_id'], $tid]); $studentId = (int)$stu->fetchColumn();
            if ($studentId && $pay['course_id']) {
                $pdo->prepare("UPDATE enrollments SET status='active' WHERE student_id=? AND course_id=? AND tenant_id=? AND status='pending'")
                    ->execute([$studentId, (int)$pay['course_id'], $tid]);
            }
            $pdo->commit();
            notify_user($pdo, $tid, (int)$pay['user_id'], 'Payment verified — course unlocked', 'Your payment was confirmed and your course is now active. Happy learning!', '/student/my-learning.php');
            queue_email_to_user($pdo, (int)$pay['user_id'], 'Payment verified — course unlocked', "Hi,\n\nYour payment has been verified and your course is now active.\n\n— " . APP_NAME);
            audit_log($pdo, 'payment.verify', 'payment', $pid);
            flash('success', 'Payment verified — the course enrollment is now active.');
        } catch (Throwable $ex) { $pdo->rollBack(); flash('error', 'Verification failed.'); }
    } elseif ($action === 'fail_course_payment' && $pay && $pay['kind'] === 'course' && $pay['status'] === 'pending') {
        $pdo->prepare("UPDATE payments SET status='failed', verified_by=?, verified_at=NOW() WHERE id=?")->execute([$me['id'], $pid]);
        notify_user($pdo, $tid, (int)$pay['user_id'], 'Payment could not be verified', 'Your course payment could not be confirmed. Please contact your academy for help.', '/student/payments.php');
        flash('error', 'Payment marked as failed.');
    } elseif ($action === 'record_refund' && $pay && $pay['status'] === 'completed') {
        $net = (float)$pay['amount'] - (float)$pay['discount_amount'];
        $amount = min($net, max(0.01, (float)($_POST['refund_amount'] ?? $net)));
        $pdo->prepare("INSERT INTO payment_refunds(tenant_id,payment_id,amount,reason,status,processed_by,created_at) VALUES(?,?,?,?,'recorded',?,NOW())")
            ->execute([$tid, $pid, $amount, post('refund_reason') ?: null, $me['id']]);
        $pdo->prepare("UPDATE payments SET status='refunded' WHERE id=?")->execute([$pid]);
        audit_log($pdo, 'payment.refund', 'payment', $pid);
        notify_user($pdo, $tid, (int)$pay['user_id'], 'Refund recorded', 'A refund of ' . money($amount) . ' has been recorded for your payment.', '/student/payments.php');
        flash('success', 'Refund of ' . money($amount) . ' recorded.');
    }
    redirect('/admin/payments.php');
}

$pageTitle = 'Payments & Invoices'; $active = 'payments';
$payments = $pdo->prepare("SELECT p.*, u.name AS payer_name, c.title AS course_title,
    (SELECT COALESCE(SUM(r.amount),0) FROM payment_refunds r WHERE r.payment_id=p.id) AS refunded_total
    FROM payments p LEFT JOIN users u ON u.id=p.user_id LEFT JOIN courses c ON c.id=p.course_id
    WHERE p.tenant_id=? ORDER BY p.created_at DESC LIMIT 80");
$payments->execute([$tid]); $payments = $payments->fetchAll();
$invoices = $pdo->prepare('SELECT * FROM invoices WHERE tenant_id=? ORDER BY id DESC LIMIT 50');
$invoices->execute([$tid]); $invoices = $invoices->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Payment history</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Date</th><th>Payer / Item</th><th>Amount</th><th>Method / Ref</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($payments as $p): ?>
      <tr><td><small><?= e(date('M j, Y', strtotime($p['created_at']))) ?></small></td>
        <td><small><strong><?= e($p['payer_name'] ?? '—') ?></strong><br><?= $p['kind'] === 'course' ? e($p['course_title'] ?? 'Course purchase') : 'Subscription' ?></small></td>
        <td class="fw-semibold"><?= money((float)$p['amount'] - (float)$p['discount_amount']) ?><?php if ((float)$p['discount_amount'] > 0): ?><br><small class="text-success">coupon <?= e($p['coupon_code'] ?? '') ?> −<?= money($p['discount_amount']) ?></small><?php endif; ?><?php if ((float)$p['refunded_total'] > 0): ?><br><small class="text-danger">refunded <?= money((float)$p['refunded_total']) ?></small><?php endif; ?></td>
        <td class="text-capitalize"><small><?= e($p['method']) ?><br><?= e($p['reference'] ?? '') ?></small></td>
        <td><span class="badge bg-<?= match($p['status']) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>-subtle text-<?= match($p['status']) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>"><?= e($p['status']) ?></span>
        <?php if ($p['status'] === 'pending'): ?><br><small class="text-muted">awaiting verification</small><?php endif; ?></td>
        <td class="text-end text-nowrap">
          <?php if ($p['kind'] === 'course' && $p['status'] === 'pending'): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Confirm this payment was received? The course unlocks for the student.')"><?= csrf_field() ?><input type="hidden" name="action" value="verify_course_payment"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-success">Verify</button></form>
          <form method="post" class="d-inline" onsubmit="return confirm('Mark this payment as failed?')"><?= csrf_field() ?><input type="hidden" name="action" value="fail_course_payment"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger">Fail</button></form>
          <?php endif; ?>
          <?php if ($p['kind'] === 'course' && $p['status'] === 'completed'): ?>
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#refundModal<?= (int)$p['id'] ?>">Refund</button>
          <div class="modal fade text-start" id="refundModal<?= (int)$p['id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Record refund</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="record_refund"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <div class="mb-2"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" max="<?= (float)$p['amount'] - (float)$p['discount_amount'] ?>" name="refund_amount" value="<?= (float)$p['amount'] - (float)$p['discount_amount'] ?>" required></div>
              <div class="mb-2"><label class="form-label">Reason</label><input class="form-control" name="refund_reason" placeholder="e.g. Student withdrew within the refund window"></div>
              <p class="text-muted small mb-0">This records the refund against the payment; process the money return through your payment provider.</p></div>
            <div class="modal-footer"><button class="btn btn-primary" type="submit">Record refund</button></div>
          </form></div></div>
          <?php endif; ?>
        </td></tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-muted py-4">No payments yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">Invoices</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($invoices as $inv): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <span><code><?= e($inv['invoice_number']) ?></code><br><small class="text-muted"><?= e($inv['description'] ?? '') ?></small></span>
        <span class="text-end"><strong><?= money($inv['amount']) ?></strong><br>
        <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'void' ? 'secondary' : 'warning') ?>-subtle text-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'void' ? 'secondary' : 'warning') ?>"><?= e($inv['status']) ?></span>
        <a class="btn btn-sm btn-outline-primary ms-1" href="?print=<?= (int)$inv['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i></a></span></li>
    <?php endforeach; ?>
    <?php if (!$invoices): ?><li class="list-group-item text-muted">No invoices yet.</li><?php endif; ?>
    </ul></div>
    <p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Payments are verified by the platform team before they count as paid — a recorded payment stays <em>pending</em> until confirmation.</p></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
