<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Dashboard'; $active = 'dashboard';

$s = $pdo->prepare('SELECT * FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $student = $s->fetch();
if (!$student) { http_response_code(403); exit('Student profile not found for your account.'); }
$sid = (int)$student['id'];

$cnt = function (string $sql, array $a) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); };
$enrolled = $cnt('SELECT COUNT(*) FROM enrollments WHERE student_id=? AND tenant_id=? AND status="active"', [$sid, $tid]);
$completed = $cnt('SELECT COUNT(*) FROM enrollments WHERE student_id=? AND tenant_id=? AND status="completed"', [$sid, $tid]);
$certs = $cnt('SELECT COUNT(*) FROM certificates WHERE student_id=? AND tenant_id=?', [$sid, $tid]);
$lessonsDone = $cnt("SELECT COUNT(*) FROM lesson_progress WHERE student_id=? AND tenant_id=? AND status='completed'", [$sid, $tid]);
$s = $pdo->prepare('SELECT COALESCE(AVG(percent),0) FROM quiz_attempts WHERE student_id=? AND tenant_id=?');
$s->execute([$sid, $tid]); $quizAvg = (float)$s->fetchColumn();

$s = $pdo->prepare("SELECT en.*, c.title, c.thumbnail, cat.name AS category_name FROM enrollments en JOIN courses c ON c.id=en.course_id
    LEFT JOIN categories cat ON cat.id=c.category_id WHERE en.student_id=? AND en.tenant_id=? AND en.status IN ('active','completed')
    ORDER BY en.enrolled_at DESC LIMIT 4");
$s->execute([$sid, $tid]); $continue = $s->fetchAll();
$s = $pdo->prepare("SELECT a.*, c.title AS course_title FROM assignments a JOIN courses c ON c.id=a.course_id
    JOIN enrollments en ON en.course_id=a.course_id AND en.student_id=?
    LEFT JOIN submissions s ON s.assignment_id=a.id AND s.student_id=?
    WHERE a.tenant_id=? AND s.id IS NULL AND (a.due_date IS NULL OR a.due_date > NOW()) ORDER BY a.due_date LIMIT 4");
$s->execute([$sid, $sid, $tid]); $dueAssignments = $s->fetchAll();
$s = $pdo->prepare("SELECT lp.completed_at, l.title AS lesson_title, c.title AS course_title FROM lesson_progress lp
    JOIN lessons l ON l.id=lp.lesson_id JOIN courses c ON c.id=lp.course_id
    WHERE lp.student_id=? AND lp.tenant_id=? AND lp.status='completed' ORDER BY lp.completed_at DESC LIMIT 5");
$s->execute([$sid, $tid]); $recent = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="alert alert-light border">Welcome back, <strong><?= e($me['name']) ?></strong> — student code <code><?= e($student['student_code'] ?? '') ?></code>. Pick up right where you left off.</div>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-journal-bookmark', 'Courses in progress', $enrolled, 'primary'],
    ['bi-check-circle', 'Courses completed', $completed, 'success'],
    ['bi-play-btn', 'Lessons completed', $lessonsDone, 'violet'],
    ['bi-award', 'Certificates earned', $certs, 'info'],
  ] as [$icon, $label, $val, $cls]): ?>
  <div class="col-md-6 col-xl-3"><div class="stat-card"><span class="stat-icon <?= $cls ?>"><i class="bi <?= $icon ?>"></i></span>
    <div><div class="stat-value"><?= number_format($val) ?></div><div class="stat-label"><?= $label ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card mb-3"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-bold">Continue learning</span><a class="small" href="/student/my-learning.php">All my courses</a></div>
      <div class="card-body"><div class="row g-3">
      <?php foreach ($continue as $en): ?>
        <div class="col-md-6"><article class="course-card">
          <a href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><img src="<?= e($en['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="course-thumb" alt=""></a>
          <div class="course-body"><span class="badge bg-primary-subtle text-primary"><?= e($en['category_name'] ?? 'Course') ?></span>
            <a class="course-title" href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><?= e($en['title']) ?></a>
            <div class="progress progress-slim"><div class="progress-bar" style="width:<?= (float)$en['progress_percent'] ?>%"></div></div>
            <div class="d-flex justify-content-between align-items-center"><small class="text-muted"><?= (float)$en['progress_percent'] ?>% complete</small>
            <a class="btn btn-sm btn-gradient" href="/student/course.php?id=<?= (int)$en['course_id'] ?>"><?= (float)$en['progress_percent'] >= 100 ? 'Review' : 'Continue' ?></a></div>
          </div></article></div>
      <?php endforeach; ?>
      <?php if (!$continue): ?><p class="text-muted mb-0">You are not enrolled in any course yet — <a href="/student/browse.php">browse the catalog</a>.</p><?php endif; ?>
      </div></div></div>
    <div class="card"><div class="card-header bg-white fw-bold">Recent activity</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($recent as $r): ?>
        <li class="list-group-item"><i class="bi bi-check-circle text-success me-2"></i>Completed “<?= e($r['lesson_title']) ?>” <small class="text-muted">in <?= e($r['course_title']) ?> · <?= e(time_ago($r['completed_at'])) ?></small></li>
      <?php endforeach; ?>
      <?php if (!$recent): ?><li class="list-group-item text-muted">Complete a lesson and it will show up here.</li><?php endif; ?>
      </ul></div>
  </div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-body text-center">
      <div class="stat-value"><?= number_format($quizAvg, 0) ?>%</div><div class="stat-label">Average quiz score</div>
      <a class="btn btn-outline-primary btn-sm mt-2" href="/student/quizzes.php">Take a quiz</a></div></div>
    <div class="card"><div class="card-header bg-white fw-bold">Assignments due</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($dueAssignments as $a): ?>
        <li class="list-group-item"><strong><?= e($a['title']) ?></strong><br><small class="text-muted"><?= e($a['course_title']) ?> · due <?= $a['due_date'] ? e(date('M j, H:i', strtotime($a['due_date']))) : 'any time' ?></small>
        <a class="btn btn-sm btn-gradient float-end" href="/student/assignments.php">Submit</a></li>
      <?php endforeach; ?>
      <?php if (!$dueAssignments): ?><li class="list-group-item text-muted">No pending assignments. 🎉</li><?php endif; ?>
      </ul></div>
  </div>
</div>
<div class="row g-3 mt-1">
  <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-bold"><i class="bi bi-activity text-primary"></i> My learning activity — lessons completed per week</div>
    <div class="card-body"><div style="height:220px"><canvas id="myActivity" data-series="weekly_activity" data-chart-type="bar" data-chart-label="Lessons completed"></canvas></div></div></div></div>
  <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-bold"><i class="bi bi-bar-chart text-primary"></i> Progress by course</div>
    <div class="card-body"><div style="height:220px"><canvas id="myProgress" data-series="course_progress" data-chart-type="bar" data-chart-label="Progress %"></canvas></div></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
