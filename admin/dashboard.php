<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Academy Dashboard'; $active = 'dashboard';

$q = fn(string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $s = $pdo->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); })();
$students = $q('SELECT COUNT(*) FROM students WHERE tenant_id=?', [$tid]);
$instructors = $q('SELECT COUNT(*) FROM instructors WHERE tenant_id=?', [$tid]);
$courses = $q('SELECT COUNT(*) FROM courses WHERE tenant_id=?', [$tid]);
$published = $q("SELECT COUNT(*) FROM courses WHERE tenant_id=? AND status='published'", [$tid]);
$enrollments = $q('SELECT COUNT(*) FROM enrollments WHERE tenant_id=?', [$tid]);
$certificates = $q('SELECT COUNT(*) FROM certificates WHERE tenant_id=?', [$tid]);
$pendingGrading = $q("SELECT COUNT(*) FROM submissions WHERE tenant_id=? AND status IN ('submitted','late')", [$tid]);
$s = $pdo->prepare('SELECT COALESCE(AVG(rating),0) FROM reviews WHERE tenant_id=?');
$s->execute([$tid]); $avgRating = (float)$s->fetchColumn();
$sub = current_subscription($pdo, $tid);
sync_subscription_usage($pdo, $tid);

// Enrollments by month (last 6) — computed from records.
$enrRows = $pdo->prepare("SELECT DATE_FORMAT(enrolled_at,'%Y-%m') AS ym, COUNT(*) AS total FROM enrollments
    WHERE tenant_id=? AND enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY ym ORDER BY ym");
$enrRows->execute([$tid]); $enrRows = $enrRows->fetchAll();
$enrLabels = []; $enrData = [];
$cursor = new DateTime('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $key = $cursor->format('Y-m'); $enrLabels[] = $cursor->format('M'); $enrData[] = 0;
    foreach ($enrRows as $r) if ($r['ym'] === $key) $enrData[$i] = (int)$r['total'];
    $cursor->modify('+1 month');
}
$s = $pdo->prepare("SELECT c.title, COUNT(en.id) AS learners, c.rating FROM courses c LEFT JOIN enrollments en ON en.course_id=c.id
    WHERE c.tenant_id=? GROUP BY c.id ORDER BY learners DESC LIMIT 5");
$s->execute([$tid]); $topCourses = $s->fetchAll();
$s = $pdo->prepare("SELECT en.*, u.name AS student_name, c.title AS course_title FROM enrollments en
    JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id
    WHERE en.tenant_id=? ORDER BY en.enrolled_at DESC LIMIT 6");
$s->execute([$tid]); $recentEnrollments = $s->fetchAll();
$s = $pdo->prepare('SELECT * FROM activity_logs WHERE tenant_id=? ORDER BY created_at DESC LIMIT 6');
$s->execute([$tid]); $activity = $s->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<?php if ($sub && !in_array($sub['status'], ['trial', 'active'], true)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Your subscription is <strong><?= e($sub['status']) ?></strong>. <a href="/admin/subscription.php">Manage billing</a> to restore full access.</div>
<?php elseif ($sub && $sub['status'] === 'trial'): ?>
<div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>You are on a free trial of the <strong><?= e($sub['plan_name']) ?></strong> plan<?= $sub['trial_ends_at'] ? ' ending ' . e(date('M j, Y', strtotime($sub['trial_ends_at']))) : '' ?>. <a href="/admin/subscription.php">View plans</a></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-people', 'Students', $students, 'primary'],
    ['bi-person-video3', 'Instructors', $instructors, 'violet'],
    ['bi-journal-bookmark', 'Courses', $courses, 'info'],
    ['bi-person-check', 'Enrollments', $enrollments, 'success'],
  ] as [$icon, $label, $val, $cls]): ?>
  <div class="col-md-6 col-xl-3"><div class="stat-card"><span class="stat-icon <?= $cls ?>"><i class="bi <?= $icon ?>"></i></span>
    <div><div class="stat-value"><?= number_format($val) ?></div><div class="stat-label"><?= $label ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-8"><div class="card h-100"><div class="card-header bg-white fw-bold">Enrollments — last 6 months</div>
    <div class="card-body"><canvas id="enrChart" height="210"></canvas></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">At a glance</div>
    <ul class="list-group list-group-flush">
      <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-check2-square text-warning me-2"></i>Submissions to grade</span><strong><?= $pendingGrading ?></strong></li>
      <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-award text-success me-2"></i>Certificates issued</span><strong><?= $certificates ?></strong></li>
      <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-star text-warning me-2"></i>Average course rating</span><strong><?= number_format($avgRating, 1) ?> / 5</strong></li>
      <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-broadcast text-primary me-2"></i>Published courses</span><strong><?= $published ?> / <?= $courses ?></strong></li>
      <?php if ($sub): ?><li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-gem text-primary me-2"></i>Current plan</span><strong><?= e($sub['plan_name']) ?></strong></li><?php endif; ?>
    </ul></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">Top courses</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($topCourses as $c): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center"><span><?= e($c['title']) ?><br><small class="text-warning">★ <?= number_format((float)$c['rating'], 1) ?></small></span><span class="badge bg-primary-subtle text-primary"><?= (int)$c['learners'] ?> learners</span></li>
    <?php endforeach; ?>
    <?php if (!$topCourses): ?><li class="list-group-item text-muted">No courses yet — <a href="/admin/courses.php">create your first course</a>.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">Recent enrollments</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($recentEnrollments as $en): ?>
      <li class="list-group-item"><strong><?= e($en['student_name']) ?></strong> enrolled in <?= e($en['course_title']) ?><br><small class="text-muted"><?= e(time_ago($en['enrolled_at'])) ?> · progress <?= (float)$en['progress_percent'] ?>%</small></li>
    <?php endforeach; ?>
    <?php if (!$recentEnrollments): ?><li class="list-group-item text-muted">No enrollments yet.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">Recent activity</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($activity as $a): ?>
      <li class="list-group-item"><i class="bi bi-activity text-primary me-2"></i><?= e($a['description']) ?><br><small class="text-muted"><?= e(time_ago($a['created_at'])) ?></small></li>
    <?php endforeach; ?>
    <?php if (!$activity): ?><li class="list-group-item text-muted">Activity will appear here.</li><?php endif; ?>
    </ul></div></div>
</div>
<div class="card mt-3"><div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
  <span class="fw-bold"><i class="bi bi-bar-chart-line text-primary"></i> Analytics — live from your academy data</span>
  <span class="d-flex gap-2 align-items-center">
    <input type="date" class="form-control form-control-sm" data-analytics-from value="<?= date('Y-m-d', strtotime('-11 months')) ?>">
    <input type="date" class="form-control form-control-sm" data-analytics-to value="<?= date('Y-m-d') ?>">
    <button class="btn btn-sm btn-primary" data-analytics-refresh>Apply</button>
  </span></div>
<div class="card-body">
  <div class="row g-3 mb-3">
    <?php foreach ([
        ['active_students', 'Active students', false],
        ['enrollments_range', 'Enrollments in range', false],
        ['revenue_range', 'Verified revenue in range', true],
        ['pending_payments', 'Pending payments', false],
        ['certificates_range', 'Certificates in range', false],
        ['pending_approval', 'Courses pending approval', false],
    ] as [$k, $label, $money]): ?>
    <div class="col-md-4 col-xl-2"><div class="border rounded p-3 text-center h-100">
      <div class="h4 fw-bold mb-0" data-stat="<?= $k ?>"<?= $money ? ' data-stat-money' : '' ?>>–</div>
      <div class="small text-muted"><?= $label ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-3">
    <div class="col-xl-6"><div class="fw-semibold mb-2">Revenue by month</div><div style="height:230px"><canvas id="dashRevenue" data-series="revenue" data-chart-type="bar" data-chart-label="Revenue" data-chart-money></canvas></div></div>
    <div class="col-xl-6"><div class="fw-semibold mb-2">Student growth (cumulative)</div><div style="height:230px"><canvas id="dashGrowth" data-series="student_growth" data-chart-type="line" data-chart-label="Students"></canvas></div></div>
    <div class="col-xl-6"><div class="fw-semibold mb-2">Completion rate by course (%)</div><div style="height:230px"><canvas id="dashCompletion" data-series="completion" data-chart-type="bar" data-chart-label="Completion %"></canvas></div></div>
    <div class="col-xl-6"><div class="fw-semibold mb-2">Instructor performance</div>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Instructor</th><th>Courses</th><th>Learners</th><th>Rating</th><th>Course revenue</th></tr></thead>
      <tbody id="instructorPerfBody"><tr><td colspan="5" class="text-muted">Loading…</td></tr></tbody></table></div></div>
  </div>
</div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  function fillPerf() {
    var from = document.querySelector('[data-analytics-from]'); var to = document.querySelector('[data-analytics-to]');
    var qs = [];
    if (from && from.value) qs.push('from=' + encodeURIComponent(from.value));
    if (to && to.value) qs.push('to=' + encodeURIComponent(to.value));
    fetch('/api/analytics.php' + (qs.length ? '?' + qs.join('&') : ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); }).then(function (res) {
        var body = document.getElementById('instructorPerfBody');
        if (!body) return;
        if (!res.ok || !res.instructor_performance || !res.instructor_performance.length) {
          body.innerHTML = '<tr><td colspan="5" class="text-muted">No instructor data yet.</td></tr>'; return;
        }
        body.innerHTML = res.instructor_performance.map(function (p) {
          function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
          return '<tr><td>' + esc(p.name) + '</td><td>' + p.courses + '</td><td>' + p.learners + '</td><td>★ ' + Number(p.avg_rating).toFixed(1) + '</td><td>$' + Number(p.revenue).toLocaleString() + '</td></tr>';
        }).join('');
      }).catch(function () {});
  }
  fillPerf();
  var btn = document.querySelector('[data-analytics-refresh]');
  if (btn) btn.addEventListener('click', fillPerf);
});
</script>
<script>
new Chart(document.getElementById('enrChart'), {type:'bar',
  data:{labels:<?= json_encode($enrLabels) ?>, datasets:[{label:'Enrollments', data:<?= json_encode($enrData) ?>, backgroundColor:'#7c3aed', borderRadius:8}]},
  options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{precision:0}}}}});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
