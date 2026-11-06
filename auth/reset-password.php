<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$token = getp('token', (string)($_POST['token'] ?? ''));
$email = strtolower(getp('email', (string)($_POST['email'] ?? '')));
$error = ''; $valid = false;

$s = $pdo->prepare('SELECT * FROM password_resets WHERE email=? AND token_hash=? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
$s->execute([$email, hash('sha256', $token)]);
$reset = $s->fetch();
$valid = (bool)$reset;

if (is_post() && $valid) {
    verify_csrf();
    $pw = (string)($_POST['password'] ?? '');
    if (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pw !== (string)($_POST['password_confirmation'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE email=?')
            ->execute([password_hash($pw, PASSWORD_DEFAULT), $email]);
        $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$reset['id']]);
        flash('success', 'Password updated. Please sign in with your new password.');
        redirect('/auth/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set new password · <?= e(APP_NAME) ?></title>
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
    <h1 class="h4 fw-bold">Set a new password</h1>
    <?php if (!$valid): ?>
      <div class="alert alert-danger mt-3">This reset link is invalid or has expired. Please request a new one.</div>
      <a class="btn btn-gradient w-100" href="/auth/forgot-password.php">Request new link</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="mt-3"><?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <div class="mb-3"><label class="form-label" for="password">New password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="mb-4"><label class="form-label" for="password_confirmation">Confirm password</label>
        <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-gradient w-100 py-2" type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
