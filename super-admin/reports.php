<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Platform Reports'; $active = 'reports';

// CSV exports (same pattern as the tenant reports page).
if (getp('export') === 'revenue') {
    $s = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, tenant_id,
            SUM(CASE WHEN status='completed' THEN amount-discount_amount ELSE 0 END) AS revenue,
            SUM(status='pending') AS pending_count, COUNT(*) AS payments
        FROM payments GROUP BY month, tenant_id ORDER BY month DESC, tenant_id");
    export_csv($s->fetchAll(), 'platform-revenue.csv');
}
if (getp('export') === 'tenants') {
    $s = $pdo->query("SELECT t.name, t.email, t.status, t.created_at,
            (SELECT COUNT(*) FROM students st WHERE st.tenant_id=t.id) AS students,
            (SELECT COUNT(*) FROM courses c WHERE c.tenant_id=t.id) AS courses,
            (SELECT COALESCE(SUM(p.amount-p.discount_amount),0) FROM payments p WHERE p.tenant_id=t.id AND p.status='completed') AS revenue
        FROM tenants t ORDER BY revenue DESC");
    export_csv($s->fetchAll(), 'platform-institutions.csv');
}

$s = $pdo->query("SELECT t.name,
        (SELECT COUNT(*) FROM students st WHERE st.tenant_id=t.id) AS students,
        (SELECT COUNT(*) FROM courses c WHERE c.tenant_id=t.id) AS courses,
        (SELECT COALESCE(SUM(p.amount-p.discount_amount),0) FROM payments p WHERE p.tenant_id=t.id AND p.status='completed') AS revenue
    FROM tenants t ORDER BY revenue DESC LIMIT 10");
$topTenants = $s->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <div><h2 class="h4 fw-bold mb-0">Platform reports</h2>
  <p class="text-muted mb-0">Live aggregates from /api/analytics.php — pick a date range to refilter every chart.</p></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary" href="?export=revenue"><i class="bi bi-download me-1"></i>Revenue CSV</a>
    <a class="btn btn-outline-primary" href="?export=tenants"><i class="bi bi-download me-1"></i>Institutions CSV</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ([
      ['tenants_active', 'Active institutions', 'bi-buildings', false],
      ['students_total', 'Students platform-wide', 'bi-people', false],
      ['revenue_range', 'Revenue in range', 'bi-cash-stack', true],
      ['mrr', 'MRR (est.)', 'bi-graph-up-arrow', true],
      ['pending_payments', 'Pending payments', 'bi-hourglass-split', false],
      ['pending_approval', 'Courses awaiting approval', 'bi-journal-check', false],
  ] as [$key, $label, $icon, $money]): ?>
  <div class="col-md-4 col-xl-2"><div class="stat-card"><div class="stat-icon"><i class="bi <?= $icon ?>"></i></div>
    <div><div class="stat-value" data-stat="<?= $key ?>"<?= $money ? ' data-stat-money' : '' ?>>–</div><div class="stat-label"><?= $label ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
  <div><label class="form-label small mb-1">From</label><input type="date" class="form-control" data-analytics-from value="<?= date('Y-m-d', strtotime('-11 months')) ?>"></div>
  <div><label class="form-label small mb-1">To</label><input type="date" class="form-control" data-analytics-to value="<?= date('Y-m-d') ?>"></div>
  <button class="btn btn-primary" data-analytics-refresh><i class="bi bi-funnel"></i> Apply range</button>
</div></div>

<div class="row g-3">
  <div class="col-xl-8"><div class="card h-100"><div class="card-header bg-white fw-bold">Platform revenue by month</div>
    <div class="card-body"><div style="height:280px"><canvas id="chartRevenue" data-series="revenue" data-chart-type="bar" data-chart-label="Revenue" data-chart-money></canvas></div></div></div></div>
  <div class="col-xl-4"><div class="card h-100"><div class="card-header bg-white fw-bold">Subscriptions by plan</div>
    <div class="card-body"><div style="height:280px"><canvas id="chartPlans" data-series="plans" data-chart-type="doughnut" data-chart-label="Subscriptions"></canvas></div></div></div></div>
  <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-bold">Enrollments by month</div>
    <div class="card-body"><div style="height:240px"><canvas id="chartEnrollments" data-series="enrollments" data-chart-type="line" data-chart-label="Enrollments"></canvas></div></div></div></div>
  <div class="col-xl-6"><div class="card h-100"><div class="card-header bg-white fw-bold">New institutions by month</div>
    <div class="card-body"><div style="height:240px"><canvas id="chartTenants" data-series="tenants" data-chart-type="line" data-chart-label="Institutions"></canvas></div></div></div></div>
</div>

<div class="card mt-3"><div class="card-header bg-white fw-bold">Top institutions by verified revenue</div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Institution</th><th>Students</th><th>Courses</th><th>Verified revenue</th></tr></thead><tbody>
    <?php foreach ($topTenants as $t): ?>
    <tr><td><strong><?= e($t['name']) ?></strong></td><td><?= (int)$t['students'] ?></td><td><?= (int)$t['courses'] ?></td><td class="fw-semibold"><?= money((float)$t['revenue']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$topTenants): ?><tr><td colspan="4" class="text-center text-muted py-4">No institutions yet.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php require __DIR__ . '/footer.php'; ?>
