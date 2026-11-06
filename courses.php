<?php
declare(strict_types=1);
$pageTitle = 'Browse Courses';
$sitePage = 'courses';
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$q = getp('q'); $catSlug = getp('category'); $level = getp('level');
$cats = []; $rows = []; $total = 0;
try {
    $cats = $pdo->query("SELECT name, slug FROM categories WHERE status='active' GROUP BY name, slug ORDER BY name")->fetchAll();
    $where = ["c.status='published'", "c.approval_status='approved'", "c.visibility='public'"]; $args = [];
    if ($q !== '') { $where[] = '(c.title LIKE ? OR c.subtitle LIKE ? OR c.description LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%", "%$q%"]); }
    if ($catSlug !== '') { $where[] = 'cat.slug=?'; $args[] = $catSlug; }
    if (in_array($level, ['beginner', 'intermediate', 'advanced', 'all_levels'], true)) { $where[] = 'c.level=?'; $args[] = $level; }
    $sqlWhere = implode(' AND ', $where);
    $s = $pdo->prepare("SELECT COUNT(*) FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id WHERE $sqlWhere");
    $s->execute($args); $total = (int)$s->fetchColumn();
    $pg = paginate($total, 12);
    $s = $pdo->prepare("SELECT c.*, cat.name AS category_name, u.name AS instructor_name,
        (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners,
        (SELECT COUNT(*) FROM reviews r WHERE r.course_id=c.id AND r.status='published') AS review_count
        FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id
        LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
        WHERE $sqlWhere ORDER BY c.is_featured DESC, c.rating DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
    $s->execute($args); $rows = $s->fetchAll();
} catch (Throwable $ignored) {}
$queryBase = '?' . http_build_query(array_filter(['q' => $q, 'category' => $catSlug, 'level' => $level]));
?>
<section class="page-hero">
  <div class="container">
    <h1>Browse courses</h1>
    <p class="text-muted mb-0"><?= $total ?> published course<?= $total === 1 ? '' : 's' ?> from verified academies and instructors.</p>
    <form class="hero-search mt-4" action="/courses.php" method="get" style="max-width:640px">
      <i class="bi bi-search"></i>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search courses…">
      <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
      <button class="btn btn-gradient" type="submit">Search</button>
    </form>
  </div>
</section>
<section class="section-pad pt-4">
  <div class="container">
    <div class="d-flex flex-wrap gap-2 mb-4">
      <a class="filter-chip <?= $catSlug === '' ? 'active' : '' ?>" href="/courses.php<?= $q ? '?q=' . urlencode($q) : '' ?>">All</a>
      <?php foreach ($cats as $cat): ?>
      <a class="filter-chip <?= $catSlug === $cat['slug'] ? 'active' : '' ?>" href="/courses.php?category=<?= e($cat['slug']) ?><?= $q ? '&q=' . urlencode($q) : '' ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
      <span class="ms-auto d-flex gap-2">
        <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $lv => $label): ?>
        <a class="filter-chip <?= $level === $lv ? 'active' : '' ?>" href="/courses.php?level=<?= $lv ?>&category=<?= e($catSlug) ?>&q=<?= e($q) ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </span>
    </div>
    <div class="row g-4">
      <?php foreach ($rows as $c): ?>
      <div class="col-md-6 col-lg-4">
        <article class="course-card">
          <a href="/course.php?id=<?= (int)$c['id'] ?>"><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" alt="<?= e($c['title']) ?>" class="course-thumb"></a>
          <div class="course-body">
            <div class="d-flex justify-content-between align-items-center">
              <span class="badge bg-primary-subtle text-primary"><?= e($c['category_name'] ?? 'General') ?></span>
              <span class="small text-warning fw-semibold">★ <?= number_format((float)$c['rating'], 1) ?> <span class="text-muted fw-normal">(<?= (int)$c['review_count'] ?>)</span></span>
            </div>
            <a class="course-title" href="/course.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a>
            <div class="course-meta"><span><i class="bi bi-person-video3"></i> <?= e($c['instructor_name'] ?? 'Instructor') ?></span><span class="text-capitalize"><i class="bi bi-bar-chart"></i> <?= e(str_replace('_', ' ', $c['level'])) ?></span><span><i class="bi bi-people"></i> <?= (int)$c['learners'] ?></span></div>
            <div class="course-foot">
              <span class="price"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?><?php if (!empty($c['old_price'])): ?> <del><?= money($c['old_price']) ?></del><?php endif; ?></span>
              <a class="btn btn-sm btn-gradient" href="/course.php?id=<?= (int)$c['id'] ?>">View course</a>
            </div>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <div class="col-12"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><h2 class="h5 fw-bold mt-3">No courses found</h2><p class="text-muted">Try a different search or category.</p></div></div>
      <?php endif; ?>
    </div>
    <div class="d-flex justify-content-center mt-4"><?= pagination_html($pg, $queryBase) ?></div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
