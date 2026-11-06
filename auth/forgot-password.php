<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$pdo = db();
$done = false; $devLink = '';
if (is_post()) {
    verify_csrf();
    $email = strtolower(post('email'));
    $s = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $s->execute([$email]);
    if ($s->fetchColumn()) {
        $token = bin2hex(random_bytes(32));
        $ins = $pdo->prepare('INSERT INTO password_resets(email,token_hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR),NOW())');
        $ins->execute([$email, hash('sha256', $token)]);
        $link = '/auth/reset-password.php?token=' . $token . '&email=' . urlencode($email);
        // In production this link is emailed via MAIL_FROM. For local/demo installs it is
        // written to storage/logs/password-resets.log and shown when APP_DEBUG is on.
        @file_put_contents(dirname(__DIR__) . '/storage/logs/password-resets.log', date('c') . ' ' . $email . ' ' . $link . PHP_EOL, FILE_APPEND);
        if (APP_DEBUG) $devLink = $link;
    }
    // Always show the same message to avoid account enumeration.
    $done = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot password · <?= e(APP_NAME) ?></title>
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
    <h1 class="h4 fw-bold">Reset your password</h1>
    <?php if ($done): ?>
      <div class="alert alert-success mt-3">If an account exists for that email, a secure reset link (valid 1 hour) has been sent.</div>
      <?php if ($devLink): ?><div class="demo-box small">Debug reset link: <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a></div><?php endif; ?>
      <a class="btn btn-gradient w-100 mt-3" href="/auth/login.php">Back to login</a>
    <?php else: ?>
      <p class="text-muted">Enter your account email and we will send a secure reset link.</p>
      <form method="post"><?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email address</label>
        <input class="form-control" type="email" id="email" name="email" required autocomplete="email"></div>
        <button class="btn btn-gradient w-100 py-2" type="submit">Send reset link</button>
      </form>
      <p class="text-center text-muted mt-4 mb-0"><a href="/auth/login.php">Back to login</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
