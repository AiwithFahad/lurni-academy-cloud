<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
// Public certificate verification — anyone with a certificate code can
// confirm it is genuine: /verify-certificate.php?code=LURNI-2026-XXXXXXXX
$pdo = db();
$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$cert = null; $searched = $code !== '';
if ($searched) {
    $s = $pdo->prepare("SELECT cert.*, u.name AS student_name, c.title AS course_title,
            t.name AS institution_name, t.logo AS institution_logo
        FROM certificates cert
        JOIN students st ON st.id=cert.student_id JOIN users u ON u.id=st.user_id
        JOIN courses c ON c.id=cert.course_id JOIN tenants t ON t.id=cert.tenant_id
        WHERE cert.certificate_code=? LIMIT 1");
    $s->execute([$code]); $cert = $s->fetch() ?: null;
}
$pageTitle = 'Verify a Certificate';
require __DIR__ . '/includes/site-header.php';
?>
<section class="public-section">
  <div class="container" style="max-width:760px">
    <div class="text-center mb-4">
      <span class="section-kicker">Certificate Verification</span>
      <h1 class="fw-bold">Verify a Lurni certificate</h1>
      <p class="text-muted">Enter the unique code printed on the certificate to confirm it was issued by the institution.</p>
    </div>
    <form method="get" class="card shadow-sm mb-4"><div class="card-body d-flex gap-2">
      <input class="form-control form-control-lg" name="code" value="<?= e($code) ?>" placeholder="e.g. LURNI-2026-AB12CD34" required>
      <button class="btn btn-gradient btn-lg" type="submit"><i class="bi bi-search"></i> Verify</button>
    </div></form>

    <?php if ($searched && !$cert): ?>
    <div class="alert alert-danger text-center py-4">
      <i class="bi bi-x-octagon display-6 d-block mb-2"></i>
      <strong>Certificate not found.</strong><br>
      <span class="text-muted">No certificate with code <strong><?= e($code) ?></strong> exists. Check the code and try again.</span>
    </div>
    <?php elseif ($cert): ?>
    <?php $revoked = !empty($cert['revoked_at']); ?>
    <div class="card shadow-sm border-<?= $revoked ? 'danger' : 'success' ?>">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div class="d-flex align-items-center gap-3">
            <?php if ($cert['institution_logo']): ?><img src="/<?= e($cert['institution_logo']) ?>" alt="" style="height:48px" class="rounded"><?php endif; ?>
            <div><div class="small text-muted">Issued by</div><div class="fw-bold fs-5"><?= e($cert['institution_name']) ?></div></div>
          </div>
          <span class="badge bg-<?= $revoked ? 'danger' : 'success' ?> fs-6"><?= $revoked ? 'REVOKED' : '✓ VERIFIED' ?></span>
        </div>
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">Student</dt><dd class="col-sm-8 fw-semibold"><?= e($cert['student_name']) ?></dd>
          <dt class="col-sm-4 text-muted">Course</dt><dd class="col-sm-8"><?= e($cert['course_title']) ?></dd>
          <dt class="col-sm-4 text-muted">Certificate</dt><dd class="col-sm-8"><?= e($cert['title'] ?? 'Certificate of Completion') ?></dd>
          <dt class="col-sm-4 text-muted">Certificate code</dt><dd class="col-sm-8"><code><?= e($cert['certificate_code']) ?></code></dd>
          <dt class="col-sm-4 text-muted">Issued</dt><dd class="col-sm-8"><?= e(date('F j, Y', strtotime((string)$cert['issued_at']))) ?></dd>
          <?php if (!empty($cert['grade'])): ?><dt class="col-sm-4 text-muted">Result</dt><dd class="col-sm-8"><?= e($cert['grade']) ?> (<?= (float)$cert['final_score'] ?>%)</dd><?php endif; ?>
          <?php if ($revoked): ?><dt class="col-sm-4 text-muted">Revoked</dt><dd class="col-sm-8 text-danger"><?= e(date('F j, Y', strtotime((string)$cert['revoked_at']))) ?> — this certificate is no longer valid.</dd><?php endif; ?>
        </dl>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/site-footer.php'; ?>
