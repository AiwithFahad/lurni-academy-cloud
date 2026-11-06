<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// Printable certificate. Accessible by the owning student, or an
// admin/instructor of the same tenant.
$pdo = db();
$cert = null;
$code = getp('code');
if ($code !== '') {
    $s = $pdo->prepare('SELECT * FROM certificates WHERE certificate_code=? AND revoked_at IS NULL');
    $s->execute([$code]); $cert = $s->fetch() ?: null;
} else {
    $me = current_user();
    if (!$me) redirect('/auth/login.php');
    $certId = (int)($_GET['id'] ?? 0);
    $s = $pdo->prepare('SELECT * FROM certificates WHERE id=? AND tenant_id=? AND revoked_at IS NULL');
    $s->execute([$certId, (int)$me['tenant_id']]); $cert = $s->fetch() ?: null;
    if ($cert) {
        $allowed = false;
        if ($me['role'] === 'student') {
            $s = $pdo->prepare('SELECT COUNT(*) FROM students WHERE user_id=? AND id=?');
            $s->execute([$me['id'], (int)$cert['student_id']]); $allowed = (int)$s->fetchColumn() > 0;
        } elseif (in_array($me['role'], ['admin', 'instructor'], true)) $allowed = true;
        if (!$allowed) { http_response_code(403); exit('You do not have access to this certificate.'); }
    }
}
if (!$cert) { http_response_code(404); exit('Certificate not found.'); }

$s = $pdo->prepare('SELECT c.title, u.name AS student_name FROM courses c, students st JOIN users u ON u.id=st.user_id WHERE c.id=? AND st.id=?');
$s->execute([(int)$cert['course_id'], (int)$cert['student_id']]); $extra = $s->fetch() ?: [];
$s = $pdo->prepare('SELECT name FROM tenants WHERE id=?');
$s->execute([(int)$cert['tenant_id']]); $academy = (string)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate — <?= e($cert['certificate_code']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#eef2ff; font-family: Georgia, 'Times New Roman', serif; }
  .certificate { max-width: 960px; margin: 40px auto; background:#fff; border: 10px double #2563eb; padding: 64px 56px; text-align:center; position:relative; }
  .certificate::before { content:''; position:absolute; inset:14px; border:2px solid #c7d2fe; pointer-events:none; }
  .brand { font-family: Arial, sans-serif; letter-spacing:.35em; text-transform:uppercase; color:#2563eb; font-size:.8rem; }
  .gold { color:#b45309; }
  .seal { width:96px; height:96px; border-radius:50%; background:linear-gradient(135deg,#2563eb,#7c3aed); color:#fff; display:flex; align-items:center; justify-content:center; font-size:2.2rem; margin: 24px auto 0; font-family: Arial, sans-serif; }
  @media print { body { background:#fff; } .certificate { margin:0; } .no-print { display:none !important; } }
</style>
</head>
<body>
  <div class="certificate">
    <div class="brand"><?= e($academy) ?> · Lurni Academy Cloud</div>
    <h1 class="display-5 mt-4">Certificate of Completion</h1>
    <p class="text-muted mt-4">This is to certify that</p>
    <h2 class="display-6 fw-bold"><?= e($extra['student_name'] ?? '') ?></h2>
    <p class="text-muted">has successfully completed the course</p>
    <h3 class="fw-bold"><?= e($extra['title'] ?? $cert['title']) ?></h3>
    <?php if ($cert['grade']): ?><p class="gold fw-bold mt-3">Final grade: <?= e($cert['grade']) ?></p><?php endif; ?>
    <div class="seal">✓</div>
    <div class="d-flex justify-content-between mt-5 text-muted small">
      <span>Issued: <?= e(date('F j, Y', strtotime($cert['issued_at']))) ?></span>
      <span>Certificate code: <strong><?= e($cert['certificate_code']) ?></strong></span>
      <span>Verify at Lurni Academy Cloud</span>
    </div>
  </div>
  <div class="text-center no-print mb-5"><button class="btn btn-primary" onclick="window.print()">Print / save as PDF</button></div>
</body>
</html>
