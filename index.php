<?php
declare(strict_types=1);
$pageTitle = 'Learn Online — Courses, Instructors & Certificates';
$sitePage = 'home';
require_once __DIR__ . '/includes/site-header.php';

$pdo = db();
$featured = $categories = $instructors = $testimonials = $plans = $previews = [];
$stats = ['students' => 0, 'courses' => 0, 'instructors' => 0, 'certificates' => 0];
try {
    $featured = $pdo->query("SELECT c.*, cat.name AS category_name, u.name AS instructor_name,
        (SELECT COUNT(*) FROM enrollments en WHERE en.course_id=c.id) AS learners,
        (SELECT COUNT(*) FROM reviews r WHERE r.course_id=c.id AND r.status='published') AS review_count
        FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id
        LEFT JOIN instructors i ON i.id=c.instructor_id LEFT JOIN users u ON u.id=i.user_id
        WHERE c.status='published' AND c.is_featured=1 ORDER BY c.rating DESC LIMIT 8")->fetchAll();
    $categories = $pdo->query("SELECT c.name, MAX(c.icon) AS icon, MAX(c.color) AS color,
        COUNT(DISTINCT co.id) AS course_count FROM categories c
        LEFT JOIN courses co ON co.category_id=c.id AND co.status='published'
        WHERE c.status='active' GROUP BY c.name ORDER BY course_count DESC LIMIT 8")->fetchAll();
    $instructors = $pdo->query("SELECT i.*, u.name, (SELECT COUNT(*) FROM courses c WHERE c.instructor_id=i.id AND c.status='published') AS course_count,
        (SELECT COALESCE(AVG(c.rating),0) FROM courses c WHERE c.instructor_id=i.id) AS avg_rating
        FROM instructors i JOIN users u ON u.id=i.user_id WHERE i.status='active' LIMIT 8")->fetchAll();
    $testimonials = $pdo->query("SELECT r.*, c.title AS course_title, u.name AS student_name
        FROM reviews r JOIN courses c ON c.id=r.course_id JOIN students s ON s.id=r.student_id JOIN users u ON u.id=s.user_id
        WHERE r.status='published' ORDER BY r.created_at DESC LIMIT 6")->fetchAll();
    $plans = $pdo->query('SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY sort_order')->fetchAll();
    $previews = $pdo->query("SELECT l.title AS lesson_title, l.duration_minutes, c.id AS course_id, c.title AS course_title, c.thumbnail
        FROM lessons l JOIN courses c ON c.id=l.course_id WHERE l.is_preview=1 AND c.status='published' LIMIT 3")->fetchAll();
    $stats = [
        'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
        'courses' => (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn(),
        'instructors' => (int)$pdo->query('SELECT COUNT(*) FROM instructors')->fetchColumn(),
        'certificates' => (int)$pdo->query('SELECT COUNT(*) FROM certificates')->fetchColumn(),
    ];
} catch (Throwable $ignored) {}
?>
<!-- HERO -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="hero-badge"><i class="bi bi-stars me-1"></i>Trusted by academies & training centers</span>
        <h1 class="hero-title">Take your <span class="text-gradient">knowledge</span> a degree further</h1>
        <p class="hero-sub">Learn from expert instructors with structured courses, quizzes, assignments and verifiable certificates — or launch your own academy on the same platform.</p>
        <form class="hero-search" action="/courses.php" method="get" role="search">
          <i class="bi bi-search"></i>
          <input type="search" name="q" placeholder="Search courses, topics, instructors…" aria-label="Search courses">
          <button class="btn btn-gradient" type="submit">Explore Courses</button>
        </form>
        <div class="hero-points">
          <span><i class="bi bi-patch-check-fill"></i> Verified certificates</span>
          <span><i class="bi bi-people-fill"></i> Expert instructors</span>
          <span><i class="bi bi-infinity"></i> Lifetime access</span>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-visual">
          <img src="/assets/img/hero.svg" alt="Lurni Academy Cloud learning dashboard preview" class="img-fluid">
          <div class="floating-card fc-1"><i class="bi bi-award text-warning"></i><div><strong>12,400+</strong><small>Certificates issued</small></div></div>
          <div class="floating-card fc-2"><i class="bi bi-play-circle text-primary"></i><div><strong>850+</strong><small>Video lessons</small></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TRUSTED BY -->
<section class="trusted-strip">
  <div class="container">
    <p class="text-center text-muted small text-uppercase fw-semibold mb-3">Trusted by universities & education partners</p>
    <div class="logo-marquee">
      <?php foreach (['Stanford Online','Oxford Learning','MIT OpenCourse','Cambridge Prep','Tech University','Global Academy','SkillWorks','EduPartners'] as $logo): ?>
      <span class="logo-item"><i class="bi bi-bank me-2"></i><?= e($logo) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CATEGORIES -->
<section class="section-pad">
  <div class="container">
    <div class="section-head">
      <div><span class="section-eyebrow">Categories</span><h2>Explore popular categories</h2></div>
      <a class="btn btn-outline-primary" href="/categories.php">All categories <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3">
      <?php foreach ($categories as $cat): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a class="category-card" href="/courses.php?category=<?= e(slugify($cat['name'])) ?>">
          <span class="cat-icon" style="--cat:<?= e($cat['color'] ?: '#4f46e5') ?>"><i class="bi <?= e($cat['icon'] ?: 'bi-grid') ?>"></i></span>
          <span class="fw-semibold d-block"><?= e($cat['name']) ?></span>
          <small class="text-muted"><?= (int)$cat['course_count'] ?> courses</small>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (!$categories): ?><p class="text-muted">Categories will appear here once academies publish courses.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- FEATURED COURSES -->
<section class="section-pad bg-soft">
  <div class="container">
    <div class="section-head">
      <div><span class="section-eyebrow">Featured</span><h2>Featured courses students love</h2></div>
      <a class="btn btn-outline-primary" href="/courses.php">Browse all courses <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
      <?php foreach ($featured as $c): ?>
      <div class="col-md-6 col-lg-3">
        <article class="course-card">
          <a href="/course.php?id=<?= (int)$c['id'] ?>"><img src="<?= e($c['thumbnail'] ?: '/assets/img/course-default.svg') ?>" alt="<?= e($c['title']) ?>" class="course-thumb"></a>
          <div class="course-body">
            <div class="d-flex justify-content-between align-items-center">
              <span class="badge bg-primary-subtle text-primary"><?= e($c['category_name'] ?? 'General') ?></span>
              <span class="small text-warning fw-semibold">★ <?= number_format((float)$c['rating'], 1) ?> <span class="text-muted fw-normal">(<?= (int)$c['review_count'] ?>)</span></span>
            </div>
            <a class="course-title" href="/course.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a>
            <div class="course-meta"><span><i class="bi bi-person-video3"></i> <?= e($c['instructor_name'] ?? 'Instructor') ?></span><span><i class="bi bi-people"></i> <?= (int)$c['learners'] ?> learners</span></div>
            <div class="course-foot">
              <span class="price"><?= (float)$c['price'] > 0 ? money($c['price']) : 'Free' ?><?php if (!empty($c['old_price'])): ?> <del><?= money($c['old_price']) ?></del><?php endif; ?></span>
              <a class="btn btn-sm btn-gradient" href="/course.php?id=<?= (int)$c['id'] ?>">View course</a>
            </div>
          </div>
        </article>
      </div>
      <?php endforeach; ?>
      <?php if (!$featured): ?><p class="text-muted">Featured courses will appear here soon.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- STATS -->
<section class="stats-band">
  <div class="container">
    <div class="row text-center g-4">
      <?php foreach ([['bi-people', $stats['students'], 'Active students'], ['bi-journal-bookmark', $stats['courses'], 'Published courses'], ['bi-person-video3', $stats['instructors'], 'Expert instructors'], ['bi-award', $stats['certificates'], 'Certificates issued']] as [$icon, $num, $label]): ?>
      <div class="col-6 col-lg-3">
        <div class="stat-item"><i class="bi <?= $icon ?>"></i><div class="stat-num" data-count="<?= (int)$num ?>"><?= number_format((int)$num) ?>+</div><div class="stat-label"><?= $label ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section-pad">
  <div class="container">
    <div class="section-head center"><div><span class="section-eyebrow">How it works</span><h2>Start learning in four steps</h2></div></div>
    <div class="row g-4">
      <?php foreach ([
        ['bi-person-plus', 'Create your account', 'Register free as a student — or launch your own academy workspace in minutes.'],
        ['bi-search', 'Find your course', 'Browse categories, compare instructors, ratings and previews before you enroll.'],
        ['bi-play-btn', 'Learn & practice', 'Watch lessons, download resources, take quizzes and submit assignments.'],
        ['bi-award', 'Earn your certificate', 'Finish 100% of a course and download a verifiable certificate instantly.'],
      ] as $i => [$icon, $t, $d]): ?>
      <div class="col-md-6 col-lg-3">
        <div class="step-card"><span class="step-num"><?= $i + 1 ?></span><i class="bi <?= $icon ?> step-icon"></i><h3 class="h6 fw-bold"><?= $t ?></h3><p class="text-muted small mb-0"><?= $d ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- INSTRUCTORS -->
<section class="section-pad bg-soft">
  <div class="container">
    <div class="section-head">
      <div><span class="section-eyebrow">Instructors</span><h2>Learn from top instructors</h2></div>
      <a class="btn btn-outline-primary" href="/instructors.php">All instructors <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-4">
      <?php foreach ($instructors as $ins): ?>
      <div class="col-md-6 col-lg-3">
        <article class="instructor-card">
          <span class="avatar-circle lg mx-auto"><?= e(initials($ins['name'])) ?></span>
          <h3 class="h6 fw-bold mt-3 mb-1"><?= e($ins['name']) ?></h3>
          <p class="text-muted small mb-2"><?= e($ins['title'] ?? 'Instructor') ?></p>
          <div class="small text-warning fw-semibold">★ <?= number_format((float)$ins['avg_rating'], 1) ?> <span class="text-muted fw-normal">· <?= (int)$ins['course_count'] ?> courses</span></div>
          <a class="btn btn-sm btn-outline-primary mt-3" href="/instructor.php?id=<?= (int)$ins['id'] ?>">View profile</a>
        </article>
      </div>
      <?php endforeach; ?>
      <?php if (!$instructors): ?><p class="text-muted">Instructor profiles will appear here soon.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- BENEFITS -->
<section class="section-pad">
  <div class="container">
    <div class="section-head center"><div><span class="section-eyebrow">Why Lurni</span><h2>Everything an academy needs, in one place</h2></div></div>
    <div class="row g-4">
      <?php foreach ([
        ['/assets/img/benefit-learn.svg', 'bi-collection-play', 'Structured learning paths', 'Modules, lessons, previews and resources organized the way great teachers think.'],
        ['/assets/img/benefit-quiz.svg', 'bi-patch-question', 'Quizzes & question banks', 'Auto-graded quizzes with reusable question banks, pass marks and instant results.'],
        ['/assets/img/benefit-cert.svg', 'bi-award', 'Verifiable certificates', 'Certificates with unique codes are issued automatically at 100% completion.'],
        ['/assets/img/benefit-chart.svg', 'bi-graph-up-arrow', 'Progress analytics', 'Students see their progress; academies see enrollments, revenue and completion in real time.'],
      ] as [$img, $icon, $t, $d]): ?>
      <div class="col-md-6 col-lg-3">
        <article class="benefit-card"><img src="<?= $img ?>" alt="" class="benefit-img"><h3 class="h6 fw-bold mt-3"><?= $t ?></h3><p class="text-muted small mb-0"><?= $d ?></p></article>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="section-pad bg-soft">
  <div class="container">
    <div class="section-head center"><div><span class="section-eyebrow">Reviews</span><h2>What students say</h2></div></div>
    <div class="row g-4">
      <?php foreach ($testimonials as $t): ?>
      <div class="col-md-6 col-lg-4">
        <figure class="testimonial-card">
          <div class="text-warning"><?= stars((float)$t['rating']) ?></div>
          <blockquote class="mb-3">“<?= e($t['comment']) ?>”</blockquote>
          <figcaption class="d-flex align-items-center gap-2">
            <span class="avatar-circle"><?= e(initials($t['student_name'])) ?></span>
            <span><strong class="d-block lh-1"><?= e($t['student_name']) ?></strong><small class="text-muted"><?= e($t['course_title']) ?></small></span>
          </figcaption>
        </figure>
      </div>
      <?php endforeach; ?>
      <?php if (!$testimonials): ?><p class="text-muted text-center">Student reviews will appear here soon.</p><?php endif; ?>
    </div>
  </div>
</section>

<!-- COURSE PREVIEWS -->
<?php if ($previews): ?>
<section class="section-pad">
  <div class="container">
    <div class="section-head"><div><span class="section-eyebrow">Free previews</span><h2>Watch before you enroll</h2></div></div>
    <div class="row g-4">
      <?php foreach ($previews as $p): ?>
      <div class="col-md-4">
        <a class="preview-card" href="/course.php?id=<?= (int)$p['course_id'] ?>">
          <img src="<?= e($p['thumbnail'] ?: '/assets/img/course-default.svg') ?>" alt="" class="course-thumb">
          <span class="play-badge"><i class="bi bi-play-fill"></i></span>
          <div class="p-3"><strong class="d-block"><?= e($p['lesson_title']) ?></strong>
          <small class="text-muted"><?= e($p['course_title']) ?> · <?= (int)$p['duration_minutes'] ?> min</small></div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- DASHBOARD PREVIEW -->
<section class="section-pad bg-soft">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6"><img src="/assets/img/dashboard-preview.svg" alt="LMS admin dashboard preview" class="img-fluid rounded-4 shadow"></div>
      <div class="col-lg-6">
        <span class="section-eyebrow">For academies</span>
        <h2 class="mb-3">Run your whole academy from one dashboard</h2>
        <p class="text-muted">Students, instructors, enrollments, payments and reports — every number computed live from your academy data, with subscriptions and plan limits built in.</p>
        <ul class="check-list">
          <li><i class="bi bi-check-circle-fill"></i> Multi-tenant workspaces with isolated data</li>
          <li><i class="bi bi-check-circle-fill"></i> Super Admin platform analytics (MRR, ARR, tenants)</li>
          <li><i class="bi bi-check-circle-fill"></i> Plans, trials, coupons, invoices & grace periods</li>
          <li><i class="bi bi-check-circle-fill"></i> Role-based portals for admins, instructors & students</li>
        </ul>
        <a class="btn btn-gradient btn-lg mt-2" href="/auth/register.php">Launch your academy</a>
      </div>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="section-pad">
  <div class="container">
    <div class="section-head center"><div><span class="section-eyebrow">Pricing</span><h2>Plans for every academy size</h2>
    <div class="billing-toggle mt-3">
      <span>Monthly</span>
      <div class="form-check form-switch d-inline-block mx-2"><input class="form-check-input" type="checkbox" id="cycleToggle" aria-label="Toggle yearly billing"></div>
      <span>Yearly <span class="badge bg-success-subtle text-success">2 months free</span></span>
    </div></div></div>
    <div class="row g-4 justify-content-center">
      <?php foreach ($plans as $p): $feats = json_decode((string)($p['features'] ?? '[]'), true) ?: []; $popular = $p['slug'] === 'professional'; ?>
      <div class="col-md-6 col-lg-<?= count($plans) > 4 ? '2' : '3' ?>">
        <article class="pricing-card <?= $popular ? 'popular' : '' ?>">
          <?php if ($popular): ?><span class="popular-badge">Most popular</span><?php endif; ?>
          <h3 class="h6 fw-bold"><?= e($p['name']) ?></h3>
          <div class="price-line"><span class="price-amount" data-monthly="<?= (float)$p['price_monthly'] ?>" data-yearly="<?= (float)$p['price_yearly'] ?>"><?= money($p['price_monthly']) ?></span><span class="text-muted price-cycle">/month</span></div>
          <p class="text-muted small"><?= e($p['description'] ?? '') ?></p>
          <ul class="check-list small">
            <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_students'] < 0 ? 'Unlimited' : number_format((int)$p['max_students']) ?> students</li>
            <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_instructors'] < 0 ? 'Unlimited' : (int)$p['max_instructors'] ?> instructors</li>
            <li><i class="bi bi-check-circle-fill"></i> <?= (int)$p['max_courses'] < 0 ? 'Unlimited' : (int)$p['max_courses'] ?> courses</li>
            <?php foreach (array_slice($feats, 0, 2) as $f): ?><li><i class="bi bi-check-circle-fill"></i> <?= e($f) ?></li><?php endforeach; ?>
          </ul>
          <a class="btn <?= $popular ? 'btn-gradient' : 'btn-outline-primary' ?> w-100 mt-3" href="/auth/register.php?plan=<?= e($p['slug']) ?>">Start free</a>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- SUCCESS STORIES + BLOG STRIP -->
<section class="section-pad bg-soft">
  <div class="container">
    <div class="section-head"><div><span class="section-eyebrow">From the blog</span><h2>Success stories & learning tips</h2></div>
    <a class="btn btn-outline-primary" href="/blog.php">Read the blog <i class="bi bi-arrow-right"></i></a></div>
    <div class="row g-4">
      <?php foreach ([
        ['How Demo Academy reached 1,000 learners in 90 days', 'Growth', 'The exact playbook: categories, featured courses and certificate incentives.'],
        ['Designing quizzes students actually finish', 'Teaching', 'Question banks, pass marks and instant feedback that keep completion rates high.'],
        ['From classroom to cloud: a training center migration story', 'Stories', 'How a 12-teacher center moved its entire catalog online in two weeks.'],
      ] as [$t, $tag, $d]): ?>
      <div class="col-md-4">
        <article class="blog-card"><span class="badge bg-primary-subtle text-primary"><?= $tag ?></span>
        <h3 class="h6 fw-bold mt-2"><a href="/blog-post.php" class="text-decoration-none text-dark"><?= $t ?></a></h3>
        <p class="text-muted small mb-0"><?= $d ?></p></article>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section-pad">
  <div class="container" style="max-width:820px">
    <div class="section-head center"><div><span class="section-eyebrow">FAQ</span><h2>Frequently asked questions</h2></div></div>
    <div class="accordion" id="faqAcc">
      <?php foreach ([
        ['Can I really run my own academy on Lurni?', 'Yes. Register your organization and you get an isolated tenant workspace with your own students, instructors, courses, branding and subscription — no shared data with other academies.'],
        ['Do students get certificates?', 'Yes. When a student completes 100% of a course, a certificate with a unique verification code is issued automatically and can be downloaded from the student portal.'],
        ['How do payments and plans work?', 'Academies subscribe to Free, Starter, Professional, Business or Enterprise plans with monthly or yearly billing, free trials, coupons and invoices. Payments are recorded and verified before activation — never auto-marked as paid.'],
        ['Can instructors create their own courses?', 'Instructors get their own portal to build courses, modules, video lessons, quizzes and assignments, grade submissions and message students.'],
      ] as $i => [$q, $a]): ?>
      <div class="accordion-item">
        <h3 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= e($q) ?></button></h3>
        <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body text-muted"><?= e($a) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- NEWSLETTER + CONTACT CTA -->
<section class="cta-band">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <h2 class="text-white mb-2">Get learning tips in your inbox</h2>
        <p class="text-white-50 mb-0">One practical email a week — course launches, teaching tactics and platform updates.</p>
      </div>
      <div class="col-lg-6">
        <form class="newsletter-form" method="post" action="/contact.php">
          <?= csrf_field() ?>
          <input type="hidden" name="subject" value="Newsletter signup">
          <input type="hidden" name="name" value="Newsletter subscriber">
          <input type="email" name="email" required placeholder="Your email address" aria-label="Email address">
          <input type="hidden" name="message" value="Please subscribe me to the Lurni newsletter.">
          <button class="btn btn-light fw-semibold" type="submit">Subscribe</button>
        </form>
        <div class="text-end mt-3"><a class="text-white" href="/contact.php">Talk to our team <i class="bi bi-arrow-right"></i></a></div>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
