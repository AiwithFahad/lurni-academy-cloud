<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Learning'; $active = 'learning';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();

// Review submission (one review per course, updates course rating cache).
if (is_post() && post('action') === 'review') {
    verify_csrf();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $enrolled = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=?');
    $enrolled->execute([$sid, $courseId, $tid]);
    if ((int)$enrolled->fetchColumn() > 0) {
        $pdo->prepare('INSERT INTO reviews(tenant_id,course_id,student_id,rating,comment) VALUES(?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), status="published"')
            ->execute([$tid, $courseId, $sid, $rating, post('comment')]);
        $avg = $pdo->prepare("SELECT COALESCE(AVG(rating),0) FROM reviews WHERE course_id=? AND tenant_id=? AND status='published'");
        $avg->execute([$courseId, $tid]);
        $pdo->prepare('UPDATE courses SET rating=? WHERE id=? AND tenant_id=?')->execute([round((float)$avg->fetchColumn(), 2), $courseId, $tid]);
        flash('success', 'Thanks! Your review was saved.');
    }
    redirect('/student/my-learning.php');
}

$s = $pdo->prepare("SELECT en.*, c.title, c.thumbnail, cat.name AS category_name,
    (SELECT r.rating FROM reviews r WHERE r.course_id=c.id AND r.student_id=? LIMIT 1) AS my_rating
    FROM enrollments en JOIN courses c ON c.id=en.course_id LEFT JOIN categories cat ON cat.id=c.category_id
    WHERE en.student_id=? AND en.tenant_id=? ORDER BY en.enrolled_at DESC");
$s->execute([$sid, $sid, $tid]); $rows = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
  <?php foreach ($rows as $en): ?>
  <div class="col-md-6 col-xl-4"><article class="course-card">
    <a href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><img src="<?= e($en['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="course-thumb" alt=""></a>
    <div class="course-body">
      <div class="d-flex justify-content-between"><span class="badge bg-primary-subtle text-primary"><?= e($en['category_name'] ?? 'Course') ?></span>
      <span class="badge bg-<?= $en['status'] === 'completed' ? 'success' : 'primary' ?>-subtle text-<?= $en['status'] === 'completed' ? 'success' : 'primary' ?>"><?= e($en['status']) ?></span></div>
      <a class="course-title" href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><?= e($en['title']) ?></a>
      <div class="progress progress-slim"><div class="progress-bar" style="width:<?= (float)$en['progress_percent'] ?>%"></div></div>
      <div class="d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= (float)$en['progress_percent'] ?>% · enrolled <?= e(date('M j, Y', strtotime($en['enrolled_at']))) ?></small>
        <a class="btn btn-sm btn-gradient" href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><?= (float)$en['progress_percent'] >= 100 ? 'Review course' : 'Continue' ?></a>
      </div>
      <hr class="my-2">
      <form method="post" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="course_id" value="<?= (int)$en['course_id'] ?>">
        <select class="form-select form-select-sm" name="rating" style="width:auto" aria-label="Your rating">
          <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int)($en['my_rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?> ★</option><?php endfor; ?>
        </select>
        <input class="form-control form-control-sm" name="comment" placeholder="Write a short review…" required>
        <button class="btn btn-sm btn-outline-primary">Save</button>
      </form>
    </div></article></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="col-12"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><h2 class="h6 fw-bold mt-3">No enrollments yet</h2><p class="text-muted">Browse the catalog and enroll in your first course.</p><a class="btn btn-gradient" href="/student/browse.php">Browse courses</a></div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
