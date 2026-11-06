<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Platform Settings'; $active = 'settings';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'settings') {
        foreach (['platform_name', 'support_email', 'currency', 'trial_days_default', 'maintenance_mode'] as $key) {
            set_platform_setting($pdo, $key, post($key));
        }
        flash('success', 'Platform settings saved.');
    } elseif (post('action') === 'read_message') {
        $pdo->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Message marked as read.');
    }
    redirect('/super-admin/settings.php');
}
$messages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 20')->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="row g-3">
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">Platform settings</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <div class="mb-2"><label class="form-label">Platform name</label><input class="form-control" name="platform_name" value="<?= e(platform_setting($pdo, 'platform_name', APP_NAME)) ?>"></div>
      <div class="mb-2"><label class="form-label">Support email</label><input class="form-control" type="email" name="support_email" value="<?= e(platform_setting($pdo, 'support_email')) ?>"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Currency</label><input class="form-control" name="currency" value="<?= e(platform_setting($pdo, 'currency', 'USD')) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Default trial days</label><input class="form-control" type="number" min="0" name="trial_days_default" value="<?= e(platform_setting($pdo, 'trial_days_default', '14')) ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label">Maintenance mode</label>
        <select class="form-select" name="maintenance_mode"><option value="0" <?= platform_setting($pdo, 'maintenance_mode') !== '1' ? 'selected' : '' ?>>Off</option><option value="1" <?= platform_setting($pdo, 'maintenance_mode') === '1' ? 'selected' : '' ?>>On</option></select></div>
      <button class="btn btn-primary w-100">Save settings</button>
    </form>
    <hr>
    <h3 class="h6 fw-bold">Backup & restore</h3>
    <p class="small text-muted mb-0">Production backups: schedule <code>mysqldump lurni_academy_cloud</code> to <code>storage/backups/</code> nightly and test restores monthly. Full procedure: <code>docs/DEPLOYMENT.md</code>.</p>
  </div></div></div>
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Website contact messages</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($messages as $m): ?>
      <li class="list-group-item">
        <div class="d-flex justify-content-between"><strong><?= e($m['name']) ?></strong><small class="text-muted"><?= e(time_ago($m['created_at'])) ?></small></div>
        <small class="text-muted"><?= e($m['email']) ?> · <?= e($m['subject'] ?? '') ?></small>
        <p class="small mb-1 mt-1"><?= nl2br(e($m['message'])) ?></p>
        <?php if (!(int)$m['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_message"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Mark read</button></form><?php endif; ?>
      </li>
    <?php endforeach; ?>
    <?php if (!$messages): ?><li class="list-group-item text-muted">No contact messages yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
