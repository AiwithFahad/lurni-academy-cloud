<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['instructor']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Instructor Dashboard'; $active = 'dashboard';

$s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $ins = $s->fetch();
if (!$ins) { http_response_code(403); exit('Instructor profile not found for your account.'); }
$iid = (int)$ins['id'];

$cnt = function (string $sql, array $a) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); };
$myCourses = $cnt('SELECT COUNT(*) FROM courses WHERE instructor_id=? AND tenant_id=?', [$iid, $tid]);
$myStudents = $cnt('SELECT COUNT(DISTINCT en.student_id) FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE c.instructor_id=? AND en.tenant_id=?', [$iid, $tid]);
$toGrade = $cnt("SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id WHERE c.instructor_id=? AND s.status IN ('submitted','late') AND s.tenant_id=?", [$iid, $tid]);
$s = $pdo->prepare('SELECT COALESCE(AVG(rating),0) FROM courses WHERE instructor_id=? AND tenant_id=?');
$s->execute([$iid, $tid]); $avgRating = (float)$s->fetchColumn();

$s = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners FROM courses c WHERE c.instructor_id=? AND c.tenant_id=? ORDER BY c.id DESC LIMIT 6");
$s->execute([$iid, $tid]); $courses = $s->fetchAll();
$s = $pdo->prepare("SELECT s.*, a.title AS assignment_title, u.name AS student_name FROM submissions s
    JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id JOIN students st ON st.id=s.student_id JOIN users u ON u.id=st.user_id
    WHERE c.instructor_id=? AND s.tenant_id=? AND s.status IN ('submitted','late') ORDER BY s.submitted_at DESC LIMIT 5");
$s->execute([$iid, $tid]); $pending = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="alert alert-light border">Welcome back, <strong><?= e($me['name']) ?></strong> — <?= e($ins['title'] ?? 'Instructor') ?>. Your courses, learners and grading queue are below.</div>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-journal-bookmark', 'My courses', $myCourses, 'primary'],
    ['bi-people', 'My learners', $myStudents, 'violet'],
    ['bi-check2-square', 'To grade', $toGrade, 'warning'],
    ['bi-star', 'Average rating', number_format($avgRating, 1), 'success'],
  ] as [$icon, $label, $val, $cls]): ?>
  <div class="col-md-6 col-xl-3"><div class="stat-card"><span class="stat-icon <?= $cls ?>"><i class="bi <?= $icon ?>"></i></span>
    <div><div class="stat-value"><?= $val ?></div><div class="stat-label"><?= $label ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-bold">My courses</span><a class="small" href="/instructor/courses.php">Manage</a></div>
    <ul class="list-group list-group-flush">
    <?php foreach ($courses as $c): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center"><span><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" class="table-thumb" alt=""> <strong><?= e($c['title']) ?></strong><br><small class="text-muted"><?= e($c['status']) ?> · ★ <?= number_format((float)$c['rating'], 1) ?></small></span>
      <span class="badge bg-primary-subtle text-primary"><?= (int)$c['learners'] ?> learners</span></li>
    <?php endforeach; ?>
    <?php if (!$courses): ?><li class="list-group-item text-muted">No courses yet — <a href="/instructor/courses.php">create your first course</a>.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-bold">Grading queue</span><a class="small" href="/instructor/grading.php">Open</a></div>
    <ul class="list-group list-group-flush">
    <?php foreach ($pending as $p): ?>
      <li class="list-group-item"><strong><?= e($p['student_name']) ?></strong> submitted “<?= e($p['assignment_title']) ?>”<br><small class="text-muted"><?= e(time_ago($p['submitted_at'])) ?> · <?= e($p['status']) ?></small></li>
    <?php endforeach; ?>
    <?php if (!$pending): ?><li class="list-group-item text-muted">Nothing waiting to be graded. 🎉</li><?php endif; ?>
    </ul></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
