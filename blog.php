<?php
declare(strict_types=1);
$pageTitle = 'Blog — Learning Tips & Academy Growth';
$sitePage = 'blog';
require_once __DIR__ . '/includes/site-header.php';
$posts = [
    ['How Demo Academy reached 1,000 learners in 90 days', 'Growth', 'Nov 2, 2026', '6 min read', 'The exact playbook: categories, featured courses and certificate incentives that turned a small academy into a growing online school.'],
    ['Designing quizzes students actually finish', 'Teaching', 'Oct 26, 2026', '4 min read', 'Question banks, pass marks and instant feedback that keep completion rates high without dumbing anything down.'],
    ['From classroom to cloud: a training center migration story', 'Stories', 'Oct 18, 2026', '5 min read', 'How a 12-teacher center moved its entire catalog online in two weeks — and what they would do differently.'],
    ['Certificates that employers actually trust', 'Careers', 'Oct 9, 2026', '4 min read', 'Unique verification codes, real assessment and transparent criteria: the three ingredients of a credible certificate.'],
    ['The academy metrics dashboard: 7 numbers that matter', 'Analytics', 'Sep 30, 2026', '7 min read', 'MRR is for platforms; academies live on enrollment velocity, completion rate and review scores. Here is how to read them.'],
    ['Pricing your first online course', 'Business', 'Sep 21, 2026', '5 min read', 'Anchor pricing, trials and coupons — a pragmatic guide for instructors publishing their first paid course.'],
];
?>
<section class="page-hero"><div class="container">
  <h1>The Lurni blog</h1>
  <p class="text-muted mb-0">Teaching tactics, academy growth stories and platform updates.</p>
</div></section>
<section class="section-pad pt-4"><div class="container">
  <div class="row g-4">
    <?php foreach ($posts as [$t, $tag, $date, $read, $d]): ?>
    <div class="col-md-6 col-lg-4">
      <article class="blog-card h-100">
        <span class="badge bg-primary-subtle text-primary"><?= $tag ?></span>
        <h2 class="h6 fw-bold mt-2"><a href="/blog-post.php" class="text-decoration-none text-dark"><?= $t ?></a></h2>
        <p class="text-muted small"><?= $d ?></p>
        <div class="small text-muted d-flex justify-content-between"><span><i class="bi bi-calendar3"></i> <?= $date ?></span><span><i class="bi bi-clock"></i> <?= $read ?></span></div>
        <a class="btn btn-sm btn-outline-primary mt-3" href="/blog-post.php">Read article</a>
      </article>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
