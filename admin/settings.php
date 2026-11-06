<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Website Settings'; $active = 'settings';
require_permission($pdo, 'settings.manage');

$s = $pdo->prepare('SELECT * FROM tenants WHERE id=?');
$s->execute([$tid]); $tenant = $s->fetch();

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'branding') {
        $logoPath = $tenant['logo'];
        if (!empty($_FILES['logo']['name'])) {
            try { $up = handle_upload($_FILES['logo'], 'logos', ['png', 'jpg', 'jpeg', 'svg', 'webp'], 2); if ($up) $logoPath = $up; }
            catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect('/admin/settings.php'); }
        }
        $pdo->prepare('UPDATE tenants SET name=?, website=?, phone=?, country=?, logo=? WHERE id=?')
            ->execute([post('org_name'), post('website'), post('phone'), post('country'), $logoPath, $tid]);
        set_tenant_setting($pdo, $tid, 'brand_color', preg_match('/^#[0-9a-fA-F]{6}$/', post('brand_color')) ? post('brand_color') : '#4f46e5');
        set_tenant_setting($pdo, $tid, 'site_title', post('site_title'));
        set_tenant_setting($pdo, $tid, 'welcome_message', post('welcome_message'));
        set_tenant_setting($pdo, $tid, 'timezone', post('timezone', 'UTC'));
        set_tenant_setting($pdo, $tid, 'currency', post('currency', 'USD'));
        audit_log($pdo, 'settings_updated', 'tenants', $tid);
        flash('success', 'Academy branding & settings saved.');
    } elseif ($action === 'password') {
        $s = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
        $s->execute([$me['id']]); $hash = (string)$s->fetchColumn();
        if (!password_verify((string)($_POST['current_password'] ?? ''), $hash)) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen((string)($_POST['new_password'] ?? '')) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ((string)$_POST['new_password'] !== (string)($_POST['confirm_password'] ?? '')) {
            flash('error', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash((string)$_POST['new_password'], PASSWORD_DEFAULT), $me['id']]);
            audit_log($pdo, 'password_changed', 'users', (int)$me['id']);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'profile') {
        $pdo->prepare('UPDATE users SET name=?, phone=? WHERE id=?')->execute([post('name'), post('phone'), $me['id']]);
        $_SESSION['user']['name'] = post('name');
        flash('success', 'Profile updated.');
    }
    redirect('/admin/settings.php');
}
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Academy branding & website settings</div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="branding">
      <div class="row g-2">
        <div class="col-md-6 mb-2"><label class="form-label">Academy name</label><input class="form-control" name="org_name" required value="<?= e($tenant['name']) ?>"></div>
        <div class="col-md-6 mb-2"><label class="form-label">Site title</label><input class="form-control" name="site_title" value="<?= e(tenant_setting($pdo, $tid, 'site_title', $tenant['name'])) ?>"></div>
        <div class="col-md-6 mb-2"><label class="form-label">Website</label><input class="form-control" name="website" value="<?= e($tenant['website'] ?? '') ?>" placeholder="https://"></div>
        <div class="col-md-3 mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($tenant['phone'] ?? '') ?>"></div>
        <div class="col-md-3 mb-2"><label class="form-label">Country</label><input class="form-control" name="country" value="<?= e($tenant['country'] ?? '') ?>"></div>
        <div class="col-md-4 mb-2"><label class="form-label">Brand color</label><input class="form-control form-control-color w-100" type="color" name="brand_color" value="<?= e(tenant_setting($pdo, $tid, 'brand_color', '#4f46e5')) ?>"></div>
        <div class="col-md-4 mb-2"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="<?= e(tenant_setting($pdo, $tid, 'timezone', 'UTC')) ?>"></div>
        <div class="col-md-4 mb-2"><label class="form-label">Currency</label><input class="form-control" name="currency" value="<?= e(tenant_setting($pdo, $tid, 'currency', 'USD')) ?>"></div>
      </div>
      <div class="mb-2"><label class="form-label">Welcome message</label><textarea class="form-control" name="welcome_message" rows="2"><?= e(tenant_setting($pdo, $tid, 'welcome_message')) ?></textarea></div>
      <div class="mb-3"><label class="form-label">Logo</label>
        <?php if ($tenant['logo']): ?><div class="mb-1"><img src="/<?= e($tenant['logo']) ?>" alt="Academy logo" style="height:44px"></div><?php endif; ?>
        <input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp"></div>
      <button class="btn btn-primary">Save settings</button>
    </form></div></div></div>
  <div class="col-xl-5">
    <div class="card mb-3"><div class="card-header bg-white fw-bold">My profile</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($me['name']) ?>"></div>
        <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($me['email']) ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($me['phone'] ?? '') ?>"></div>
        <button class="btn btn-outline-primary w-100">Update profile</button>
      </form></div></div>
    <div class="card"><div class="card-header bg-white fw-bold">Change password</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="mb-2"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" required></div>
        <div class="mb-2"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" required minlength="8"></div>
        <div class="mb-3"><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="confirm_password" required minlength="8"></div>
        <button class="btn btn-outline-primary w-100">Change password</button>
      </form>
      <p class="text-muted small mt-3 mb-0"><i class="bi bi-shield-lock me-1"></i>Passwords are hashed with PHP's password_hash (bcrypt). Two-factor authentication can be enabled by the platform team — the schema stores TOTP secrets per user.</p>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
