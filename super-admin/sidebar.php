<?php
$active = $active ?? '';
$items = [
    ['section' => 'Platform'],
    ['key' => 'dashboard', 'href' => '/super-admin/dashboard.php', 'icon' => 'bi-grid-1x2', 'label' => 'Dashboard'],
    ['key' => 'institutions', 'href' => '/super-admin/institutions.php', 'icon' => 'bi-buildings', 'label' => 'Institutions'],
    ['key' => 'users', 'href' => '/super-admin/users.php', 'icon' => 'bi-people', 'label' => 'Users'],
    ['key' => 'courses', 'href' => '/super-admin/courses.php', 'icon' => 'bi-journal-bookmark', 'label' => 'Courses'],
    ['key' => 'reports', 'href' => '/super-admin/reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Reports'],
    ['section' => 'Billing'],
    ['key' => 'plans', 'href' => '/super-admin/plans.php', 'icon' => 'bi-gem', 'label' => 'Plans'],
    ['key' => 'subscriptions', 'href' => '/super-admin/subscriptions.php', 'icon' => 'bi-arrow-repeat', 'label' => 'Subscriptions'],
    ['key' => 'payments', 'href' => '/super-admin/payments.php', 'icon' => 'bi-credit-card', 'label' => 'Payments'],
    ['key' => 'invoices', 'href' => '/super-admin/invoices.php', 'icon' => 'bi-receipt', 'label' => 'Invoices'],
    ['key' => 'coupons', 'href' => '/super-admin/coupons.php', 'icon' => 'bi-ticket-percent', 'label' => 'Coupons'],
    ['section' => 'Support & System'],
    ['key' => 'tickets', 'href' => '/super-admin/tickets.php', 'icon' => 'bi-life-preserver', 'label' => 'Support Tickets'],
    ['key' => 'announcements', 'href' => '/super-admin/announcements.php', 'icon' => 'bi-megaphone', 'label' => 'Announcements'],
    ['key' => 'logs', 'href' => '/super-admin/logs.php', 'icon' => 'bi-clock-history', 'label' => 'Activity & Audit Logs'],
    ['key' => 'settings', 'href' => '/super-admin/settings.php', 'icon' => 'bi-gear', 'label' => 'Platform Settings'],
];
?>
<aside class="sidebar super" id="appSidebar">
  <a class="sidebar-brand" href="/super-admin/index.php">
    <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="brand-text">Lurni <span class="opacity-75">Platform</span></span>
  </a>
  <nav class="sidebar-nav">
    <?php foreach ($items as $item): ?>
      <?php if (isset($item['section'])): ?><div class="nav-section"><?= e($item['section']) ?></div>
      <?php else: ?><a class="nav-link-app <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span></a><?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a class="nav-link-app" href="/index.php"><i class="bi bi-globe"></i><span>View website</span></a>
    <a class="nav-link-app" href="/auth/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
