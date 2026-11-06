<?php
declare(strict_types=1);
$pageTitle = 'Course Categories';
$sitePage = 'categories';
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$cats = [];
try {
    $cats = $pdo->query("SELECT c.name, c.slug, MAX(c.icon) AS icon, MAX(c.color) AS color, MAX(c.description) AS description,
        COUNT(DISTINCT co.id) AS course_count,
        COUNT(DISTINCT en.id) AS learners
        FROM categories c
        LEFT JOIN courses co ON co.category_id=c.id AND co.status='published'
        LEFT JOIN enrollments en ON en.course_id=co.id
        WHERE c.status='active' GROUP BY c.name, c.slug ORDER BY course_count DESC")->fetchAll();
} catch (Throwable $ignored) {}
?>
<section class="page-hero"><div class="container">
  <h1>Course categories</h1>
  <p class="text-muted mb-0">Find the right path — every category is taught by verified instructors.</p>
</div></section>
<section class="section-pad pt-4"><div class="container">
  <div class="row g-4">
    <?php foreach ($cats as $cat): ?>
    <div class="col-md-6 col-lg-4">
      <a class="category-card lg" href="/courses.php?category=<?= e($cat['slug']) ?>">
        <span class="cat-icon" style="--cat:<?= e($cat['color'] ?: '#4f46e5') ?>"><i class="bi <?= e($cat['icon'] ?: 'bi-grid') ?>"></i></span>
        <span class="fw-bold fs-5 d-block mt-2"><?= e($cat['name']) ?></span>
        <span class="text-muted d-block"><?= e($cat['description'] ?? '') ?></span>
        <span class="small text-muted mt-2 d-block"><?= (int)$cat['course_count'] ?> courses · <?= (int)$cat['learners'] ?> learners</span>
      </a>
    </div>
    <?php endforeach; ?>
    <?php if (!$cats): ?><p class="text-muted">No categories yet.</p><?php endif; ?>
  </div>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
