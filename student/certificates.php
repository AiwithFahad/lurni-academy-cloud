<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'My Certificates'; $active = 'certificates';

$s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
$s->execute([$me['id'], $tid]); $sid = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT cert.*, c.title FROM certificates cert JOIN courses c ON c.id=cert.course_id
    WHERE cert.student_id=? AND cert.tenant_id=? AND cert.revoked_at IS NULL ORDER BY cert.issued_at DESC");
$s->execute([$sid, $tid]); $rows = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <?php foreach ($rows as $r): ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body text-center py-4">
    <i class="bi bi-award display-5 text-warning"></i>
    <h3 class="h6 fw-bold mt-2"><?= e($r['title']) ?></h3>
    <p class="text-muted small mb-1"><?= e($r['course_title']) ?></p>
    <p class="text-muted small">Issued <?= e(date('M j, Y', strtotime($r['issued_at']))) ?> · Code <code><?= e($r['certificate_code']) ?></code><?= $r['grade'] ? ' · Grade ' . e($r['grade']) : '' ?> · <a href="/verify-certificate.php?code=<?= e($r['certificate_code']) ?>" target="_blank" rel="noopener">Public verification</a></p>
    <a class="btn btn-gradient" href="/student/certificate.php?id=<?= (int)$r['id'] ?>" target="_blank"><i class="bi bi-download me-1"></i>View / print certificate</a>
  </div></div></div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="col-12"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">Certificates are issued automatically when you complete 100% of a course.</p></div></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
