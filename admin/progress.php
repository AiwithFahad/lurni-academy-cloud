<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Student Progress'; $active = 'progress';

$courseF = (int)($_GET['course_id'] ?? 0);
$where = ['en.tenant_id=?']; $args = [$tid];
if ($courseF) { $where[] = 'en.course_id=?'; $args[] = $courseF; }
$s = $pdo->prepare("SELECT en.*, u.name AS student_name, c.title AS course_title,
    (SELECT COUNT(*) FROM lessons l WHERE l.course_id=en.course_id) AS total_lessons,
    (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.student_id=en.student_id AND lp.course_id=en.course_id AND lp.status='completed') AS done_lessons,
    (SELECT COALESCE(AVG(a.percent),0) FROM quiz_attempts a WHERE a.student_id=en.student_id AND a.quiz_id IN (SELECT id FROM quizzes WHERE course_id=en.course_id)) AS quiz_avg,
    (SELECT COUNT(*) FROM quiz_attempts a WHERE a.student_id=en.student_id AND a.quiz_id IN (SELECT id FROM quizzes WHERE course_id=en.course_id)) AS quiz_count
    FROM enrollments en JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id
    WHERE " . implode(' AND ', $where) . " ORDER BY en.progress_percent DESC LIMIT 100");
$s->execute($args); $rows = $s->fetchAll();
$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-header bg-white"><form class="row g-2" method="get">
    <div class="col-md-9"><select class="form-select" name="course_id"><option value="">All courses</option>
      <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseF === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Filter</button></div>
  </form></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Student</th><th>Course</th><th>Lessons done</th><th>Progress</th><th>Quiz average</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr><td><span class="avatar-circle sm"><?= e(initials($r['student_name'])) ?></span> <strong><?= e($r['student_name']) ?></strong></td>
      <td><small><?= e($r['course_title']) ?></small></td>
      <td><?= (int)$r['done_lessons'] ?> / <?= (int)$r['total_lessons'] ?></td>
      <td style="min-width:150px"><div class="progress progress-slim"><div class="progress-bar" style="width:<?= (float)$r['progress_percent'] ?>%"></div></div><small class="text-muted"><?= (float)$r['progress_percent'] ?>%</small></td>
      <td><?= (int)$r['quiz_count'] ? number_format((float)$r['quiz_avg'], 0) . '% (' . (int)$r['quiz_count'] . ' attempts)' : '—' ?></td>
      <td><span class="badge bg-<?= $r['status'] === 'completed' ? 'success' : ($r['status'] === 'cancelled' ? 'secondary' : 'primary') ?>-subtle text-<?= $r['status'] === 'completed' ? 'success' : ($r['status'] === 'cancelled' ? 'secondary' : 'primary') ?>"><?= e($r['status']) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No progress data yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Progress is recalculated automatically from completed lessons whenever a student finishes one — never typed in by hand.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
