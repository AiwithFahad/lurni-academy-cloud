<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Progress'; $active = 'progress';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT en.*, c.title FROM enrollments en JOIN courses c ON c.id=en.course_id
    WHERE en.student_id=? AND en.tenant_id=? ORDER BY en.progress_percent DESC");
$s->execute([$sid, $tid]); $rows = $s->fetchAll();
$s = $pdo->prepare('SELECT COALESCE(AVG(percent),0) FROM quiz_attempts WHERE student_id=? AND tenant_id=?');
$s->execute([$sid, $tid]); $quizAvg = (float)$s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id=? AND tenant_id=? AND status='completed'");
$s->execute([$sid, $tid]); $lessonsDone = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM lessons l JOIN enrollments en ON en.course_id=l.course_id AND en.student_id=? WHERE l.tenant_id=?");
$s->execute([$sid, $tid]); $lessonsTotal = (int)$s->fetchColumn();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="stat-card"><span class="stat-icon primary"><i class="bi bi-play-btn"></i></span>
    <div><div class="stat-value"><?= $lessonsDone ?> / <?= $lessonsTotal ?></div><div class="stat-label">Lessons completed</div></div></div></div>
  <div class="col-md-4"><div class="stat-card"><span class="stat-icon violet"><i class="bi bi-patch-question"></i></span>
    <div><div class="stat-value"><?= number_format($quizAvg, 0) ?>%</div><div class="stat-label">Average quiz score</div></div></div></div>
  <div class="col-md-4"><div class="stat-card"><span class="stat-icon info"><i class="bi bi-graph-up"></i></span>
    <div><div class="stat-value"><?= count($rows) ?></div><div class="stat-label">Enrolled courses</div></div></div></div>
</div>
<div class="card"><div class="card-header bg-white fw-bold">Progress by course</div><div class="card-body">
  <?php foreach ($rows as $en): ?>
  <div class="d-flex align-items-center gap-3 mb-3">
    <a class="fw-semibold text-decoration-none flex-shrink-0" style="min-width:220px" href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><?= e($en['title']) ?></a>
    <div class="progress flex-grow-1" style="height:14px"><div class="progress-bar" style="width:<?= (float)$en['progress_percent'] ?>%"></div></div>
    <span class="fw-bold"><?= (float)$en['progress_percent'] ?>%</span>
    <?php if ($en['status'] === 'completed'): ?><span class="badge bg-success-subtle text-success">Completed</span><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="text-muted mb-0">Enroll in a course to start tracking progress.</p><?php endif; ?>
  <?php if ($rows): ?><canvas id="progChart" height="140" class="mt-3"></canvas><?php endif; ?>
</div></div>
<?php if ($rows): ?>
<script>
new Chart(document.getElementById('progChart'), {type:'bar',
  data:{labels:<?= json_encode(array_column($rows, 'title')) ?>, datasets:[{label:'% complete', data:<?= json_encode(array_map('floatval', array_column($rows, 'progress_percent'))) ?>, backgroundColor:'#7c3aed', borderRadius:8}]},
  options:{scales:{y:{min:0,max:100}}, plugins:{legend:{display:false}}}});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
