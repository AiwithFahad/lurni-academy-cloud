<?php
// Public marketing website layout header. Expects $pageTitle; loads DB lazily for nav state.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
$siteUser = current_user();
$sitePage = $sitePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<meta name="description" content="<?= e(APP_TAGLINE) ?> — online courses, live instructors, quizzes, certificates and a complete academy management platform.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="site-body">
<nav class="navbar navbar-expand-lg site-nav sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/index.php">
      <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
      <span class="fw-bold">Lurni <span class="text-gradient">Academy Cloud</span></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="siteNav">
      <ul class="navbar-nav mx-auto">
        <?php foreach ([['home','/index.php','Home'],['about','/about.php','About'],['courses','/courses.php','Courses'],['categories','/categories.php','Categories'],['instructors','/instructors.php','Instructors'],['pricing','/pricing.php','Pricing'],['blog','/blog.php','Blog'],['contact','/contact.php','Contact']] as [$k,$href,$label]): ?>
        <li class="nav-item"><a class="nav-link <?= $sitePage === $k ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <?php if ($siteUser): ?>
          <a class="btn btn-gradient" href="<?= e(role_home($siteUser['role'])) ?>"><i class="bi bi-speedometer2 me-1"></i>My Dashboard</a>
          <a class="btn btn-outline-secondary" href="/auth/logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-link text-decoration-none fw-semibold" href="/auth/login.php">Login</a>
          <a class="btn btn-gradient" href="/auth/register.php">Start Free Trial</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?= flash_html() ?>
