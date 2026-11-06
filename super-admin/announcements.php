<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Announcements'; $active = 'announcements';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'send' && post('title') !== '') {
        // Broadcast: tenant_id NULL + user_id NULL means every academy sees it.
        $pdo->prepare('INSERT INTO notifications(tenant_id,user_id,title,body,link,created_at) VALUES(NULL,NULL,?,?,?,NOW())')
            ->execute([post('title'), post('body'), post('link')]);
        log_activity($pdo, 'Announcement sent: ' . post('title'));
        flash('success', 'Announcement broadcast to all academies.');
    } elseif (post('action') === 'delete') {
        $pdo->prepare('DELETE FROM notifications WHERE id=? AND tenant_id IS NULL AND user_id IS NULL')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Announcement removed.');
    }
    redirect('/super-admin/announcements.php');
}
$rows = $pdo->query('SELECT * FROM notifications WHERE tenant_id IS NULL AND user_id IS NULL ORDER BY created_at DESC LIMIT 30')->fetchAll();
require __DIR__ . '/header.php';
?>
<div class="row g-3">
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">New announcement</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="Platform update"></div>
      <div class="mb-2"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="4" placeholder="What should every academy know?"></textarea></div>
      <div class="mb-3"><label class="form-label">Link (optional)</label><input class="form-control" name="link" placeholder="/admin/dashboard.php"></div>
      <button class="btn btn-primary w-100"><i class="bi bi-megaphone me-1"></i>Broadcast to all academies</button>
    </form></div></div></div>
  <div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Sent announcements</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($rows as $n): ?>
      <li class="list-group-item d-flex justify-content-between gap-3">
        <div><strong><?= e($n['title']) ?></strong><p class="text-muted small mb-0"><?= e($n['body'] ?? '') ?></p><small class="text-muted"><?= e(date('M j, Y H:i', strtotime($n['created_at']))) ?></small></div>
        <form method="post" onsubmit="return confirm('Remove this announcement?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
      </li>
    <?php endforeach; ?>
    <?php if (!$rows): ?><li class="list-group-item text-muted">No announcements yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
