<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['instructor']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Course Performance'; $active = 'performance';

$s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $ins = $s->fetch();
if (!$ins) { http_response_code(403); exit('Instructor profile not found.'); }
$iid = (int)$ins['id'];

$s = $pdo->prepare("SELECT c.title, c.rating,
    (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners,
    (SELECT COALESCE(AVG(en.progress_percent),0) FROM enrollments en WHERE en.course_id=c.id) AS avg_progress,
    (SELECT COUNT(*) FROM reviews r WHERE r.course_id=c.id AND r.status='published') AS review_count
    FROM courses c WHERE c.tenant_id=? AND c.instructor_id=? ORDER BY learners DESC");
$s->execute([$tid, $iid]); $courseRows = $s->fetchAll();
$s = $pdo->prepare("SELECT qz.title, COUNT(a.id) AS attempts, COALESCE(AVG(a.percent),0) AS avg_pct,
    SUM(CASE WHEN a.passed=1 THEN 1 ELSE 0 END) AS passes FROM quizzes qz JOIN courses c ON c.id=qz.course_id
    LEFT JOIN quiz_attempts a ON a.quiz_id=qz.id WHERE qz.tenant_id=? AND c.instructor_id=? GROUP BY qz.id ORDER BY attempts DESC");
$s->execute([$tid, $iid]); $quizRows = $s->fetchAll();
$s = $pdo->prepare("SELECT r.*, c.title AS course_title, u.name AS student_name FROM reviews r
    JOIN courses c ON c.id=r.course_id JOIN students st ON st.id=r.student_id JOIN users u ON u.id=st.user_id
    WHERE r.tenant_id=? AND c.instructor_id=? AND r.status='published' ORDER BY r.created_at DESC LIMIT 6");
$s->execute([$tid, $iid]); $reviews = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-xl-7"><div class="card h-100"><div class="card-header bg-white fw-bold">Learners per course</div>
    <div class="card-body"><canvas id="perfChart" height="220"></canvas></div></div></div>
  <div class="col-xl-5"><div class="card h-100"><div class="card-header bg-white fw-bold">Latest reviews</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($reviews as $r): ?>
      <li class="list-group-item"><span class="text-warning"><?= stars((float)$r['rating']) ?></span> <strong><?= e($r['student_name']) ?></strong>
      <small class="text-muted">on <?= e($r['course_title']) ?></small><p class="small text-muted mb-0"><?= e($r['comment']) ?></p></li>
    <?php endforeach; ?>
    <?php if (!$reviews): ?><li class="list-group-item text-muted">No published reviews yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Course metrics</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Course</th><th>Learners</th><th>Avg progress</th><th>Rating</th><th>Reviews</th></tr></thead><tbody>
      <?php foreach ($courseRows as $c): ?>
      <tr><td><strong><?= e($c['title']) ?></strong></td><td><?= (int)$c['learners'] ?></td>
        <td><div class="progress progress-slim" style="min-width:90px"><div class="progress-bar" style="width:<?= (float)$c['avg_progress'] ?>%"></div></div><small><?= number_format((float)$c['avg_progress'], 0) ?>%</small></td>
        <td><span class="text-warning">★ <?= number_format((float)$c['rating'], 1) ?></span></td><td><?= (int)$c['review_count'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$courseRows): ?><tr><td colspan="5" class="text-center text-muted py-4">No courses yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">Quiz results</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($quizRows as $q): $pr = (int)$q['attempts'] ? round((int)$q['passes'] / (int)$q['attempts'] * 100) : 0; ?>
      <li class="list-group-item d-flex justify-content-between"><span><?= e($q['title']) ?><br><small class="text-muted"><?= (int)$q['attempts'] ?> attempts · avg <?= number_format((float)$q['avg_pct'], 0) ?>%</small></span><span class="badge bg-success-subtle text-success"><?= $pr ?>% pass</span></li>
    <?php endforeach; ?>
    <?php if (!$quizRows): ?><li class="list-group-item text-muted">No quizzes yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<script>
new Chart(document.getElementById('perfChart'), {type:'bar',
  data:{labels:<?= json_encode(array_column($courseRows, 'title')) ?>, datasets:[{label:'Learners', data:<?= json_encode(array_map('intval', array_column($courseRows, 'learners'))) ?>, backgroundColor:'#2563eb', borderRadius:8}]},
  options:{indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true, ticks:{precision:0}}}}});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
