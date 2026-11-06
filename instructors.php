<?php
declare(strict_types=1);
$pageTitle = 'Our Instructors';
$sitePage = 'instructors';
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$rows = [];
try {
    $rows = $pdo->query("SELECT i.*, u.name,
        (SELECT COUNT(*) FROM courses c WHERE c.instructor_id=i.id AND c.status='published') AS course_count,
        (SELECT COUNT(*) FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE c.instructor_id=i.id) AS learners,
        (SELECT COALESCE(AVG(c.rating),0) FROM courses c WHERE c.instructor_id=i.id AND c.status='published') AS avg_rating
        FROM instructors i JOIN users u ON u.id=i.user_id WHERE i.status='active' ORDER BY avg_rating DESC")->fetchAll();
} catch (Throwable $ignored) {}
?>
<section class="page-hero"><div class="container">
  <h1>Meet your instructors</h1>
  <p class="text-muted mb-0">Industry practitioners who teach what they actually do every day.</p>
</div></section>
<section class="section-pad pt-4"><div class="container">
  <div class="row g-4">
    <?php foreach ($rows as $ins): ?>
    <div class="col-md-6 col-lg-4">
      <article class="instructor-card text-start">
        <div class="d-flex align-items-center gap-3">
          <span class="avatar-circle lg"><?= e(initials($ins['name'])) ?></span>
          <div><h2 class="h6 fw-bold mb-0"><?= e($ins['name']) ?></h2><small class="text-muted"><?= e($ins['title'] ?? 'Instructor') ?></small></div>
        </div>
        <p class="text-muted small mt-3"><?= e(mb_strimwidth((string)($ins['bio'] ?? ''), 0, 140, '…')) ?></p>
        <div class="d-flex gap-3 small">
          <span class="text-warning fw-semibold">★ <?= number_format((float)$ins['avg_rating'], 1) ?></span>
          <span><i class="bi bi-journal-bookmark"></i> <?= (int)$ins['course_count'] ?> courses</span>
          <span><i class="bi bi-people"></i> <?= (int)$ins['learners'] ?> learners</span>
        </div>
        <?php if (!empty($ins['expertise'])): ?><div class="mt-2"><?php foreach (array_slice(explode(',', $ins['expertise']), 0, 4) as $tag): ?><span class="badge bg-primary-subtle text-primary me-1"><?= e(trim($tag)) ?></span><?php endforeach; ?></div><?php endif; ?>
        <a class="btn btn-outline-primary btn-sm mt-3" href="/instructor.php?id=<?= (int)$ins['id'] ?>">View profile & courses</a>
      </article>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="text-muted">Instructor profiles will appear here soon.</p><?php endif; ?>
  </div>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
