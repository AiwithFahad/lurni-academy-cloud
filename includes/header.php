<?php
// Shared app layout header (tenant admin / instructor / student portals).
// Expects: $pageTitle (string). Requires includes/auth.php loaded first.
$u = current_user();
$pdo = db();
$unread = 0; $tenantName = APP_NAME; $brandColor = '#4f46e5';
if ($u) {
    try {
        $s = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND (user_id=? OR (user_id IS NULL AND tenant_id=?))');
        $s->execute([$u['id'], $u['tenant_id']]);
        $unread = (int)$s->fetchColumn();
        if ($u['tenant_id']) {
            $s = $pdo->prepare('SELECT name FROM tenants WHERE id=?');
            $s->execute([$u['tenant_id']]);
            $tenantName = (string)($s->fetchColumn() ?: APP_NAME);
            $brandColor = tenant_setting($pdo, (int)$u['tenant_id'], 'brand_color', '#4f46e5');
        }
    } catch (Throwable $ignored) {}
}
$searchAction = $u && $u['role'] === 'admin' ? '/admin/students.php' : ($u && $u['role'] === 'student' ? '/student/browse.php' : '/instructor/courses.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Dashboard') ?> · <?= e($tenantName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
<style>:root{--brand:<?= e($brandColor) ?>}</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="app-main">
  <header class="topbar">
    <button class="btn btn-light d-lg-none" id="sidebarToggle" aria-label="Toggle menu"><i class="bi bi-list fs-5"></i></button>
    <h1 class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
    <form class="topbar-search d-none d-md-flex" method="get" action="<?= e($searchAction) ?>">
      <i class="bi bi-search"></i>
      <input type="search" name="q" placeholder="Search students, courses…" value="<?= e(getp('q')) ?>">
    </form>
    <div class="topbar-actions">
      <a class="icon-btn position-relative" href="<?= e($u['role'] === 'admin' ? '/admin/notifications.php' : ($u['role'] === 'student' ? '/student/messages.php' : '/instructor/messages.php')) ?>" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <?php if ($unread > 0): ?><span class="badge-dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
      </a>
      <div class="dropdown">
        <button class="profile-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="avatar-circle"><?= e(initials($u['name'] ?? 'U')) ?></span>
          <span class="d-none d-md-block text-start">
            <span class="d-block fw-semibold lh-1"><?= e($u['name'] ?? '') ?></span>
            <small class="text-muted text-capitalize"><?= e(str_replace('_', ' ', $u['role'] ?? '')) ?></small>
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><a class="dropdown-item" href="<?= e(role_home($u['role'] ?? '')) ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
          <?php if (($u['role'] ?? '') === 'admin'): ?>
          <li><a class="dropdown-item" href="/admin/settings.php"><i class="bi bi-gear me-2"></i>Academy settings</a></li>
          <li><a class="dropdown-item" href="/admin/subscription.php"><i class="bi bi-credit-card me-2"></i>Subscription & billing</a></li>
          <?php endif; ?>
          <li><a class="dropdown-item" href="/index.php"><i class="bi bi-globe me-2"></i>View website</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </header>
  <main class="app-content">
  <?= flash_html() ?>
