<?php
declare(strict_types=1);
$pageTitle = 'About Us';
$sitePage = 'about';
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$stats = ['students' => 0, 'courses' => 0, 'instructors' => 0, 'tenants' => 0];
try {
    $stats = [
        'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
        'courses' => (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn(),
        'instructors' => (int)$pdo->query('SELECT COUNT(*) FROM instructors')->fetchColumn(),
        'tenants' => (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE status='active'")->fetchColumn(),
    ];
} catch (Throwable $ignored) {}
?>
<section class="page-hero"><div class="container">
  <span class="section-eyebrow">About us</span>
  <h1>Learning should be structured, measurable and human</h1>
  <p class="text-muted" style="max-width:720px"><?= e(APP_NAME) ?> is a complete Learning Management SaaS built for academies, schools, universities and training centers. One platform runs the public course catalog, the academy back office, instructor studios and student portals — with subscriptions, billing and analytics included, not bolted on.</p>
</div></section>
<section class="section-pad pt-0"><div class="container">
  <div class="row g-4 text-center">
    <?php foreach ([['bi-people', $stats['students'], 'Students learning'], ['bi-journal-bookmark', $stats['courses'], 'Published courses'], ['bi-person-video3', $stats['instructors'], 'Expert instructors'], ['bi-buildings', $stats['tenants'], 'Academies onboard']] as [$icon, $n, $l]): ?>
    <div class="col-6 col-lg-3"><div class="stat-card"><i class="bi <?= $icon ?> fs-3 text-primary"></i><div class="stat-num"><?= number_format((int)$n) ?>+</div><div class="text-muted"><?= $l ?></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="row g-4 mt-2">
    <?php foreach ([
      ['bi-bullseye', 'Our mission', 'Give every academy — from a solo tutor to a university — the same quality of learning platform that only large institutions could afford before.'],
      ['bi-shield-check', 'Trust by design', 'Tenant data is isolated at the database level, passwords are hashed, every form is CSRF-protected, and payments are only marked paid after verified confirmation.'],
      ['bi-graph-up-arrow', 'Proof over promises', 'Every dashboard number in Lurni is computed from real records — enrollments, payments and progress — never hardcoded marketing figures.'],
    ] as [$icon, $t, $d]): ?>
    <div class="col-md-4"><div class="card p-4 h-100"><i class="bi <?= $icon ?> fs-3 text-primary mb-2"></i><h2 class="h6 fw-bold"><?= $t ?></h2><p class="text-muted mb-0"><?= $d ?></p></div></div>
    <?php endforeach; ?>
  </div>
  <div class="cta-inline mt-5">
    <div><h2 class="h4 fw-bold mb-1">Ready to launch your academy?</h2><p class="text-muted mb-0">Start with a free plan or a 14-day trial of any paid plan.</p></div>
    <a class="btn btn-gradient btn-lg" href="/auth/register.php">Start free trial</a>
  </div>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
