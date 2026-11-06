<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Platform Dashboard'; $active = 'dashboard';

// --- Every figure below is computed from live platform records. ---
$q = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$tenantsTotal = $q('SELECT COUNT(*) FROM tenants');
$tenantsActive = $q("SELECT COUNT(*) FROM tenants WHERE status='active'");
$tenantsSuspended = $q("SELECT COUNT(*) FROM tenants WHERE status='suspended'");
$usersTotal = $q('SELECT COUNT(*) FROM users');
$instructorsTotal = $q('SELECT COUNT(*) FROM instructors');
$studentsTotal = $q('SELECT COUNT(*) FROM students');
$coursesTotal = $q('SELECT COUNT(*) FROM courses');
$subsActive = $q("SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trial')");
$subsExpired = $q("SELECT COUNT(*) FROM subscriptions WHERE status='expired'");
$paymentsCount = $q('SELECT COUNT(*) FROM payments');
$revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount - discount_amount),0) FROM payments WHERE status='completed'")->fetchColumn();

// MRR: sum of monthly-equivalent amounts of live subscriptions. ARR = MRR x 12.
$mrr = (float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN billing_cycle='yearly' THEN amount/12 ELSE amount END),0)
    FROM subscriptions WHERE status IN ('active','trial','past_due')")->fetchColumn();
$arr = $mrr * 12;

// Revenue by month (verified payments only), last 6 months.
$revRows = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, SUM(amount - discount_amount) AS total
    FROM payments WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY ym ORDER BY ym")->fetchAll();
$revLabels = []; $revData = [];
$cursor = new DateTime('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $key = $cursor->format('Y-m');
    $revLabels[] = $cursor->format('M Y');
    $revData[] = 0.0;
    foreach ($revRows as $r) if ($r['ym'] === $key) $revData[$i] = (float)$r['total'];
    $cursor->modify('+1 month');
}
// Subscriptions by plan.
$planRows = $pdo->query("SELECT p.name, COUNT(s.id) AS total FROM subscription_plans p
    LEFT JOIN subscriptions s ON s.plan_id=p.id GROUP BY p.id, p.name ORDER BY p.sort_order")->fetchAll();
// Recent tenants & payments.
$recentTenants = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.tenant_id=t.id) AS user_count,
    (SELECT p.name FROM subscriptions s JOIN subscription_plans p ON p.id=s.plan_id WHERE s.tenant_id=t.id ORDER BY s.id DESC LIMIT 1) AS plan_name,
    (SELECT s.status FROM subscriptions s WHERE s.tenant_id=t.id ORDER BY s.id DESC LIMIT 1) AS sub_status
    FROM tenants t ORDER BY t.created_at DESC LIMIT 6")->fetchAll();
$recentPayments = $pdo->query("SELECT pay.*, t.name AS tenant_name FROM payments pay JOIN tenants t ON t.id=pay.tenant_id ORDER BY pay.created_at DESC LIMIT 6")->fetchAll();

require __DIR__ . '/header.php';
?>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['bi-buildings', 'Total tenants', number_format($tenantsTotal), $tenantsActive . ' active · ' . $tenantsSuspended . ' suspended', 'primary'],
    ['bi-people', 'Platform users', number_format($usersTotal), $studentsTotal . ' students · ' . $instructorsTotal . ' instructors', 'violet'],
    ['bi-arrow-repeat', 'Active subscriptions', number_format($subsActive), $subsExpired . ' expired', 'success'],
    ['bi-journal-bookmark', 'Courses platform-wide', number_format($coursesTotal), 'across all academies', 'info'],
  ] as [$icon, $label, $val, $sub, $cls]): ?>
  <div class="col-md-6 col-xl-3"><div class="stat-card"><span class="stat-icon <?= $cls ?>"><i class="bi <?= $icon ?>"></i></span>
    <div><div class="stat-value"><?= $val ?></div><div class="stat-label"><?= $label ?></div><small class="text-muted"><?= $sub ?></small></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="money-card"><small>MRR</small><div class="money-value"><?= money($mrr) ?></div><small class="opacity-75">Monthly recurring revenue (live subscriptions)</small></div></div>
  <div class="col-md-4"><div class="money-card alt"><small>ARR</small><div class="money-value"><?= money($arr) ?></div><small class="opacity-75">Annual recurring revenue (MRR × 12)</small></div></div>
  <div class="col-md-4"><div class="money-card dark"><small>Verified revenue</small><div class="money-value"><?= money($revenue) ?></div><small class="opacity-75"><?= $paymentsCount ?> payment transactions recorded</small></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-8"><div class="card h-100"><div class="card-header bg-white fw-bold">Revenue — last 6 months (verified payments)</div>
    <div class="card-body"><canvas id="revChart" height="220"></canvas></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">Subscriptions by plan</div>
    <div class="card-body"><canvas id="planChart" height="220"></canvas></div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-bold">Newest tenants</span><a href="/super-admin/tenants.php" class="small">Manage all</a></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Academy</th><th>Plan</th><th>Users</th><th>Status</th><th>Joined</th></tr></thead><tbody>
      <?php foreach ($recentTenants as $t): ?>
      <tr><td><strong><?= e($t['name']) ?></strong><br><small class="text-muted"><?= e($t['slug']) ?> · <?= e($t['org_type']) ?></small></td>
        <td><?= e($t['plan_name'] ?? '—') ?><br><small class="text-muted"><?= e($t['sub_status'] ?? '') ?></small></td>
        <td><?= (int)$t['user_count'] ?></td>
        <td><span class="badge bg-<?= $t['status'] === 'active' ? 'success' : 'danger' ?>-subtle text-<?= $t['status'] === 'active' ? 'success' : 'danger' ?>"><?= e($t['status']) ?></span></td>
        <td><small><?= e(date('M j, Y', strtotime($t['created_at']))) ?></small></td></tr>
      <?php endforeach; ?>
      <?php if (!$recentTenants): ?><tr><td colspan="5" class="text-muted text-center py-4">No tenants yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-bold">Latest payments</span><a href="/super-admin/payments.php" class="small">All payments</a></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Tenant</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($recentPayments as $p): ?>
      <tr><td><?= e($p['tenant_name']) ?><br><small class="text-muted"><?= e($p['method']) ?> · <?= e(time_ago($p['created_at'])) ?></small></td>
        <td class="fw-semibold"><?= money($p['amount']) ?></td>
        <td><span class="badge bg-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'secondary') ?>-subtle text-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e($p['status']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$recentPayments): ?><tr><td colspan="3" class="text-muted text-center py-4">No payments yet.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
</div>

<script>
new Chart(document.getElementById('revChart'), {type:'bar',
  data:{labels:<?= json_encode($revLabels) ?>, datasets:[{label:'Revenue ($)', data:<?= json_encode($revData) ?>, backgroundColor:'#4f46e5', borderRadius:8}]},
  options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('planChart'), {type:'doughnut',
  data:{labels:<?= json_encode(array_column($planRows, 'name')) ?>, datasets:[{data:<?= json_encode(array_map('intval', array_column($planRows, 'total'))) ?>, backgroundColor:['#94a3b8','#38bdf8','#4f46e5','#7c3aed','#0ea5e9','#f59e0b']}]},
  options:{plugins:{legend:{position:'bottom'}}}});
</script>
<?php require __DIR__ . '/footer.php'; ?>
