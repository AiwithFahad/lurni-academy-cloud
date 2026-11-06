<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Coupons'; $active = 'coupons';

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [strtoupper(post('code')), post('description'), post('discount_type') === 'fixed' ? 'fixed' : 'percent',
                 (float)($_POST['discount_value'] ?? 0), (float)($_POST['min_amount'] ?? 0), (int)($_POST['max_uses'] ?? 0),
                 post('expires_at') !== '' ? post('expires_at') : null, isset($_POST['is_active']) ? 1 : 0];
        if ($id) {
            $pdo->prepare('UPDATE coupons SET code=?, description=?, discount_type=?, discount_value=?, min_amount=?, max_uses=?, expires_at=?, is_active=? WHERE id=?')->execute([...$data, $id]);
            flash('success', 'Coupon updated.');
        } else {
            $pdo->prepare('INSERT INTO coupons(code,description,discount_type,discount_value,min_amount,max_uses,expires_at,is_active) VALUES(?,?,?,?,?,?,?,?)')->execute($data);
            flash('success', 'Coupon created.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM coupons WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Coupon deleted.');
    }
    redirect('/super-admin/coupons.php');
}
$rows = $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM coupons WHERE id=?'); $s->execute([$eid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Discount</th><th>Usage</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $c): ?>
    <tr><td><code class="fw-bold"><?= e($c['code']) ?></code><br><small class="text-muted"><?= e($c['description'] ?? '') ?></small></td>
      <td><?= $c['discount_type'] === 'percent' ? rtrim(rtrim(number_format((float)$c['discount_value'], 2), '0'), '.') . '%' : money($c['discount_value']) ?> off<?= (float)$c['min_amount'] > 0 ? '<br><small class="text-muted">min ' . money($c['min_amount']) . '</small>' : '' ?></td>
      <td><?= (int)$c['times_used'] ?> / <?= (int)$c['max_uses'] === 0 ? '∞' : (int)$c['max_uses'] ?></td>
      <td><small><?= $c['expires_at'] ? e(date('M j, Y', strtotime($c['expires_at']))) : 'Never' ?></small></td>
      <td><span class="badge bg-<?= (int)$c['is_active'] ? 'success' : 'secondary' ?>-subtle text-<?= (int)$c['is_active'] ? 'success' : 'secondary' ?>"><?= (int)$c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete coupon?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No coupons yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit coupon' : 'Create coupon' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Code</label><input class="form-control text-uppercase" name="code" required value="<?= e($edit['code'] ?? '') ?>" placeholder="WELCOME10"></div>
      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Type</label><select class="form-select" name="discount_type"><option value="percent" <?= ($edit['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent %</option><option value="fixed" <?= ($edit['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed $</option></select></div>
        <div class="col-6 mb-2"><label class="form-label">Value</label><input class="form-control" type="number" step="0.01" min="0" name="discount_value" required value="<?= e($edit['discount_value'] ?? '10') ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Min amount $</label><input class="form-control" type="number" step="0.01" min="0" name="min_amount" value="<?= e($edit['min_amount'] ?? '0') ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Max uses (0 = ∞)</label><input class="form-control" type="number" min="0" name="max_uses" value="<?= e($edit['max_uses'] ?? '0') ?>"></div>
      </div>
      <div class="mb-2"><label class="form-label">Expires at</label><input class="form-control" type="datetime-local" name="expires_at" value="<?= $edit && $edit['expires_at'] ? e(date('Y-m-d\TH:i', strtotime($edit['expires_at']))) : '' ?>"></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="ca" <?= !$edit || (int)$edit['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="ca">Active</label></div>
      <button class="btn btn-primary w-100" type="submit"><?= $edit ? 'Update coupon' : 'Create coupon' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/super-admin/coupons.php">Cancel edit</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
