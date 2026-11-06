<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
if (current_user()) redirect(role_home(current_user()['role']));
$pdo = db();
$plans = $pdo->query('SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY price_monthly')->fetchAll();
$errors = [];

if (is_post()) {
    verify_csrf();
    $orgName  = post('org_name');
    $orgType  = post('org_type', 'academy');
    $adminName = post('admin_name');
    $email    = strtolower(post('email'));
    $password = (string)($_POST['password'] ?? '');
    $planId   = (int)($_POST['plan_id'] ?? 0);
    $cycle    = post('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
    $brand    = post('brand_color', '#4f46e5');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $brand)) $brand = '#4f46e5';

    if ($orgName === '') $errors[] = 'Organization name is required.';
    if ($adminName === '') $errors[] = 'Your name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    $plan = null;
    foreach ($plans as $p) if ((int)$p['id'] === $planId) $plan = $p;
    if (!$plan) $errors[] = 'Please choose a plan.';
    $s = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
    $s->execute([$email]);
    if ((int)$s->fetchColumn() > 0) $errors[] = 'That email is already registered. Please sign in instead.';

    $logoPath = null;
    if (empty($errors) && !empty($_FILES['logo']['name'])) {
        try { $logoPath = handle_upload($_FILES['logo'], 'logos', ['png', 'jpg', 'jpeg', 'svg', 'webp'], 2); }
        catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
    }

    if (empty($errors) && $plan) {
        try {
            $pdo->beginTransaction();
            // Unique tenant slug.
            $slug = slugify($orgName); $base = $slug; $i = 1;
            $chk = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug=?');
            while (true) { $chk->execute([$slug]); if ((int)$chk->fetchColumn() === 0) break; $slug = $base . '-' . (++$i); }

            $trialDays = (int)($plan['trial_days'] ?? 14);
            $ins = $pdo->prepare('INSERT INTO tenants(name,slug,org_type,email,logo,status,created_at) VALUES(?,?,?,?,?,"active",NOW())');
            $ins->execute([$orgName, $slug, $orgType, $email, $logoPath]);
            $tenantId = (int)$pdo->lastInsertId();

            $verifyToken = bin2hex(random_bytes(24));
            $ins = $pdo->prepare('INSERT INTO users(tenant_id,name,email,password_hash,role,status,verification_token,created_at) VALUES(?,?,?,?,"admin","pending",?,NOW())');
            $ins->execute([$tenantId, $adminName, $email, password_hash($password, PASSWORD_DEFAULT), $verifyToken]);
            $adminId = (int)$pdo->lastInsertId();

            // Onboarding defaults & branding.
            set_tenant_setting($pdo, $tenantId, 'brand_color', $brand);
            set_tenant_setting($pdo, $tenantId, 'site_title', $orgName);
            set_tenant_setting($pdo, $tenantId, 'onboarding_step', 'completed');
            set_tenant_setting($pdo, $tenantId, 'timezone', post('timezone', 'UTC'));

            // Subscription: free plans activate immediately; paid plans start on a free trial.
            $price = $cycle === 'yearly' ? (float)$plan['price_yearly'] : (float)$plan['price_monthly'];
            $status = ((float)$plan['price_monthly'] <= 0) ? 'active' : 'trial';
            $start = date('Y-m-d H:i:s');
            $end = $status === 'trial'
                ? date('Y-m-d H:i:s', strtotime('+' . max(1, $trialDays) . ' days'))
                : date('Y-m-d H:i:s', strtotime($cycle === 'yearly' ? '+1 year' : '+1 month'));
            $ins = $pdo->prepare('INSERT INTO subscriptions(tenant_id,plan_id,billing_cycle,status,amount,starts_at,current_period_end,trial_ends_at,grace_days,created_at)
                                  VALUES(?,?,?,?,?,?,?,?,7,NOW())');
            $ins->execute([$tenantId, $plan['id'], $cycle, $status, $price, $start, $end, $status === 'trial' ? $end : null]);
            $subId = (int)$pdo->lastInsertId();

            // First invoice for paid plans (unpaid until a verified payment is recorded).
            if ($price > 0) create_invoice($pdo, $tenantId, $subId, null, $price, $plan['name'] . ' plan (' . $cycle . ') — first period');
            log_activity($pdo, 'Academy registered: ' . $orgName . ' on the ' . $plan['name'] . ' plan', $tenantId);
            audit_log($pdo, 'tenant_registered', 'tenants', $tenantId);
            notify_user($pdo, null, null, 'New academy registered', $orgName . ' started on the ' . $plan['name'] . ' plan.', '/super-admin/tenants.php');

            $pdo->commit();
            $link = '/auth/verify.php?token=' . $verifyToken;
            @file_put_contents(dirname(__DIR__) . '/storage/logs/verifications.log', date('c') . ' ' . $email . ' ' . $link . PHP_EOL, FILE_APPEND);
            $_SESSION['pending_verify'] = ['email' => $email, 'link' => APP_DEBUG ? $link : ''];
            redirect('/auth/register.php?done=1');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = APP_DEBUG ? $ex->getMessage() : 'Registration failed. Please try again.';
        }
    }
}
$done = isset($_GET['done']);
$pending = $_SESSION['pending_verify'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register your academy · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container py-5" style="max-width:880px">
  <a class="d-flex align-items-center gap-2 text-decoration-none mb-4" href="/index.php">
    <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="fw-bold fs-5">Lurni <span class="text-gradient">Academy Cloud</span></span>
  </a>

  <?php if ($done): ?>
    <div class="card p-4 p-md-5 text-center">
      <span class="brand-mark mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem"><i class="bi bi-rocket-takeoff"></i></span>
      <h1 class="h3 fw-bold">Your academy is ready</h1>
      <p class="text-muted mx-auto" style="max-width:520px">We created your isolated tenant workspace, admin account and subscription.
      Please verify <strong><?= e($pending['email'] ?? 'your email') ?></strong> using the verification email to fully activate the account, then sign in to finish onboarding (add instructors, categories and your first course).</p>
      <?php if (!empty($pending['link'])): ?><div class="demo-box small">Debug verification link: <a href="<?= e($pending['link']) ?>"><?= e($pending['link']) ?></a></div><?php endif; ?>
      <a class="btn btn-gradient btn-lg mt-3" href="/auth/login.php">Continue to login</a>
    </div>
  <?php else: ?>
    <h1 class="h3 fw-bold">Launch your academy in minutes</h1>
    <p class="text-muted mb-4">For schools, academies, universities and training centers. Your courses, students and payments stay isolated in your own tenant workspace.</p>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="card p-4 p-md-5" novalidate>
      <?= csrf_field() ?>
      <div class="onboard-steps mb-4">
        <span class="step-dot active">1 · Organization</span><span class="step-dot">2 · Admin account</span><span class="step-dot">3 · Plan & branding</span>
      </div>
      <h2 class="h6 text-uppercase text-muted">Step 1 — Organization</h2>
      <div class="row g-3 mb-4">
        <div class="col-md-6"><label class="form-label" for="org_name">Organization name *</label>
          <input class="form-control" id="org_name" name="org_name" required value="<?= e(post('org_name')) ?>" placeholder="e.g. Bright Future Academy"></div>
        <div class="col-md-3"><label class="form-label" for="org_type">Type</label>
          <select class="form-select" id="org_type" name="org_type">
            <?php foreach (['academy' => 'Academy', 'school' => 'School', 'university' => 'University', 'training_center' => 'Training center', 'individual' => 'Individual tutor'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= post('org_type') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><label class="form-label" for="timezone">Timezone</label>
          <input class="form-control" id="timezone" name="timezone" value="<?= e(post('timezone', 'UTC')) ?>" placeholder="UTC"></div>
      </div>
      <h2 class="h6 text-uppercase text-muted">Step 2 — Admin account</h2>
      <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label" for="admin_name">Your name *</label>
          <input class="form-control" id="admin_name" name="admin_name" required value="<?= e(post('admin_name')) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="email">Work email *</label>
          <input class="form-control" type="email" id="email" name="email" required value="<?= e(post('email')) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="password">Password *</label>
          <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
          <div class="form-text">Minimum 8 characters. Stored with secure password hashing.</div></div>
      </div>
      <h2 class="h6 text-uppercase text-muted">Step 3 — Plan & branding</h2>
      <div class="row g-3 mb-3">
        <?php foreach ($plans as $p): ?>
        <div class="col-md-<?= count($plans) > 4 ? '4' : '6' ?>">
          <label class="plan-card">
            <input type="radio" name="plan_id" value="<?= (int)$p['id'] ?>" <?= (int)(post('plan_id') ?: ($plans[1]['id'] ?? $p['id'])) === (int)$p['id'] ? 'checked' : '' ?>>
            <span class="plan-card-body">
              <span class="fw-bold d-block"><?= e($p['name']) ?></span>
              <span class="fs-5 fw-bold text-gradient"><?= money($p['price_monthly']) ?></span><span class="text-muted">/mo</span>
              <span class="d-block small text-muted mt-1"><?= (int)$p['max_students'] < 0 ? 'Unlimited' : (int)$p['max_students'] ?> students · <?= (int)$p['max_courses'] < 0 ? 'Unlimited' : (int)$p['max_courses'] ?> courses</span>
              <?php if ((int)$p['trial_days'] > 0 && (float)$p['price_monthly'] > 0): ?><span class="badge bg-primary-subtle text-primary mt-1"><?= (int)$p['trial_days'] ?>-day free trial</span><?php endif; ?>
            </span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-md-3"><label class="form-label" for="billing_cycle">Billing cycle</label>
          <select class="form-select" id="billing_cycle" name="billing_cycle">
            <option value="monthly">Monthly</option>
            <option value="yearly" <?= post('billing_cycle') === 'yearly' ? 'selected' : '' ?>>Yearly (2 months free)</option>
          </select></div>
        <div class="col-md-3"><label class="form-label" for="brand_color">Brand color</label>
          <input class="form-control form-control-color w-100" type="color" id="brand_color" name="brand_color" value="<?= e(post('brand_color', '#4f46e5')) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="logo">Academy logo (optional)</label>
          <input class="form-control" type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp"></div>
      </div>
      <button class="btn btn-gradient btn-lg w-100" type="submit"><i class="bi bi-rocket-takeoff me-2"></i>Create my academy</button>
      <p class="text-muted small text-center mt-3 mb-0">By registering you agree to the <a href="/terms.php">Terms</a> and <a href="/privacy.php">Privacy Policy</a>. Paid plans start with a free trial — no card required.</p>
    </form>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
