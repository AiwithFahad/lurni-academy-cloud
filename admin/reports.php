<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Reports & Analytics'; $active = 'reports';
require_permission($pdo, 'reports.view');

// Exports (real records only).
if (getp('export') === 'courses') {
    $s = $pdo->prepare("SELECT c.title, cat.name AS category, c.status, c.price, c.rating,
        (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS enrollments,
        (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id AND en.status='completed') AS completions
        FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.tenant_id=? ORDER BY enrollments DESC");
    $s->execute([$tid]);
    export_csv($s->fetchAll(), 'course-report.csv');
}
if (getp('export') === 'payments') {
    $s = $pdo->prepare('SELECT id, amount, discount_amount, coupon_code, method, reference, status, created_at FROM payments WHERE tenant_id=? ORDER BY created_at DESC');
    $s->execute([$tid]);
    export_csv($s->fetchAll(), 'payment-report.csv');
}

// Enrollments & completions by month.
$rows = $pdo->prepare("SELECT DATE_FORMAT(enrolled_at,'%Y-%m') AS ym, COUNT(*) AS total,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed FROM enrollments
    WHERE tenant_id=? AND enrolled_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY ym ORDER BY ym");
$rows->execute([$tid]); $rows = $rows->fetchAll();
$labels = []; $enr = []; $comp = [];
$cursor = new DateTime('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $key = $cursor->format('Y-m'); $labels[] = $cursor->format('M Y'); $enr[] = 0; $comp[] = 0;
    foreach ($rows as $r) if ($r['ym'] === $key) { $enr[$i] = (int)$r['total']; $comp[$i] = (int)$r['completed']; }
    $cursor->modify('+1 month');
}
// Category distribution.
$s = $pdo->prepare("SELECT cat.name, COUNT(c.id) AS total FROM categories cat LEFT JOIN courses c ON c.category_id=cat.id AND c.tenant_id=cat.tenant_id WHERE cat.tenant_id=? GROUP BY cat.id ORDER BY total DESC");
$s->execute([$tid]); $catRows = $s->fetchAll();
// Quiz performance.
$s = $pdo->prepare("SELECT qz.title, COUNT(a.id) AS attempts, COALESCE(AVG(a.percent),0) AS avg_pct,
    SUM(CASE WHEN a.passed=1 THEN 1 ELSE 0 END) AS passes FROM quizzes qz LEFT JOIN quiz_attempts a ON a.quiz_id=qz.id
    WHERE qz.tenant_id=? GROUP BY qz.id ORDER BY attempts DESC LIMIT 8");
$s->execute([$tid]); $quizRows = $s->fetchAll();
// Billing totals (own subscription payments).
$s = $pdo->prepare("SELECT status, COUNT(*) AS n, COALESCE(SUM(amount - discount_amount),0) AS total FROM payments WHERE tenant_id=? GROUP BY status");
$s->execute([$tid]); $billing = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex gap-2 mb-3">
  <a class="btn btn-outline-primary" href="?export=courses"><i class="bi bi-download me-1"></i>Course report (CSV)</a>
  <a class="btn btn-outline-primary" href="?export=payments"><i class="bi bi-download me-1"></i>Payment report (CSV)</a>
  <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / PDF</button>
</div>
<div class="row g-3 mb-4">
  <div class="col-xl-8"><div class="card h-100"><div class="card-header bg-white fw-bold">Enrollments vs completions — last 6 months</div>
    <div class="card-body"><canvas id="enrChart" height="220"></canvas></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">Courses by category</div>
    <div class="card-body"><canvas id="catChart" height="220"></canvas></div></div></div>
</div>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Quiz performance</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Quiz</th><th>Attempts</th><th>Pass rate</th><th>Avg score</th></tr></thead><tbody>
      <?php foreach ($quizRows as $r): $passRate = (int)$r['attempts'] ? round((int)$r['passes'] / (int)$r['attempts'] * 100) : 0; ?>
      <tr><td><?= e($r['title']) ?></td><td><?= (int)$r['attempts'] ?></td>
        <td><div class="progress progress-slim" style="min-width:90px"><div class="progress-bar bg-success" style="width:<?= $passRate ?>%"></div></div><small><?= $passRate ?>%</small></td>
        <td><?= number_format((float)$r['avg_pct'], 0) ?>%</td></tr>
      <?php endforeach; ?>
      <?php if (!$quizRows): ?><tr><td colspan="4" class="text-center text-muted py-4">No quizzes yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">My billing totals</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($billing as $b): ?>
      <li class="list-group-item d-flex justify-content-between"><span class="text-capitalize"><?= e($b['status']) ?> payments (<?= (int)$b['n'] ?>)</span><strong><?= money($b['total']) ?></strong></li>
    <?php endforeach; ?>
    <?php if (!$billing): ?><li class="list-group-item text-muted">No payments recorded.</li><?php endif; ?>
    </ul>
    <div class="card-body small text-muted">Only <strong>completed</strong> payments count as revenue — pending ones are awaiting verification.</div></div></div>
</div>
<script>
new Chart(document.getElementById('enrChart'), {type:'bar',
  data:{labels:<?= json_encode($labels) ?>, datasets:[
    {label:'Enrollments', data:<?= json_encode($enr) ?>, backgroundColor:'#4f46e5', borderRadius:6},
    {label:'Completions', data:<?= json_encode($comp) ?>, backgroundColor:'#22c55e', borderRadius:6}]},
  options:{scales:{y:{beginAtZero:true, ticks:{precision:0}}}}});
new Chart(document.getElementById('catChart'), {type:'doughnut',
  data:{labels:<?= json_encode(array_column($catRows, 'name')) ?>, datasets:[{data:<?= json_encode(array_map('intval', array_column($catRows, 'total'))) ?>, backgroundColor:['#2563eb','#7c3aed','#0ea5e9','#db2777','#059669','#f59e0b']}]},
  options:{plugins:{legend:{position:'bottom'}}}});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
