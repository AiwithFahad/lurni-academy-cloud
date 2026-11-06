<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Reviews & Ratings'; $active = 'reviews';
require_permission($pdo, 'reviews.manage');

function refresh_course_rating(PDO $pdo, int $tid, int $courseId): void {
    $s = $pdo->prepare("SELECT COALESCE(AVG(rating),0) FROM reviews WHERE course_id=? AND tenant_id=? AND status='published'");
    $s->execute([$courseId, $tid]);
    $pdo->prepare('UPDATE courses SET rating=? WHERE id=? AND tenant_id=?')->execute([round((float)$s->fetchColumn(), 2), $courseId, $tid]);
}
if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = post('action');
    $s = $pdo->prepare('SELECT course_id FROM reviews WHERE id=? AND tenant_id=?');
    $s->execute([$id, $tid]); $courseId = (int)$s->fetchColumn();
    if ($action === 'toggle' && $courseId) {
        $pdo->prepare("UPDATE reviews SET status=IF(status='published','hidden','published') WHERE id=? AND tenant_id=?")->execute([$id, $tid]);
        refresh_course_rating($pdo, $tid, $courseId);
        flash('success', 'Review visibility updated and course rating recalculated.');
    } elseif ($action === 'delete' && $courseId) {
        $pdo->prepare('DELETE FROM reviews WHERE id=? AND tenant_id=?')->execute([$id, $tid]);
        refresh_course_rating($pdo, $tid, $courseId);
        flash('success', 'Review deleted and course rating recalculated.');
    }
    redirect('/admin/reviews.php');
}
$s = $pdo->prepare("SELECT r.*, c.title AS course_title, u.name AS student_name FROM reviews r
    JOIN courses c ON c.id=r.course_id JOIN students st ON st.id=r.student_id JOIN users u ON u.id=st.user_id
    WHERE r.tenant_id=? ORDER BY r.created_at DESC LIMIT 60");
$s->execute([$tid]); $rows = $s->fetchAll();
$s = $pdo->prepare("SELECT COALESCE(AVG(rating),0) AS avg_r, COUNT(*) AS total FROM reviews WHERE tenant_id=?");
$s->execute([$tid]); $summary = $s->fetch();
require __DIR__ . '/../includes/header.php';
?>
<div class="alert alert-light border d-flex justify-content-between align-items-center">
  <span><i class="bi bi-star-fill text-warning me-1"></i>Academy average: <strong><?= number_format((float)$summary['avg_r'], 1) ?> / 5</strong> across <?= (int)$summary['total'] ?> reviews</span>
  <span class="text-muted small">Course ratings on the public site are recalculated from published reviews.</span>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Student</th><th>Course</th><th>Rating</th><th>Review</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
  <tr><td><span class="avatar-circle sm"><?= e(initials($r['student_name'])) ?></span> <?= e($r['student_name']) ?><br><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td>
    <td><small><?= e($r['course_title']) ?></small></td>
    <td><span class="text-warning"><?= stars((float)$r['rating']) ?></span><br><small><?= (int)$r['rating'] ?>/5</small></td>
    <td style="max-width:340px"><small><?= e($r['comment']) ?></small></td>
    <td><span class="badge bg-<?= $r['status'] === 'published' ? 'success' : 'secondary' ?>-subtle text-<?= $r['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
    <td class="text-end text-nowrap"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-sm btn-outline-secondary" name="action" value="toggle"><?= $r['status'] === 'published' ? 'Hide' : 'Publish' ?></button>
      <button class="btn btn-sm btn-outline-danger" name="action" value="delete" onclick="return confirm('Delete this review?')">Delete</button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No reviews yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
