<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) redirect(role_home(current_user()['role']));
$pdo = db();
$error = '';
if (($_GET['error'] ?? '') === 'suspended') $error = 'This academy account has been suspended. Please contact the platform team.';

if (is_post()) {
    verify_csrf();
    $email = strtolower(post('email'));
    $password = (string)($_POST['password'] ?? '');
    $s = $pdo->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $s->execute([$email]);
    $user = $s->fetch();

    if ($user && !empty($user['locked_until']) && strtotime((string)$user['locked_until']) > time()) {
        $error = 'Account temporarily locked after too many failed attempts. Try again in 15 minutes.';
    } elseif (!$user || !password_verify($password, $user['password_hash'])) {
        // Login rate limiting: 5 failures => 15 minute lock.
        if ($user) {
            $attempts = (int)$user['failed_attempts'] + 1;
            if ($attempts >= 5) {
                $pdo->prepare('UPDATE users SET failed_attempts=0, locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id=?')->execute([$user['id']]);
            } else {
                $pdo->prepare('UPDATE users SET failed_attempts=? WHERE id=?')->execute([$attempts, $user['id']]);
            }
        }
        $error = 'Invalid email or password.';
    } elseif ($user['status'] !== 'active') {
        $error = 'This account is not active. Please contact your administrator.';
    } else {
        if ($user['tenant_id']) {
            $t = $pdo->prepare('SELECT status FROM tenants WHERE id=?');
            $t->execute([$user['tenant_id']]);
            if ($t->fetchColumn() === 'suspended') $error = 'This academy account has been suspended. Please contact the platform team.';
        }
        if ($error === '') {
            $pdo->prepare('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
            login_user($user);
            audit_log($pdo, 'login', 'users', (int)$user['id']);
            redirect(role_home($user['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card card">
    <a class="d-flex align-items-center gap-2 text-decoration-none mb-4" href="/index.php">
      <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
      <span class="fw-bold fs-5">Lurni <span class="text-gradient">Academy Cloud</span></span>
    </a>
    <h1 class="h4 fw-bold">Welcome back</h1>
    <p class="text-muted mb-4">Sign in to your academy, instructor or student portal.</p>
    <?= flash_html() ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input class="form-control" type="email" id="email" name="email" required value="<?= e(post('email')) ?>" autocomplete="email">
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
          <label class="form-check-label" for="remember">Remember me</label>
        </div>
        <a href="/auth/forgot-password.php" class="small">Forgot password?</a>
      </div>
      <button class="btn btn-gradient w-100 py-2" type="submit">Sign in</button>
    </form>
    <div class="demo-box mt-4">
      <div class="fw-semibold small mb-2"><i class="bi bi-key me-1"></i>Demo accounts — password: <code>password</code></div>
      <div class="small text-muted lh-lg">
        Super Admin: <code>super@lurni.test</code><br>
        Academy Admin: <code>admin@demo.com</code><br>
        Instructor: <code>instructor@demo.com</code> · Student: <code>student@demo.com</code>
      </div>
    </div>
    <p class="text-center text-muted mt-4 mb-0">New academy? <a href="/auth/register.php" class="fw-semibold">Start your free trial</a></p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
