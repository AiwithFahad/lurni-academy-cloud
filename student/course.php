<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();

$sid = current_student_id($pdo);

$courseId = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT c.*, cat.name AS category_name, u.name AS instructor_name FROM courses c
    LEFT JOIN categories cat ON cat.id=c.category_id LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
    WHERE c.id=? AND c.tenant_id=?");
$s->execute([$courseId, $tid]); $course = $s->fetch() ?: null;
if (!$course) { http_response_code(404); exit('Course not found in your academy.'); }
$pageTitle = $course['title']; $active = 'learning';

$s = $pdo->prepare('SELECT * FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=?');
$s->execute([$sid, $courseId, $tid]); $enrollmentRow = $s->fetch() ?: null;
// Only verified (active/completed) enrollments unlock lessons; a 'pending'
// enrollment means a course purchase is still awaiting payment verification.
$enrollment = ($enrollmentRow && in_array($enrollmentRow['status'], ['active', 'completed'], true)) ? $enrollmentRow : null;
$pendingEnrollment = ($enrollmentRow && $enrollmentRow['status'] === 'pending') ? $enrollmentRow : null;

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
// Previous / next lesson navigation (flat curriculum order).
$prevLesson = null; $nextLesson = null;
if ($current) {
    $idx = null;
    foreach ($lessons as $i => $l) if ((int)$l['id'] === (int)$current['id']) { $idx = $i; break; }
    if ($idx !== null) {
        $prevLesson = $lessons[$idx - 1] ?? null;
        $nextLesson = $lessons[$idx + 1] ?? null;
    }
}
$s = $pdo->prepare('SELECT * FROM resources WHERE course_id=? AND tenant_id=? ORDER BY created_at DESC');
$s->execute([$courseId, $tid]); $resources = $s->fetchAll();
$s = $pdo->prepare("SELECT qz.*, (SELECT COUNT(*) FROM questions q WHERE q.quiz_id=qz.id) AS qc FROM quizzes qz WHERE qz.course_id=? AND qz.tenant_id=? AND qz.status='published'");
$s->execute([$courseId, $tid]); $quizzes = $s->fetchAll();

// Engagement data (upgrade): bookmarks, current note, announcements, Q&A.
$bookmarks = [];
$currentNote = '';
if ($enrollment) {
    $s = $pdo->prepare('SELECT lesson_id FROM lesson_bookmarks WHERE student_id=? AND tenant_id=? AND course_id=?');
    $s->execute([$sid, $tid, $courseId]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $lid) $bookmarks[(int)$lid] = true;
    if ($current) {
        $s = $pdo->prepare('SELECT note FROM lesson_notes WHERE student_id=? AND lesson_id=?');
        $s->execute([$sid, (int)$current['id']]); $currentNote = (string)($s->fetchColumn() ?: '');
    }
}
$s = $pdo->prepare("SELECT ca.*, u.name AS author_name FROM course_announcements ca JOIN users u ON u.id=ca.author_id
    WHERE ca.course_id=? AND ca.tenant_id=? ORDER BY ca.created_at DESC LIMIT 10");
$s->execute([$courseId, $tid]); $announcements = $s->fetchAll();
$threads = [];
if ($enrollment) {
    $s = $pdo->prepare("SELECT d.*, u.name AS author_name, u.role AS author_role FROM discussions d JOIN users u ON u.id=d.user_id
        WHERE d.course_id=? AND d.tenant_id=? AND d.status='published' ORDER BY d.created_at ASC LIMIT 200");
    $s->execute([$courseId, $tid]);
    foreach ($s->fetchAll() as $d) {
        if ($d['parent_id']) { foreach ($threads as &$t) if ((int)$t['id'] === (int)$d['parent_id']) { $t['replies'][] = $d; break; } unset($t); }
        else { $d['replies'] = []; $threads[] = $d; }
    }
}
$byModule = [];
foreach ($lessons as $l) $byModule[(int)($l['module_id'] ?? 0)][] = $l;
$canAccess = fn(array $l) => $enrollment || (int)$l['is_preview'] === 1;
$lessonUrl = fn(array $l) => '/student/course.php?id=' . $courseId . '&lesson=' . (int)$l['id'];
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-3">
  <span class="badge bg-primary-subtle text-primary"><?= e($course['category_name'] ?? 'Course') ?></span>
  <h2 class="h4 fw-bold d-inline ms-1"><?= e($course['title']) ?></h2>
  <div class="text-muted small">by <?= e($course['instructor_name'] ?? 'Instructor') ?> · <?= count($lessons) ?> lessons
  <?php if ($enrollment): ?> · your progress <strong class="js-course-progress-pct"><?= (float)$enrollment['progress_percent'] ?>%</strong><?php endif; ?></div>
  <?php if ($enrollment): ?><div class="progress progress-slim mt-2" style="max-width:520px"><div class="progress-bar js-course-progress-bar" role="progressbar" style="width:<?= (float)$enrollment['progress_percent'] ?>%" aria-valuenow="<?= (float)$enrollment['progress_percent'] ?>"></div></div>
  <?php elseif ($pendingEnrollment): ?><div class="alert alert-warning mt-2 mb-0"><i class="bi bi-hourglass-split me-1"></i>Your purchase for this course is recorded and awaiting payment verification. Lessons unlock automatically once it is confirmed — track it under <a href="/student/payments.php">Payment history</a>.</div>
  <?php else: ?><form method="post" action="/api/enroll.php" class="mt-2"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= $courseId ?>"><button class="btn btn-gradient"><?= (float)$course['price'] > 0 ? 'Buy this course to unlock all lessons' : 'Enroll free to unlock all lessons' ?></button></form><?php endif; ?>
</div>

<?php if ($announcements): ?>
<div class="card mb-3 border-primary-subtle"><div class="card-header bg-white fw-bold"><i class="bi bi-megaphone text-primary"></i> Course announcements</div>
  <ul class="list-group list-group-flush">
  <?php foreach ($announcements as $a): ?>
    <li class="list-group-item"><strong><?= e($a['title']) ?></strong> <small class="text-muted">· <?= e($a['author_name']) ?> · <?= e(date('M j, Y', strtotime((string)$a['created_at']))) ?></small><br><?= nl2br(e($a['body'])) ?></li>
  <?php endforeach; ?>
  </ul></div>
<?php endif; ?>

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
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-3">
          <span class="text-muted small"><i class="bi bi-clock"></i> <?= (int)$current['duration_minutes'] ?> minutes · <?= e($current['type']) ?></span>
          <?php if ($enrollment): ?>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if ($prevLesson): ?><a class="btn btn-outline-secondary" href="<?= e($lessonUrl($prevLesson)) ?>"><i class="bi bi-chevron-left"></i> Previous</a><?php endif; ?>
            <button type="button" class="btn btn-outline-primary js-bookmark-toggle <?= isset($bookmarks[(int)$current['id']]) ? 'active' : '' ?>" data-lesson-id="<?= (int)$current['id'] ?>"><i class="bi <?= isset($bookmarks[(int)$current['id']]) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i> Bookmark</button>
            <?php if (isset($done[(int)$current['id']])): ?><span class="badge bg-success-subtle text-success fs-6"><i class="bi bi-check-circle me-1"></i>Completed</span>
            <?php else: ?>
              <button type="button" class="btn btn-success js-complete-next" data-lesson-id="<?= (int)$current['id'] ?>"<?= $nextLesson ? ' data-next-url="' . e($lessonUrl($nextLesson)) . '"' : '' ?>><i class="bi bi-check-lg me-1"></i><?= $nextLesson ? 'Complete & next' : 'Mark as complete' ?></button>
            <?php endif; ?>
            <?php if ($nextLesson): ?><a class="btn btn-gradient" href="<?= e($lessonUrl($nextLesson)) ?>">Next <i class="bi bi-chevron-right"></i></a><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div></div>

    <?php if ($enrollment): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-bold"><i class="bi bi-journal-text text-primary"></i> My notes for this lesson</div><div class="card-body">
      <form class="js-notes-form" data-lesson-id="<?= (int)$current['id'] ?>">
        <input type="hidden" name="lesson_id" value="<?= (int)$current['id'] ?>">
        <textarea class="form-control" name="note" rows="3" maxlength="5000" placeholder="Write private notes about this lesson…"><?= e($currentNote) ?></textarea>
        <button class="btn btn-outline-primary btn-sm mt-2" type="submit"><i class="bi bi-save"></i> Save note</button>
      </form>
    </div></div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($quizzes): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-bold">Quizzes in this course</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($quizzes as $qz): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><strong><?= e($qz['title']) ?></strong><br><small class="text-muted"><?= (int)$qz['qc'] ?> questions · pass <?= (int)$qz['pass_percent'] ?>%<?= (int)($qz['max_attempts'] ?? 0) ? ' · max ' . (int)$qz['max_attempts'] . ' attempts' : '' ?></small></span>
        <?php if ($enrollment): ?><a class="btn btn-sm btn-gradient" href="/student/take-quiz.php?id=<?= (int)$qz['id'] ?>">Take quiz</a><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Enroll first</span><?php endif; ?></li>
      <?php endforeach; ?>
      </ul></div>
    <?php endif; ?>

    <?php if ($resources): ?>
    <div class="card mb-3"><div class="card-header bg-white fw-bold">Downloadable resources</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($resources as $r): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-file-earmark-arrow-down text-primary me-2"></i><?= e($r['title']) ?> <small class="text-muted">(<?= e($r['file_type'] ?? '') ?> · <?= number_format(((int)$r['file_size']) / 1048576, 2) ?> MB)</small></span>
        <?php if ($enrollment): ?><a class="btn btn-sm btn-outline-primary" href="/<?= e($r['file_path']) ?>" download>Download</a><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Enroll first</span><?php endif; ?></li>
      <?php endforeach; ?>
      </ul></div>
    <?php endif; ?>

    <?php if ($enrollment): ?>
    <div class="card"><div class="card-header bg-white fw-bold"><i class="bi bi-chat-dots text-primary"></i> Discussion &amp; Q&amp;A</div><div class="card-body">
      <div id="discussionList">
      <?php if (!$threads): ?><p class="text-muted js-discussion-empty mb-0">No questions yet — ask the first one below.</p><?php endif; ?>
      <?php foreach ($threads as $t): ?>
        <div class="discussion-post border rounded p-3 mb-2">
          <div class="small text-muted mb-1"><strong class="text-dark"><?= e($t['author_name']) ?></strong> · <?= e($t['author_role']) ?> · <?= e(date('M j, Y H:i', strtotime((string)$t['created_at']))) ?></div>
          <div><?= nl2br(e($t['body'])) ?></div>
          <?php foreach ($t['replies'] as $rp): ?>
          <div class="border-start ps-3 mt-2 ms-2">
            <div class="small text-muted"><strong class="text-dark"><?= e($rp['author_name']) ?></strong> · <?= e($rp['author_role']) ?> · <?= e(date('M j, Y H:i', strtotime((string)$rp['created_at']))) ?></div>
            <div><?= nl2br(e($rp['body'])) ?></div>
          </div>
          <?php endforeach; ?>
          <form class="js-discussion-form mt-2 d-flex gap-2" data-target="#discussionList">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="hidden" name="parent_id" value="<?= (int)$t['id'] ?>">
            <input class="form-control form-control-sm" name="body" required maxlength="4000" placeholder="Reply…">
            <button class="btn btn-sm btn-outline-primary" type="submit">Reply</button>
          </form>
        </div>
      <?php endforeach; ?>
      </div>
      <form class="js-discussion-form mt-3" data-target="#discussionList">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <?php if ($current): ?><input type="hidden" name="lesson_id" value="<?= (int)$current['id'] ?>"><?php endif; ?>
        <label class="form-label fw-semibold">Ask a question</label>
        <textarea class="form-control" name="body" rows="2" required maxlength="4000" placeholder="Ask your instructor or classmates…"></textarea>
        <button class="btn btn-gradient btn-sm mt-2" type="submit"><i class="bi bi-send"></i> Post question</button>
      </form>
    </div></div>
    <?php endif; ?>
  </div>

  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold">Curriculum</div>
    <div class="curriculum">
    <?php $renderLessons = function (array $ls) use ($courseId, $current, $done, $canAccess, $bookmarks) {
        foreach ($ls as $l): ?>
        <a class="curriculum-item <?= $current && (int)$current['id'] === (int)$l['id'] ? 'active' : '' ?>" data-curriculum-lesson="<?= (int)$l['id'] ?>" href="/student/course.php?id=<?= $courseId ?>&lesson=<?= (int)$l['id'] ?>">
          <i class="lesson-state-icon bi <?= isset($done[(int)$l['id']]) ? 'bi-check-circle-fill text-success' : ($canAccess($l) ? 'bi-play-circle' : 'bi-lock') ?>"></i>
          <span class="flex-grow-1"><?= e($l['title']) ?><?php if (isset($bookmarks[(int)$l['id']])): ?> <i class="bi bi-bookmark-fill text-primary small" title="Bookmarked"></i><?php endif; ?></span>
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
