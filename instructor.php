<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$ins = null; $courses = [];
try {
    $s = $pdo->prepare("SELECT i.*, u.name,
        (SELECT COUNT(*) FROM courses c WHERE c.instructor_id=i.id AND c.status='published') AS course_count,
        (SELECT COUNT(*) FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE c.instructor_id=i.id) AS learners,
        (SELECT COALESCE(AVG(c.rating),0) FROM courses c WHERE c.instructor_id=i.id AND c.status='published') AS avg_rating
        FROM instructors i JOIN users u ON u.id=i.user_id WHERE i.id=? AND i.status='active'");
    $s->execute([$id]); $ins = $s->fetch() ?: null;
    if ($ins) {
        $s = $pdo->prepare("SELECT c.*, cat.name AS category_name, (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners
            FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.instructor_id=? AND c.status='published' ORDER BY c.rating DESC");
        $s->execute([$id]); $courses = $s->fetchAll();
    }
} catch (Throwable $ignored) {}
$pageTitle = $ins ? $ins['name'] . ' — Instructor' : 'Instructor not found';
?>
<?php if (!$ins): ?>
<section class="section-pad"><div class="container"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><h1 class="h4 fw-bold mt-3">Instructor not found</h1><a class="btn btn-gradient" href="/instructors.php">All instructors</a></div></div></section>
<?php else: ?>
<section class="page-hero"><div class="container">
  <div class="d-flex flex-wrap align-items-center gap-4">
    <span class="avatar-circle xl"><?= e(initials($ins['name'])) ?></span>
    <div>
      <span class="section-eyebrow">Instructor</span>
      <h1 class="mb-1"><?= e($ins['name']) ?></h1>
      <p class="text-muted mb-2"><?= e($ins['title'] ?? '') ?></p>
      <div class="d-flex gap-3 small">
        <span class="text-warning fw-semibold">★ <?= number_format((float)$ins['avg_rating'], 1) ?> average rating</span>
        <span><i class="bi bi-journal-bookmark"></i> <?= (int)$ins['course_count'] ?> courses</span>
        <span><i class="bi bi-people"></i> <?= (int)$ins['learners'] ?> learners</span>
      </div>
    </div>
  </div>
  <div class="card p-4 mt-4"><h2 class="h6 fw-bold">About</h2><p class="text-muted mb-2"><?= nl2br(e($ins['bio'] ?? '')) ?></p>
  <?php if (!empty($ins['expertise'])): ?><div><?php foreach (explode(',', $ins['expertise']) as $tag): ?><span class="badge bg-primary-subtle text-primary me-1"><?= e(trim($tag)) ?></span><?php endforeach; ?></div><?php endif; ?>
  </div>
</div></section>
<section class="section-pad pt-0"><div class="container">
  <h2 class="h4 fw-bold mb-4">Courses by <?= e($ins['name']) ?></h2>
  <div class="row g-4">
    <?php foreach ($courses as $c): ?>
    <div class="col-md-6 col-lg-4"><article class="course-card">
      <a href="/course.php?id=<?= (int)$c['id'] ?>"><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" alt="" class="course-thumb"></a>
      <div class="course-body">
        <span class="badge bg-primary-subtle text-primary"><?= e($c['category_name'] ?? 'General') ?></span>
        <a class="course-title" href="/course.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a>
        <div class="course-meta"><span class="text-warning fw-semibold">★ <?= number_format((float)$c['rating'], 1) ?></span><span><i class="bi bi-people"></i> <?= (int)$c['learners'] ?> learners</span></div>
        <div class="course-foot"><span class="price"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?></span><a class="btn btn-sm btn-gradient" href="/course.php?id=<?= (int)$c['id'] ?>">View course</a></div>
      </div>
    </article></div>
    <?php endforeach; ?>
    <?php if (!$courses): ?><p class="text-muted">No published courses yet.</p><?php endif; ?>
  </div>
</div></section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
