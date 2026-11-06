<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
// Invitation activation: tenant admins create instructor/student accounts with an
// activation token; the invitee sets their own password here.
$token = getp('token', (string)($_POST['token'] ?? ''));
$error = ''; $user = null;
if ($token !== '') {
    $s = $pdo->prepare('SELECT * FROM users WHERE activation_token=? LIMIT 1');
    $s->execute([$token]);
    $user = $s->fetch() ?: null;
}
if (is_post() && $user) {
    verify_csrf();
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8) $error = 'Password must be at least 8 characters.';
    elseif ($pw !== (string)($_POST['password_confirmation'] ?? '')) $error = 'Passwords do not match.';
    else {
        $pdo->prepare('UPDATE users SET password_hash=?, activation_token=NULL, status="active", email_verified_at=COALESCE(email_verified_at, NOW()) WHERE id=?')
            ->execute([password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
        audit_log($pdo, 'account_activated', 'users', (int)$user['id']);
        flash('success', 'Account activated. Welcome to ' . APP_NAME . ' — please sign in.');
        redirect('/auth/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activate account · <?= e(APP_NAME) ?></title>
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
    <?php if (!$user): ?>
      <h1 class="h4 fw-bold">Invitation not found</h1>
      <p class="text-muted">This activation link is invalid or has already been used.</p>
      <a class="btn btn-gradient w-100" href="/auth/login.php">Go to login</a>
    <?php else: ?>
      <h1 class="h4 fw-bold">Activate your account</h1>
      <p class="text-muted">Welcome, <?= e($user['name']) ?>. Set a password to activate your <span class="text-capitalize"><?= e($user['role']) ?></span> account for <code><?= e($user['email']) ?></code>.</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="mb-4"><label class="form-label" for="password_confirmation">Confirm password</label>
        <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-gradient w-100 py-2" type="submit">Activate account</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
