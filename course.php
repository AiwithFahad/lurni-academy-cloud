<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$course = null; $modules = []; $lessonsByModule = []; $reviews = []; $related = [];
try {
    $s = $pdo->prepare("SELECT c.*, cat.name AS category_name, u.name AS instructor_name, i.id AS ins_id, i.title AS instructor_title, i.bio AS instructor_bio,
        (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners
        FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id
        LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
        WHERE c.id=? AND c.status='published' AND c.approval_status='approved' AND c.visibility<>'private'");
    $s->execute([$id]); $course = $s->fetch() ?: null;
    if ($course) {
        $s = $pdo->prepare('SELECT * FROM course_modules WHERE course_id=? ORDER BY sort_order, id');
        $s->execute([$id]); $modules = $s->fetchAll();
        $s = $pdo->prepare('SELECT * FROM lessons WHERE course_id=? ORDER BY sort_order, id');
        $s->execute([$id]);
        foreach ($s->fetchAll() as $l) $lessonsByModule[(int)($l['module_id'] ?? 0)][] = $l;
        $s = $pdo->prepare("SELECT r.*, u.name AS student_name FROM reviews r JOIN students st ON st.id=r.student_id JOIN users u ON u.id=st.user_id WHERE r.course_id=? AND r.status='published' ORDER BY r.created_at DESC LIMIT 10");
        $s->execute([$id]); $reviews = $s->fetchAll();
        $s = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners FROM courses c WHERE c.category_id=? AND c.id<>? AND c.status='published' AND c.approval_status='approved' AND c.visibility='public' LIMIT 3");
        $s->execute([$course['category_id'], $id]); $related = $s->fetchAll();
    }
} catch (Throwable $ignored) {}
$pageTitle = $course ? $course['title'] : 'Course not found';
$totalLessons = array_sum(array_map('count', $lessonsByModule));
$totalMinutes = 0; foreach ($lessonsByModule as $ls) foreach ($ls as $l) $totalMinutes += (int)$l['duration_minutes'];
$user = current_user();
$enrolled = false;
if ($course && $user && $user['role'] === 'student') {
    $s = $pdo->prepare('SELECT COUNT(*) FROM enrollments en JOIN students st ON st.id=en.student_id WHERE st.user_id=? AND en.course_id=?');
    $s->execute([$user['id'], $id]); $enrolled = (int)$s->fetchColumn() > 0;
}
?>
<?php if (!$course): ?>
<section class="section-pad"><div class="container"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><h1 class="h4 fw-bold mt-3">Course not found</h1><p class="text-muted">This course may have been unpublished or removed.</p><a class="btn btn-gradient" href="/courses.php">Browse courses</a></div></div></section>
<?php else: ?>
<section class="page-hero">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8">
        <span class="badge bg-primary-subtle text-primary mb-2"><?= e($course['category_name'] ?? 'General') ?></span>
        <h1><?= e($course['title']) ?></h1>
        <p class="lead text-muted"><?= e($course['subtitle'] ?? '') ?></p>
        <div class="d-flex flex-wrap gap-3 text-muted small">
          <span class="text-warning fw-semibold">★ <?= number_format((float)$course['rating'], 1) ?> rating</span>
          <span><i class="bi bi-people"></i> <?= (int)$course['learners'] ?> learners</span>
          <span><i class="bi bi-play-btn"></i> <?= $totalLessons ?> lessons · <?= $totalMinutes ?> minutes</span>
          <span class="text-capitalize"><i class="bi bi-bar-chart"></i> <?= e(str_replace('_', ' ', $course['level'])) ?></span>
          <span><i class="bi bi-translate"></i> <?= e($course['language']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 mt-3">
          <span class="avatar-circle"><?= e(initials($course['instructor_name'] ?? 'I')) ?></span>
          <span>Created by <a href="/instructor.php?id=<?= (int)($course['ins_id'] ?? 0) ?>"><strong><?= e($course['instructor_name'] ?? 'Instructor') ?></strong></a> · <?= e($course['instructor_title'] ?? '') ?></span>
        </div>
        <div class="card p-4 mt-4">
          <h2 class="h5 fw-bold">About this course</h2>
          <p class="text-muted mb-0"><?= nl2br(e($course['description'] ?? '')) ?></p>
          <?php if (!empty($course['objectives'])): ?>
          <h2 class="h5 fw-bold mt-4 mb-2">What you will learn</h2>
          <ul class="list-unstyled row row-cols-md-2 g-2 mb-0">
            <?php foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$course['objectives']))) as $obj): ?>
            <li class="col"><i class="bi bi-check-circle-fill text-success me-1"></i><?= e($obj) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if (!empty($course['requirements'])): ?>
          <h2 class="h5 fw-bold mt-4 mb-2">Requirements</h2>
          <ul class="mb-0">
            <?php foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$course['requirements']))) as $req): ?>
            <li><?= e($req) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
        <div class="card p-4 mt-4">
          <h2 class="h5 fw-bold mb-3">Course curriculum</h2>
          <?php foreach ($modules as $m): ?>
            <h3 class="h6 fw-bold mt-3"><i class="bi bi-collection me-2 text-primary"></i><?= e($m['title']) ?></h3>
            <ul class="lesson-list">
              <?php foreach ($lessonsByModule[(int)$m['id']] ?? [] as $l): ?>
              <li><i class="bi <?= $l['type'] === 'video' ? 'bi-play-circle' : ($l['type'] === 'quiz' ? 'bi-patch-question' : 'bi-file-text') ?>"></i>
                <span class="flex-grow-1"><?= e($l['title']) ?></span>
                <?php if ((int)$l['is_preview']): ?><span class="badge bg-success-subtle text-success">Free preview</span><?php endif; ?>
                <small class="text-muted"><?= (int)$l['duration_minutes'] ?> min</small></li>
              <?php endforeach; ?>
            </ul>
          <?php endforeach; ?>
          <?php if (!empty($lessonsByModule[0])): ?>
            <h3 class="h6 fw-bold mt-3"><i class="bi bi-collection me-2 text-primary"></i>Lessons</h3>
            <ul class="lesson-list"><?php foreach ($lessonsByModule[0] as $l): ?>
              <li><i class="bi bi-play-circle"></i><span class="flex-grow-1"><?= e($l['title']) ?></span><small class="text-muted"><?= (int)$l['duration_minutes'] ?> min</small></li>
            <?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if (!$modules && empty($lessonsByModule[0])): ?><p class="text-muted mb-0">Curriculum is being prepared by the instructor.</p><?php endif; ?>
        </div>
        <div class="card p-4 mt-4">
          <h2 class="h5 fw-bold mb-3">Student reviews</h2>
          <?php foreach ($reviews as $r): ?>
          <div class="d-flex gap-3 mb-3">
            <span class="avatar-circle"><?= e(initials($r['student_name'])) ?></span>
            <div><div class="text-warning small"><?= stars((float)$r['rating']) ?></div>
            <strong><?= e($r['student_name']) ?></strong> <small class="text-muted">· <?= e(time_ago($r['created_at'])) ?></small>
            <p class="text-muted mb-0"><?= e($r['comment']) ?></p></div>
          </div>
          <?php endforeach; ?>
          <?php if (!$reviews): ?><p class="text-muted mb-0">No reviews yet — be the first to share your experience.</p><?php endif; ?>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card enroll-card sticky-top" style="top:96px">
          <img src="<?= e($course['thumbnail'] ?: '/assets/img/course-default.svg') ?>" alt="<?= e($course['title']) ?>" class="course-thumb rounded-top">
          <div class="p-4">
            <div class="price fs-3"><?= (float)$course['price'] > 0 ? money($course['price']) : 'Free' ?><?php if (!empty($course['old_price'])): ?> <del class="fs-6"><?= money($course['old_price']) ?></del><?php endif; ?></div>
            <?php if ($enrolled): ?>
              <a class="btn btn-success w-100 mt-3" href="/student/my-learning.php"><i class="bi bi-play-circle me-1"></i>Continue learning</a>
            <?php elseif ($user && $user['role'] === 'student'): ?>
              <form method="post" action="/api/enroll.php" class="mt-3">
                <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
                <button class="btn btn-gradient w-100" type="submit">Enroll now</button>
              </form>
            <?php else: ?>
              <a class="btn btn-gradient w-100 mt-3" href="/auth/login.php">Login to enroll</a>
              <p class="text-muted small text-center mt-2 mb-0">Students enroll free during the demo period.</p>
            <?php endif; ?>
            <ul class="check-list small mt-3">
              <li><i class="bi bi-check-circle-fill"></i> <?= $totalLessons ?> lessons with previews</li>
              <li><i class="bi bi-check-circle-fill"></i> Quizzes & assignments included</li>
              <li><i class="bi bi-check-circle-fill"></i> Downloadable resources</li>
              <li><i class="bi bi-check-circle-fill"></i> Certificate of completion</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php if ($related): ?>
<section class="section-pad pt-0"><div class="container">
  <h2 class="h4 fw-bold mb-4">Related courses</h2>
  <div class="row g-4">
    <?php foreach ($related as $c): ?>
    <div class="col-md-4"><article class="course-card">
      <a href="/course.php?id=<?= (int)$c['id'] ?>"><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" alt="" class="course-thumb"></a>
      <div class="course-body"><a class="course-title" href="/course.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a>
      <div class="course-foot"><span class="price"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?></span><a class="btn btn-sm btn-gradient" href="/course.php?id=<?= (int)$c['id'] ?>">View</a></div></div>
    </article></div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
