<?php
// Role-aware sidebar for the tenant portals. $active = current page key.
$u = current_user();
$role = $u['role'] ?? 'student';
$active = $active ?? '';
$tenantName = $tenantName ?? APP_NAME;

$menus = [
    'admin' => [
        ['section' => 'Overview'],
        ['key' => 'dashboard', 'href' => '/admin/dashboard.php', 'icon' => 'bi-grid-1x2', 'label' => 'Dashboard'],
        ['key' => 'reports', 'href' => '/admin/reports.php', 'icon' => 'bi-bar-chart', 'label' => 'Reports'],
        ['section' => 'People'],
        ['key' => 'students', 'href' => '/admin/students.php', 'icon' => 'bi-people', 'label' => 'Students'],
        ['key' => 'instructors', 'href' => '/admin/instructors.php', 'icon' => 'bi-person-video3', 'label' => 'Instructors'],
        ['section' => 'Learning'],
        ['key' => 'courses', 'href' => '/admin/courses.php', 'icon' => 'bi-journal-bookmark', 'label' => 'Courses'],
        ['key' => 'categories', 'href' => '/admin/categories.php', 'icon' => 'bi-tags', 'label' => 'Categories'],
        ['key' => 'modules', 'href' => '/admin/modules.php', 'icon' => 'bi-collection', 'label' => 'Course Modules'],
        ['key' => 'lessons', 'href' => '/admin/lessons.php', 'icon' => 'bi-play-btn', 'label' => 'Lessons'],
        ['key' => 'resources', 'href' => '/admin/resources.php', 'icon' => 'bi-folder2-open', 'label' => 'Resources'],
        ['key' => 'quizzes', 'href' => '/admin/quizzes.php', 'icon' => 'bi-patch-question', 'label' => 'Quizzes'],
        ['key' => 'questions', 'href' => '/admin/questions.php', 'icon' => 'bi-card-list', 'label' => 'Question Banks'],
        ['key' => 'assignments', 'href' => '/admin/assignments.php', 'icon' => 'bi-pencil-square', 'label' => 'Assignments'],
        ['key' => 'grading', 'href' => '/admin/grading.php', 'icon' => 'bi-check2-square', 'label' => 'Grading'],
        ['section' => 'Operations'],
        ['key' => 'enrollments', 'href' => '/admin/enrollments.php', 'icon' => 'bi-person-check', 'label' => 'Enrollments'],
        ['key' => 'progress', 'href' => '/admin/progress.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Student Progress'],
        ['key' => 'certificates', 'href' => '/admin/certificates.php', 'icon' => 'bi-award', 'label' => 'Certificates'],
        ['key' => 'reviews', 'href' => '/admin/reviews.php', 'icon' => 'bi-star', 'label' => 'Reviews & Ratings'],
        ['key' => 'blog', 'href' => '/admin/blog.php', 'icon' => 'bi-newspaper', 'label' => 'Blog'],
        ['key' => 'payments', 'href' => '/admin/payments.php', 'icon' => 'bi-credit-card', 'label' => 'Payments'],
        ['key' => 'notifications', 'href' => '/admin/notifications.php', 'icon' => 'bi-bell', 'label' => 'Notifications'],
        ['section' => 'Account'],
        ['key' => 'subscription', 'href' => '/admin/subscription.php', 'icon' => 'bi-gem', 'label' => 'Subscription'],
        ['key' => 'settings', 'href' => '/admin/settings.php', 'icon' => 'bi-gear', 'label' => 'Website Settings'],
    ],
    'instructor' => [
        ['section' => 'Teaching'],
        ['key' => 'dashboard', 'href' => '/instructor/dashboard.php', 'icon' => 'bi-grid-1x2', 'label' => 'Dashboard'],
        ['key' => 'courses', 'href' => '/instructor/courses.php', 'icon' => 'bi-journal-bookmark', 'label' => 'My Courses'],
        ['key' => 'lessons', 'href' => '/instructor/lessons.php', 'icon' => 'bi-play-btn', 'label' => 'Lessons'],
        ['key' => 'quizzes', 'href' => '/instructor/quizzes.php', 'icon' => 'bi-patch-question', 'label' => 'Quizzes'],
        ['key' => 'assignments', 'href' => '/instructor/assignments.php', 'icon' => 'bi-pencil-square', 'label' => 'Assignments'],
        ['key' => 'grading', 'href' => '/instructor/grading.php', 'icon' => 'bi-check2-square', 'label' => 'Grading'],
        ['section' => 'Insights'],
        ['key' => 'performance', 'href' => '/instructor/performance.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Performance'],
        ['key' => 'messages', 'href' => '/instructor/messages.php', 'icon' => 'bi-chat-dots', 'label' => 'Messages'],
    ],
    'student' => [
        ['section' => 'Learning'],
        ['key' => 'dashboard', 'href' => '/student/dashboard.php', 'icon' => 'bi-grid-1x2', 'label' => 'Dashboard'],
        ['key' => 'browse', 'href' => '/student/browse.php', 'icon' => 'bi-compass', 'label' => 'Browse Courses'],
        ['key' => 'learning', 'href' => '/student/my-learning.php', 'icon' => 'bi-journal-bookmark', 'label' => 'My Learning'],
        ['key' => 'assignments', 'href' => '/student/assignments.php', 'icon' => 'bi-pencil-square', 'label' => 'Assignments'],
        ['key' => 'quizzes', 'href' => '/student/quizzes.php', 'icon' => 'bi-patch-question', 'label' => 'Quizzes'],
        ['section' => 'Achievement'],
        ['key' => 'progress', 'href' => '/student/progress.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Progress'],
        ['key' => 'grades', 'href' => '/student/grades.php', 'icon' => 'bi-mortarboard', 'label' => 'Grades'],
        ['key' => 'certificates', 'href' => '/student/certificates.php', 'icon' => 'bi-award', 'label' => 'Certificates'],
        ['key' => 'calendar', 'href' => '/student/calendar.php', 'icon' => 'bi-calendar3', 'label' => 'Calendar'],
        ['key' => 'payments', 'href' => '/student/payments.php', 'icon' => 'bi-credit-card', 'label' => 'Payment History'],
        ['key' => 'messages', 'href' => '/student/messages.php', 'icon' => 'bi-chat-dots', 'label' => 'Messages'],
    ],
];
$items = $menus[$role] ?? $menus['student'];
?>
<aside class="sidebar" id="appSidebar">
  <a class="sidebar-brand" href="<?= e(role_home($role)) ?>">
    <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="brand-text"><?= e($tenantName) ?></span>
  </a>
  <nav class="sidebar-nav">
    <?php foreach ($items as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="nav-section"><?= e($item['section']) ?></div>
      <?php else: ?>
        <a class="nav-link-app <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
          <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a class="nav-link-app" href="/index.php"><i class="bi bi-globe"></i><span>View website</span></a>
    <a class="nav-link-app" href="/auth/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
