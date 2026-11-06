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

$pageTitle = 'Payments & Invoices'; $active = 'payments';
$payments = $pdo->prepare('SELECT * FROM payments WHERE tenant_id=? ORDER BY created_at DESC LIMIT 50');
$payments->execute([$tid]); $payments = $payments->fetchAll();
$invoices = $pdo->prepare('SELECT * FROM invoices WHERE tenant_id=? ORDER BY id DESC LIMIT 50');
$invoices->execute([$tid]); $invoices = $invoices->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Payment history</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Date</th><th>Amount</th><th>Method / Ref</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($payments as $p): ?>
      <tr><td><small><?= e(date('M j, Y', strtotime($p['created_at']))) ?></small></td>
        <td class="fw-semibold"><?= money((float)$p['amount'] - (float)$p['discount_amount']) ?><?php if ((float)$p['discount_amount'] > 0): ?><br><small class="text-success">coupon <?= e($p['coupon_code'] ?? '') ?> −<?= money($p['discount_amount']) ?></small><?php endif; ?></td>
        <td class="text-capitalize"><small><?= e($p['method']) ?><br><?= e($p['reference'] ?? '') ?></small></td>
        <td><span class="badge bg-<?= match($p['status']) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>-subtle text-<?= match($p['status']) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'secondary' } ?>"><?= e($p['status']) ?></span>
        <?php if ($p['status'] === 'pending'): ?><br><small class="text-muted">awaiting verification</small><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="4" class="text-center text-muted py-4">No payments yet.</td></tr><?php endif; ?>
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
