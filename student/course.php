<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();

$courseId = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT c.*, cat.name AS category_name, u.name AS instructor_name FROM courses c
    LEFT JOIN categories cat ON cat.id=c.category_id LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
    WHERE c.id=? AND c.tenant_id=?");
$s->execute([$courseId, $tid]); $course = $s->fetch() ?: null;
if (!$course) { http_response_code(404); exit('Course not found in your academy.'); }
$pageTitle = $course['title']; $active = 'learning';

$s = $pdo->prepare('SELECT * FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=?');
$s->execute([$sid, $courseId, $tid]); $enrollment = $s->fetch() ?: null;

$s = $pdo->prepare('SELECT * FROM course_modules WHERE course_id=? AND tenant_id=? ORDER BY sort_order, id');
$s->execute([$courseId, $tid]); $modules = $s->fetchAll();
$s = $pdo->prepare('SELECT * FROM lessons WHERE course_id=? AND tenant_id=? ORDER BY sort_order, id');
$s->execute([$courseId, $tid]); $lessons = $s->fetchAll();
$done = [];
if ($enrollment) {
    $s = $pdo->prepare("SELECT lesson_id FROM lesson_progress WHERE student_id=? AND course_id=? AND status='completed'");
    $s->execute([$sid, $courseId]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $lid) $done[(int)$lid] = true;
}
$lessonId = (int)($_GET['lesson'] ?? 0);
$current = null;
foreach ($lessons as $l) if ((int)$l['id'] === $lessonId) $current = $l;
if (!$current && $lessons) {
    // First incomplete lesson, else the first lesson.
    foreach ($lessons as $l) if (!isset($done[(int)$l['id']])) { $current = $l; break; }
    if (!$current) $current = $lessons[0];
}
$s = $pdo->prepare('SELECT * FROM resources WHERE course_id=? AND tenant_id=? ORDER BY created_at DESC');
$s->execute([$courseId, $tid]); $resources = $s->fetchAll();
$s = $pdo->prepare("SELECT qz.*, (SELECT COUNT(*) FROM questions q WHERE q.quiz_id=qz.id) AS qc FROM quizzes qz WHERE qz.course_id=? AND qz.tenant_id=? AND qz.status='published'");
$s->execute([$courseId, $tid]); $quizzes = $s->fetchAll();
$byModule = [];
foreach ($lessons as $l) $byModule[(int)($l['module_id'] ?? 0)][] = $l;
$canAccess = fn(array $l) => $enrollment || (int)$l['is_preview'] === 1;
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-3">
  <span class="badge bg-primary-subtle text-primary"><?= e($course['category_name'] ?? 'Course') ?></span>
  <h2 class="h4 fw-bold d-inline ms-1"><?= e($course['title']) ?></h2>
  <div class="text-muted small">by <?= e($course['instructor_name'] ?? 'Instructor') ?> · <?= count($lessons) ?> lessons
  <?php if ($enrollment): ?> · your progress <strong><?= (float)$enrollment['progress_percent'] ?>%</strong><?php endif; ?></div>
  <?php if ($enrollment): ?><div class="progress progress-slim mt-2" style="max-width:520px"><div class="progress-bar" style="width:<?= (float)$enrollment['progress_percent'] ?>%"></div></div>
  <?php else: ?><form method="post" action="/api/enroll.php" class="mt-2"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= $courseId ?>"><button class="btn btn-gradient">Enroll free to unlock all lessons</button></form><?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <?php if ($current): ?>
    <div class="card mb-3"><div class="card-body">
      <h3 class="h5 fw-bold"><?= e($current['title']) ?></h3>
      <?php if (!$canAccess($current)): ?>
        <div class="alert alert-warning"><i class="bi bi-lock me-1"></i>This lesson is locked. Enroll in the course to watch it.</div>
      <?php else: ?>
        <?php if ($current['type'] === 'video' && $current['video_url']): ?>
        <div class="ratio ratio-16x9 rounded overflow-hidden mb-3"><iframe src="<?= e($current['video_url']) ?>" title="<?= e($current['title']) ?>" allowfullscreen loading="lazy"></iframe></div>
        <?php endif; ?>
        <?php if ($current['content']): ?><div class="lesson-content"><?= nl2br(e($current['content'])) ?></div><?php endif; ?>
        <?php if ($current['type'] === 'quiz'): ?><p class="text-muted">This lesson is assessed by quiz — find it in the quizzes list for this course.</p><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <span class="text-muted small"><i class="bi bi-clock"></i> <?= (int)$current['duration_minutes'] ?> minutes · <?= e($current['type']) ?></span>
          <?php if ($enrollment): ?>
            <?php if (isset($done[(int)$current['id']])): ?><span class="badge bg-success-subtle text-success fs-6"><i class="bi bi-check-circle me-1"></i>Completed</span>
            <?php else: ?><form method="post" action="/api/progress.php"><?= csrf_field() ?>
              <input type="hidden" name="lesson_id" value="<?= (int)$current['id'] ?>">
              <input type="hidden" name="redirect" value="/student/course.php?id=<?= $courseId ?>&lesson=<?= (int)$current['id'] ?>">
              <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Mark as complete</button></form><?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <?php if ($quizzes): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-bold">Quizzes in this course</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($quizzes as $qz): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><strong><?= e($qz['title']) ?></strong><br><small class="text-muted"><?= (int)$qz['qc'] ?> questions · pass <?= (int)$qz['pass_percent'] ?>%</small></span>
        <?php if ($enrollment): ?><a class="btn btn-sm btn-gradient" href="/student/take-quiz.php?id=<?= (int)$qz['id'] ?>">Take quiz</a><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Enroll first</span><?php endif; ?></li>
      <?php endforeach; ?>
      </ul></div>
    <?php endif; ?>

    <?php if ($resources): ?>
    <div class="card"><div class="card-header bg-white fw-bold">Downloadable resources</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($resources as $r): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-file-earmark-arrow-down text-primary me-2"></i><?= e($r['title']) ?> <small class="text-muted">(<?= e($r['file_type'] ?? '') ?> · <?= number_format(((int)$r['file_size']) / 1048576, 2) ?> MB)</small></span>
        <?php if ($enrollment): ?><a class="btn btn-sm btn-outline-primary" href="/<?= e($r['file_path']) ?>" download>Download</a><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Enroll first</span><?php endif; ?></li>
      <?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
  </div>

  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold">Curriculum</div>
    <div class="curriculum">
    <?php $renderLessons = function (array $ls) use ($courseId, $current, $done, $canAccess) {
        foreach ($ls as $l): ?>
        <a class="curriculum-item <?= $current && (int)$current['id'] === (int)$l['id'] ? 'active' : '' ?>" href="/student/course.php?id=<?= $courseId ?>&lesson=<?= (int)$l['id'] ?>">
          <i class="bi <?= isset($done[(int)$l['id']]) ? 'bi-check-circle-fill text-success' : ($canAccess($l) ? 'bi-play-circle' : 'bi-lock') ?>"></i>
          <span class="flex-grow-1"><?= e($l['title']) ?></span>
          <small class="text-muted"><?= (int)$l['duration_minutes'] ?>m</small>
        </a>
    <?php endforeach; }; ?>
    <?php foreach ($modules as $m): ?>
      <div class="curriculum-module"><?= e($m['title']) ?></div>
      <?php $renderLessons($byModule[(int)$m['id']] ?? []); ?>
    <?php endforeach; ?>
    <?php if (!empty($byModule[0])): ?><div class="curriculum-module">Lessons</div><?php $renderLessons($byModule[0]); endif; ?>
    <?php if (!$lessons): ?><p class="text-muted p-3 mb-0">The instructor has not added lessons yet.</p><?php endif; ?>
    </div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
