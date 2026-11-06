<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Browse Courses'; $active = 'browse';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();

$q = getp('q'); $catSlug = getp('category');
$cats = $pdo->prepare("SELECT name, slug FROM categories WHERE tenant_id=? AND status='active' ORDER BY name");
$cats->execute([$tid]); $cats = $cats->fetchAll();
$where = ['c.tenant_id=?', "c.status='published'", "c.approval_status='approved'", "c.visibility<>'private'"]; $args = [$tid];
if ($q !== '') { $where[] = '(c.title LIKE ? OR c.subtitle LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%"]); }
if ($catSlug !== '') { $where[] = 'cat.slug=?'; $args[] = $catSlug; }
$s = $pdo->prepare("SELECT c.*, cat.name AS category_name, u.name AS instructor_name,
    (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id) AS lesson_count,
    (SELECT COUNT(*) FROM enrollments e2 WHERE e2.course_id=c.id) AS learners,
    (SELECT COUNT(*) FROM enrollments e3 WHERE e3.course_id=c.id AND e3.student_id=? AND e3.status IN ('active','completed')) AS enrolled,
    (SELECT COUNT(*) FROM enrollments e4 WHERE e4.course_id=c.id AND e4.student_id=? AND e4.status='pending') AS enrollment_pending
    FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id
    LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
    WHERE " . implode(' AND ', $where) . ' ORDER BY c.is_featured DESC, c.rating DESC LIMIT 60');
$s->execute(array_merge([$sid, $sid], $args)); $rows = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<form class="hero-search mb-3" method="get" style="max-width:640px">
  <i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search your academy's courses…">
  <button class="btn btn-gradient" type="submit">Search</button>
</form>
<div class="d-flex flex-wrap gap-2 mb-4">
  <a class="filter-chip <?= $catSlug === '' ? 'active' : '' ?>" href="/student/browse.php">All</a>
  <?php foreach ($cats as $cat): ?><a class="filter-chip <?= $catSlug === $cat['slug'] ? 'active' : '' ?>" href="/student/browse.php?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a><?php endforeach; ?>
</div>
<div class="row g-4">
  <?php foreach ($rows as $c): ?>
  <div class="col-md-6 col-xl-4"><article class="course-card">
    <a href="/student/course.php?id=<?= (int)$c['id'] ?>"><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="course-thumb" alt=""></a>
    <div class="course-body">
      <div class="d-flex justify-content-between"><span class="badge bg-primary-subtle text-primary"><?= e($c['category_name'] ?? 'General') ?></span>
      <span class="small text-warning fw-semibold">★ <?= number_format((float)$c['rating'], 1) ?></span></div>
      <a class="course-title" href="/student/course.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a>
      <div class="course-meta"><span><i class="bi bi-person-video3"></i> <?= e($c['instructor_name'] ?? 'Instructor') ?></span><span><i class="bi bi-play-btn"></i> <?= (int)$c['lesson_count'] ?> lessons</span><span><i class="bi bi-people"></i> <?= (int)$c['learners'] ?></span></div>
      <div class="course-foot"><span class="price"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?></span>
      <?php if ((int)$c['enrolled']): ?><a class="btn btn-sm btn-success" href="/student/course.php?id=<?= (int)$c['id'] ?>">Continue</a>
      <?php elseif ((int)$c['enrollment_pending']): ?><a class="btn btn-sm btn-warning" href="/student/payments.php"><i class="bi bi-hourglass-split"></i> Awaiting verification</a>
      <?php elseif ((float)$c['price'] > 0): ?><button class="btn btn-sm btn-gradient" data-bs-toggle="modal" data-bs-target="#buyModal<?= (int)$c['id'] ?>"><i class="bi bi-cart"></i> Buy course</button>
      <?php else: ?><form method="post" action="/api/enroll.php" class="d-inline"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-gradient">Enroll free</button></form><?php endif; ?>
      </div>
      <?php if ((float)$c['price'] > 0 && !(int)$c['enrolled'] && !(int)$c['enrollment_pending']): ?>
      <div class="modal fade" id="buyModal<?= (int)$c['id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" action="/api/purchase.php" class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Buy “<?= e($c['title']) ?>”</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
          <div class="d-flex justify-content-between mb-3"><span class="text-muted">Price</span><strong><?= money((float)$c['price']) ?></strong></div>
          <label class="form-label">Coupon code (optional)</label>
          <input class="form-control" name="coupon_code" placeholder="e.g. WELCOME10">
          <p class="text-muted small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Your purchase is recorded as a <strong>pending payment</strong>. The course unlocks once your academy verifies the payment — no charge is faked or auto-confirmed.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-gradient" type="submit">Record purchase</button></div>
      </form></div></div>
      <?php endif; ?>
    </div></article></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="col-12"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">No published courses match your search.</p></div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
