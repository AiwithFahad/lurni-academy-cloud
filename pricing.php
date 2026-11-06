<?php
declare(strict_types=1);
$pageTitle = 'Pricing — Plans for Every Academy';
$sitePage = 'pricing';
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$plans = [];
try { $plans = $pdo->query('SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY sort_order')->fetchAll(); } catch (Throwable $ignored) {}
?>
<section class="page-hero"><div class="container text-center">
  <span class="section-eyebrow">Pricing</span>
  <h1>Simple pricing that grows with your academy</h1>
  <p class="text-muted mx-auto" style="max-width:620px">Every plan includes the full LMS: courses, quizzes, assignments, certificates, role-based portals and tenant isolation. Paid plans start with a free trial.</p>
  <div class="billing-toggle mt-3"><span>Monthly</span>
    <div class="form-check form-switch d-inline-block mx-2"><input class="form-check-input" type="checkbox" id="cycleToggle" aria-label="Toggle yearly billing"></div>
    <span>Yearly <span class="badge bg-success-subtle text-success">2 months free</span></span></div>
</div></section>
<section class="section-pad pt-4"><div class="container">
  <div class="row g-4 justify-content-center">
    <?php foreach ($plans as $p): $feats = json_decode((string)($p['features'] ?? '[]'), true) ?: []; $popular = $p['slug'] === 'professional'; ?>
    <div class="col-md-6 col-lg-<?= count($plans) > 4 ? '2' : '3' ?>">
      <article class="pricing-card h-100 <?= $popular ? 'popular' : '' ?>">
        <?php if ($popular): ?><span class="popular-badge">Most popular</span><?php endif; ?>
        <h2 class="h6 fw-bold"><?= e($p['name']) ?></h2>
        <div class="price-line"><span class="price-amount" data-monthly="<?= (float)$p['price_monthly'] ?>" data-yearly="<?= (float)$p['price_yearly'] ?>"><?= money($p['price_monthly']) ?></span><span class="text-muted price-cycle">/month</span></div>
        <p class="text-muted small"><?= e($p['description'] ?? '') ?></p>
        <ul class="check-list small">
          <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_students'] < 0 ? 'Unlimited' : number_format((int)$p['max_students']) ?> students</li>
          <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_instructors'] < 0 ? 'Unlimited' : (int)$p['max_instructors'] ?> instructors</li>
          <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_courses'] < 0 ? 'Unlimited' : (int)$p['max_courses'] ?> courses</li>
          <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['storage_mb'] < 0 ? 'Unlimited' : number_format((int)$p['storage_mb']) . ' MB' ?> storage</li>
          <?php foreach ($feats as $f): ?><li><i class="bi bi-check-circle-fill"></i> <?= e($f) ?></li><?php endforeach; ?>
        </ul>
        <?php if ((int)$p['trial_days'] > 0 && (float)$p['price_monthly'] > 0): ?><p class="small text-primary fw-semibold mt-2 mb-0"><?= (int)$p['trial_days'] ?>-day free trial included</p><?php endif; ?>
        <a class="btn <?= $popular ? 'btn-gradient' : 'btn-outline-primary' ?> w-100 mt-3" href="/auth/register.php?plan=<?= e($p['slug']) ?>">Choose <?= e($p['name']) ?></a>
      </article>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="card p-4 mt-5">
    <h2 class="h6 fw-bold">Good to know</h2>
    <div class="row g-3 small text-muted">
      <div class="col-md-4"><strong class="text-dark d-block">Coupons</strong>Use code <code>WELCOME10</code> for 10% off your first verified payment.</div>
      <div class="col-md-4"><strong class="text-dark d-block">Grace period</strong>Subscriptions include a 7-day grace period after the paid period ends before access is limited.</div>
      <div class="col-md-4"><strong class="text-dark d-block">Honest billing</strong>Payments are recorded as pending and only marked completed after verified confirmation.</div>
    </div>
  </div>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
