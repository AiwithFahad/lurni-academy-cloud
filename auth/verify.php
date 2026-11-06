<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$token = getp('token');
$ok = false;
if ($token !== '') {
    $s = $pdo->prepare('SELECT id FROM users WHERE verification_token=? AND email_verified_at IS NULL LIMIT 1');
    $s->execute([$token]);
    $uid = $s->fetchColumn();
    if ($uid) {
        $pdo->prepare('UPDATE users SET email_verified_at=NOW(), verification_token=NULL, status="active" WHERE id=?')->execute([$uid]);
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email verification · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card card text-center">
    <span class="brand-mark mx-auto mb-3"><i class="bi bi-<?= $ok ? 'patch-check' : 'exclamation-triangle' ?>"></i></span>
    <?php if ($ok): ?>
      <h1 class="h4 fw-bold">Email verified</h1>
      <p class="text-muted">Your account is active. You can now sign in to your portal.</p>
      <a class="btn btn-gradient w-100" href="/auth/login.php">Continue to login</a>
    <?php else: ?>
      <h1 class="h4 fw-bold">Link not valid</h1>
      <p class="text-muted">This verification link is invalid or the email is already verified.</p>
      <a class="btn btn-gradient w-100" href="/auth/login.php">Go to login</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
