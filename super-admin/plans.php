<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Subscription Plans'; $active = 'plans';

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $feats = array_values(array_filter(array_map('trim', explode("\n", post('features')))));
        $data = [
            post('name'), slugify(post('name')), post('description'),
            (float)($_POST['price_monthly'] ?? 0), (float)($_POST['price_yearly'] ?? 0),
            (int)($_POST['trial_days'] ?? 0), (int)($_POST['max_students'] ?? 0),
            (int)($_POST['max_instructors'] ?? 0), (int)($_POST['max_courses'] ?? 0),
            (int)($_POST['storage_mb'] ?? 0), json_encode($feats), isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($id) {
            $pdo->prepare('UPDATE subscription_plans SET name=?, slug=?, description=?, price_monthly=?, price_yearly=?, trial_days=?, max_students=?, max_instructors=?, max_courses=?, storage_mb=?, features=?, is_active=? WHERE id=?')
                ->execute([...$data, $id]);
            flash('success', 'Plan updated.');
        } else {
            $pdo->prepare('INSERT INTO subscription_plans(name,slug,description,price_monthly,price_yearly,trial_days,max_students,max_instructors,max_courses,storage_mb,features,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute($data);
            flash('success', 'Plan created.');
        }
        audit_log($pdo, 'plan_saved', 'subscription_plans', $id ?: (int)$pdo->lastInsertId());
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $inUse = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE plan_id=$id")->fetchColumn();
        if ($inUse > 0) { flash('error', 'This plan has subscriptions and cannot be deleted. Deactivate it instead.'); }
        else { $pdo->prepare('DELETE FROM subscription_plans WHERE id=?')->execute([$id]); flash('success', 'Plan deleted.'); }
    }
    redirect('/super-admin/plans.php');
}

$plans = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM subscriptions s WHERE s.plan_id=p.id) AS subs FROM subscription_plans p ORDER BY sort_order, price_monthly')->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM subscription_plans WHERE id=?'); $s->execute([$eid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="row g-3">
    <?php foreach ($plans as $p): ?>
      <div class="col-md-6"><div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between"><h2 class="h6 fw-bold mb-0"><?= e($p['name']) ?></h2>
          <span class="badge bg-<?= (int)$p['is_active'] ? 'success' : 'secondary' ?>-subtle text-<?= (int)$p['is_active'] ? 'success' : 'secondary' ?>"><?= (int)$p['is_active'] ? 'Active' : 'Inactive' ?></span></div>
          <div class="fs-4 fw-bold mt-2"><?= money($p['price_monthly']) ?><small class="text-muted fs-6">/mo · <?= money($p['price_yearly']) ?>/yr</small></div>
          <p class="text-muted small"><?= e($p['description'] ?? '') ?></p>
          <ul class="small text-muted ps-3">
            <li><?= (int)$p['max_students'] < 0 ? 'Unlimited' : number_format((int)$p['max_students']) ?> students · <?= (int)$p['max_instructors'] < 0 ? 'Unlimited' : (int)$p['max_instructors'] ?> instructors</li>
            <li><?= (int)$p['max_courses'] < 0 ? 'Unlimited' : (int)$p['max_courses'] ?> courses · <?= (int)$p['storage_mb'] < 0 ? 'Unlimited' : number_format((int)$p['storage_mb']) . ' MB' ?> storage · <?= (int)$p['trial_days'] ?> trial days</li>
            <li><?= (int)$p['subs'] ?> subscriptions</li>
          </ul>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$p['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Delete this plan?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
          </div>
        </div></div></div>
    <?php endforeach; ?>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit plan' : 'Create plan' ?></div>
    <div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
        <div class="row g-2">
          <div class="col-6 mb-2"><label class="form-label">Monthly $</label><input class="form-control" type="number" step="0.01" min="0" name="price_monthly" value="<?= e($edit['price_monthly'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Yearly $</label><input class="form-control" type="number" step="0.01" min="0" name="price_yearly" value="<?= e($edit['price_yearly'] ?? '0') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Trial days</label><input class="form-control" type="number" min="0" name="trial_days" value="<?= e($edit['trial_days'] ?? '14') ?>"></div>
          <div class="col-6 mb-2"><label class="form-label">Storage MB (-1 = ∞)</label><input class="form-control" type="number" name="storage_mb" value="<?= e($edit['storage_mb'] ?? '500') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Max students</label><input class="form-control" type="number" name="max_students" value="<?= e($edit['max_students'] ?? '50') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Max instructors</label><input class="form-control" type="number" name="max_instructors" value="<?= e($edit['max_instructors'] ?? '2') ?>"></div>
          <div class="col-4 mb-2"><label class="form-label">Max courses</label><input class="form-control" type="number" name="max_courses" value="<?= e($edit['max_courses'] ?? '5') ?>"></div>
        </div>
        <div class="mb-2"><label class="form-label">Features (one per line)</label><textarea class="form-control" name="features" rows="4"><?= e(implode("\n", json_decode((string)($edit['features'] ?? '[]'), true) ?: [])) ?></textarea></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="pa" <?= !$edit || (int)$edit['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="pa">Active plan</label></div>
        <button class="btn btn-primary w-100" type="submit"><?= $edit ? 'Update plan' : 'Create plan' ?></button>
        <?php if ($edit): ?><a class="btn btn-link w-100" href="/super-admin/plans.php">Cancel edit</a><?php endif; ?>
      </form>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
