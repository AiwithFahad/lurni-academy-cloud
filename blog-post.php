<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
// Article page — reads the requested post from blog_posts (by ?slug=);
// with no slug it shows the latest published post. The original static
// article remains as the fallback when the table has no published posts.
$post = null;
try {
    $pdo = db();
    $slug = trim((string)($_GET['slug'] ?? ''));
    if ($slug !== '') {
        $s = $pdo->prepare("SELECT bp.*, u.name AS author_name FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.slug=? AND bp.status='published' LIMIT 1");
        $s->execute([$slug]); $post = $s->fetch() ?: null;
        if (!$post) { http_response_code(404); }
    } else {
        $s = $pdo->query("SELECT bp.*, u.name AS author_name FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.status='published' ORDER BY bp.published_at DESC, bp.id DESC LIMIT 1");
        $post = $s->fetch() ?: null;
    }
} catch (Throwable $ignored) { $post = null; }
$pageTitle = $post ? (string)$post['title'] : 'How Demo Academy reached 1,000 learners in 90 days';
$sitePage = 'blog';
require_once __DIR__ . '/includes/site-header.php';
?>
<?php if ($post): ?>
<section class="section-pad"><div class="container" style="max-width:780px">
  <span class="badge bg-primary-subtle text-primary"><?= e($post['category'] ?? 'General') ?></span>
  <h1 class="mt-3"><?= e($post['title']) ?></h1>
  <p class="text-muted"><i class="bi bi-calendar3"></i> <?= $post['published_at'] ? e(date('M j, Y', strtotime((string)$post['published_at']))) : '' ?> · <i class="bi bi-clock"></i> <?= max(1, (int)ceil(str_word_count(strip_tags((string)$post['content'])) / 200)) ?> min read · By <?= e($post['author_name'] ?? 'the Lurni team') ?></p>
  <?php if ($post['cover_image']): ?><img src="<?= e($post['cover_image']) ?>" alt="" class="img-fluid rounded-4 my-4"><?php endif; ?>
  <div class="article-body">
    <?php foreach (preg_split('/\r\n\r\n|\n\n/', trim((string)$post['content'])) as $para): if (trim($para) === '') continue; ?>
    <p><?= nl2br(e($para)) ?></p>
    <?php endforeach; ?>
  </div>
  <hr class="my-4">
  <div class="d-flex justify-content-between align-items-center">
    <a class="btn btn-outline-primary" href="/blog.php"><i class="bi bi-arrow-left"></i> All articles</a>
    <a class="btn btn-gradient" href="/auth/register.php">Start your academy</a>
  </div>
</div></section>
<?php else: ?>
<section class="section-pad"><div class="container" style="max-width:780px">
  <span class="badge bg-primary-subtle text-primary">Growth</span>
  <h1 class="mt-3">How Demo Academy reached 1,000 learners in 90 days</h1>
  <p class="text-muted"><i class="bi bi-calendar3"></i> Nov 2, 2026 · <i class="bi bi-clock"></i> 6 min read · By the Lurni team</p>
  <img src="/assets/img/benefit-chart.svg" alt="Growth chart illustration" class="img-fluid rounded-4 my-4">
  <div class="article-body">
    <p>When Demo Academy launched on Lurni, it had six courses and a lot of optimism. Ninety days later it passed one thousand enrolled learners. There was no ad budget behind the growth — just three repeatable moves.</p>
    <h2>1. Categories before courses</h2>
    <p>Instead of publishing courses randomly, the team first defined five clean categories and made sure each had at least one excellent flagship course. Browsing a tidy catalog converts far better than scrolling a mixed feed, and category pages became their top entry point from search.</p>
    <h2>2. Free previews everywhere</h2>
    <p>Every course got at least two free preview lessons. Preview completion turned out to be the strongest predictor of enrollment — students who finished a preview enrolled at four times the rate of students who only read the description.</p>
    <h2>3. Certificates as the finish line</h2>
    <p>The academy leaned into verifiable certificates: unique codes, real quizzes with pass marks, and graded assignments. Completion rates climbed because the certificate at the end was worth something, and graduates shared them — bringing the next cohort with them.</p>
    <h2>The takeaway</h2>
    <p>Growth did not come from more content. It came from structure (categories), trust (previews) and a meaningful finish line (certificates). Any academy on the platform can copy all three this week.</p>
  </div>
  <hr class="my-4">
  <div class="d-flex justify-content-between align-items-center">
    <a class="btn btn-outline-primary" href="/blog.php"><i class="bi bi-arrow-left"></i> All articles</a>
    <a class="btn btn-gradient" href="/auth/register.php">Start your academy</a>
  </div>
</div></section>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
