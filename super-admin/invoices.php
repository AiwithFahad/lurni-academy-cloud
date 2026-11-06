<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Invoices'; $active = 'invoices';

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (post('action') === 'void' && $id) {
        $pdo->prepare("UPDATE invoices SET status='void' WHERE id=?")->execute([$id]);
        flash('success', 'Invoice voided.');
    }
    redirect('/super-admin/invoices.php');
}
$status = getp('status');
$where = ['1=1']; $args = [];
if (in_array($status, ['unpaid', 'paid', 'void'], true)) { $where[] = 'i.status=?'; $args[] = $status; }
$s = $pdo->prepare('SELECT COUNT(*) FROM invoices i WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 12);
$s = $pdo->prepare("SELECT i.*, t.name AS tenant_name FROM invoices i JOIN tenants t ON t.id=i.tenant_id
    WHERE " . implode(' AND ', $where) . " ORDER BY i.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="card">
  <div class="card-header bg-white"><div class="d-flex flex-wrap gap-2">
    <a class="filter-chip <?= $status === '' ? 'active' : '' ?>" href="?">All</a>
    <?php foreach (['unpaid', 'paid', 'void'] as $st): ?><a class="filter-chip <?= $status === $st ? 'active' : '' ?>" href="?status=<?= $st ?>"><?= ucfirst($st) ?></a><?php endforeach; ?>
  </div></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Invoice</th><th>Tenant</th><th>Description</th><th>Amount</th><th>Status</th><th>Issued / Due</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $inv): ?>
    <tr><td><code><?= e($inv['invoice_number']) ?></code></td><td><?= e($inv['tenant_name']) ?></td>
      <td><?= e($inv['description'] ?? '') ?></td><td class="fw-semibold"><?= money($inv['amount']) ?></td>
      <td><span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'void' ? 'secondary' : 'warning') ?>-subtle text-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'void' ? 'secondary' : 'warning') ?>"><?= e($inv['status']) ?></span></td>
      <td><small><?= $inv['issued_at'] ? e(date('M j, Y', strtotime($inv['issued_at']))) : '' ?><br>due <?= $inv['due_at'] ? e(date('M j, Y', strtotime($inv['due_at']))) : '—' ?></small></td>
      <td class="text-end"><?php if ($inv['status'] === 'unpaid'): ?><form method="post" class="d-inline" onsubmit="return confirm('Void this invoice?')"><?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>"><button class="btn btn-sm btn-outline-danger">Void</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr><?php endif; ?>
    </tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> invoices</small><?= pagination_html($pg, '?status=' . urlencode($status)) ?></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
