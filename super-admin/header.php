<?php
// Super Admin layout header. Expects $pageTitle; includes/auth.php loaded first.
$u = current_user();
$pdo = db();
$openTickets = 0; $pendingPayments = 0;
try {
    $openTickets = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn();
    $pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
} catch (Throwable $ignored) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle ?? 'Super Admin') ?> · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="app-main">
  <header class="topbar">
    <button class="btn btn-light d-lg-none" id="sidebarToggle" aria-label="Toggle menu"><i class="bi bi-list fs-5"></i></button>
    <h1 class="topbar-title"><?= e($pageTitle ?? 'Super Admin') ?></h1>
    <div class="topbar-actions">
      <?php if ($pendingPayments > 0): ?><a class="btn btn-warning btn-sm" href="/super-admin/payments.php?status=pending"><i class="bi bi-hourglass-split me-1"></i><?= $pendingPayments ?> payments to verify</a><?php endif; ?>
      <?php if ($openTickets > 0): ?><a class="btn btn-outline-danger btn-sm" href="/super-admin/tickets.php"><i class="bi bi-life-preserver me-1"></i><?= $openTickets ?> open tickets</a><?php endif; ?>
      <div class="dropdown">
        <button class="profile-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="avatar-circle"><?= e(initials($u['name'] ?? 'SA')) ?></span>
          <span class="d-none d-md-block text-start"><span class="d-block fw-semibold lh-1"><?= e($u['name'] ?? '') ?></span><small class="text-muted">Super Admin</small></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><a class="dropdown-item" href="/index.php"><i class="bi bi-globe me-2"></i>View website</a></li>
          <li><a class="dropdown-item" href="/super-admin/settings.php"><i class="bi bi-gear me-2"></i>Platform settings</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </header>
  <main class="app-content">
  <?= flash_html() ?>
