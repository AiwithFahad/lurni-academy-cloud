<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Certificates'; $active = 'certificates';
require_permission($pdo, 'certificates.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'issue') {
        $enrollId = (int)($_POST['enrollment_id'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM enrollments WHERE id=? AND tenant_id=?');
        $s->execute([$enrollId, $tid]); $en = $s->fetch();
        if ($en && (float)$en['progress_percent'] >= 100) {
            $code = 'LURNI-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            try {
                $pdo->prepare('INSERT INTO certificates(tenant_id,student_id,course_id,certificate_code) VALUES(?,?,?,?)')
                    ->execute([$tid, $en['student_id'], $en['course_id'], $code]);
                $pdo->prepare("UPDATE enrollments SET status='completed', completed_at=COALESCE(completed_at, NOW()) WHERE id=?")->execute([$enrollId]);
                flash('success', 'Certificate issued with code ' . $code . '.');
                log_activity($pdo, 'Certificate issued (' . $code . ')');
            } catch (Throwable $ex) { flash('error', 'A certificate already exists for this student and course.'); }
        } else {
            flash('error', 'Certificates can only be issued at 100% course completion.');
        }
    } elseif ($action === 'revoke') {
        $pdo->prepare('DELETE FROM certificates WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Certificate revoked.');
    }
    redirect('/admin/certificates.php');
}

$verify = getp('verify');
$verifyResult = null;
if ($verify !== '') {
    $s = $pdo->prepare("SELECT cert.*, u.name AS student_name, c.title AS course_title FROM certificates cert
        JOIN students st ON st.id=cert.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=cert.course_id
        WHERE cert.certificate_code=? AND cert.tenant_id=?");
    $s->execute([$verify, $tid]); $verifyResult = $s->fetch() ?: false;
}
$s = $pdo->prepare("SELECT cert.*, u.name AS student_name, c.title AS course_title FROM certificates cert
    JOIN students st ON st.id=cert.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=cert.course_id
    WHERE cert.tenant_id=? ORDER BY cert.issued_at DESC LIMIT 50");
$s->execute([$tid]); $rows = $s->fetchAll();
$s = $pdo->prepare("SELECT en.id, u.name AS student_name, c.title AS course_title FROM enrollments en
    JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id
    WHERE en.tenant_id=? AND en.progress_percent>=100 AND NOT EXISTS (SELECT 1 FROM certificates cert WHERE cert.student_id=en.student_id AND cert.course_id=en.course_id)
    ORDER BY en.completed_at DESC");
$s->execute([$tid]); $eligible = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Student</th><th>Course</th><th>Issued</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
    <tr><td><code><?= e($r['certificate_code']) ?></code></td><td><?= e($r['student_name']) ?></td><td><small><?= e($r['course_title']) ?></small></td>
      <td><small><?= e(date('M j, Y', strtotime($r['issued_at']))) ?></small></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/student/certificate.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> View</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Revoke this certificate?')"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Revoke</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No certificates issued yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4">
    <div class="card mb-3"><div class="card-header bg-white fw-bold">Verify a certificate</div><div class="card-body">
      <form method="get" class="d-flex gap-2"><input class="form-control" name="verify" value="<?= e($verify) ?>" placeholder="e.g. LURNI-2026-AB12CD34"><button class="btn btn-primary">Verify</button></form>
      <?php if ($verify !== ''): ?>
        <?php if ($verifyResult): ?><div class="alert alert-success mt-3 mb-0"><i class="bi bi-patch-check me-1"></i>Valid — issued to <strong><?= e($verifyResult['student_name']) ?></strong> for “<?= e($verifyResult['course_title']) ?>” on <?= e(date('M j, Y', strtotime($verifyResult['issued_at']))) ?>.</div>
        <?php else: ?><div class="alert alert-danger mt-3 mb-0">No certificate with this code exists in your academy.</div><?php endif; ?>
      <?php endif; ?>
    </div></div>
    <div class="card"><div class="card-header bg-white fw-bold">Eligible for certificates (100% complete)</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($eligible as $en): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center"><span><strong><?= e($en['student_name']) ?></strong><br><small class="text-muted"><?= e($en['course_title']) ?></small></span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="issue"><input type="hidden" name="enrollment_id" value="<?= (int)$en['id'] ?>"><button class="btn btn-sm btn-success">Issue</button></form></li>
      <?php endforeach; ?>
      <?php if (!$eligible): ?><li class="list-group-item text-muted">Nobody is waiting for a certificate right now.</li><?php endif; ?>
      </ul></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
