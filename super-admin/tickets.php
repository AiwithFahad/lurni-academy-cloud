<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['super_admin']);
$pdo = db();
$pageTitle = 'Support Tickets'; $active = 'tickets';

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $action = post('action');
    if ($action === 'reply' && $id && post('message') !== '') {
        $pdo->prepare('INSERT INTO support_ticket_replies(ticket_id,user_id,message,created_at) VALUES(?,?,?,NOW())')->execute([$id, $me['id'], post('message')]);
        $pdo->prepare("UPDATE support_tickets SET status='in_progress' WHERE id=? AND status='open'")->execute([$id]);
        $t = $pdo->prepare('SELECT tenant_id, user_id, subject FROM support_tickets WHERE id=?');
        $t->execute([$id]); $ticket = $t->fetch();
        if ($ticket) notify_user($pdo, $ticket['tenant_id'] ? (int)$ticket['tenant_id'] : null, $ticket['user_id'] ? (int)$ticket['user_id'] : null, 'Support reply: ' . $ticket['subject'], post('message'), '');
        flash('success', 'Reply sent.');
    } elseif ($action === 'status' && $id && in_array(post('status'), ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $pdo->prepare('UPDATE support_tickets SET status=? WHERE id=?')->execute([post('status'), $id]);
        flash('success', 'Ticket status updated.');
    }
    redirect('/super-admin/tickets.php?view=' . $id);
}

$viewId = (int)($_GET['view'] ?? 0);
$rows = $pdo->query("SELECT tk.*, t.name AS tenant_name, u.name AS user_name FROM support_tickets tk
    LEFT JOIN tenants t ON t.id=tk.tenant_id LEFT JOIN users u ON u.id=tk.user_id ORDER BY tk.created_at DESC LIMIT 50")->fetchAll();
$view = null; $replies = [];
if ($viewId) {
    $s = $pdo->prepare("SELECT tk.*, t.name AS tenant_name, u.name AS user_name, u.email AS user_email FROM support_tickets tk
        LEFT JOIN tenants t ON t.id=tk.tenant_id LEFT JOIN users u ON u.id=tk.user_id WHERE tk.id=?");
    $s->execute([$viewId]); $view = $s->fetch() ?: null;
    if ($view) {
        $s = $pdo->prepare("SELECT r.*, u.name AS author, u.role FROM support_ticket_replies r LEFT JOIN users u ON u.id=r.user_id WHERE r.ticket_id=? ORDER BY r.created_at");
        $s->execute([$viewId]); $replies = $s->fetchAll();
    }
}
require __DIR__ . '/header.php';
?>
<div class="row g-3">
  <div class="col-xl-<?= $view ? '5' : '12' ?>"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Subject</th><th>From</th><th>Priority</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $t): ?>
    <tr class="<?= $viewId === (int)$t['id'] ? 'table-primary' : '' ?>"><td><a href="?view=<?= (int)$t['id'] ?>"><strong><?= e($t['subject']) ?></strong></a><br><small class="text-muted"><?= e(time_ago($t['created_at'])) ?></small></td>
      <td><?= e($t['user_name'] ?? '—') ?><br><small class="text-muted"><?= e($t['tenant_name'] ?? 'Platform') ?></small></td>
      <td><span class="badge bg-<?= match($t['priority']) { 'urgent', 'high' => 'danger', 'medium' => 'warning', default => 'secondary' } ?>-subtle text-<?= match($t['priority']) { 'urgent', 'high' => 'danger', 'medium' => 'warning', default => 'secondary' } ?>"><?= e($t['priority']) ?></span></td>
      <td><span class="badge bg-<?= match($t['status']) { 'open' => 'danger', 'in_progress' => 'info', 'resolved' => 'success', default => 'secondary' } ?>-subtle text-<?= match($t['status']) { 'open' => 'danger', 'in_progress' => 'info', 'resolved' => 'success', default => 'secondary' } ?>"><?= e(str_replace('_', ' ', $t['status'])) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">No tickets yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>

  <?php if ($view): ?>
  <div class="col-xl-7"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start">
      <div><h2 class="h6 fw-bold mb-1"><?= e($view['subject']) ?></h2>
      <small class="text-muted"><?= e($view['user_name'] ?? '') ?> (<?= e($view['user_email'] ?? '') ?>) · <?= e($view['tenant_name'] ?? 'Platform') ?> · <?= e(date('M j, Y H:i', strtotime($view['created_at']))) ?></small></div>
      <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
        <select class="form-select form-select-sm" name="status"><?php foreach (['open', 'in_progress', 'resolved', 'closed'] as $st): ?><option value="<?= $st ?>" <?= $view['status'] === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-outline-primary">Save</button></form>
    </div>
    <div class="ticket-msg mt-3"><p class="mb-0"><?= nl2br(e($view['message'])) ?></p></div>
    <?php foreach ($replies as $r): ?>
    <div class="ticket-msg <?= $r['role'] === 'super_admin' ? 'mine' : '' ?>"><strong><?= e($r['author'] ?? 'User') ?></strong> <small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small><p class="mb-0 mt-1"><?= nl2br(e($r['message'])) ?></p></div>
    <?php endforeach; ?>
    <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
      <textarea class="form-control mb-2" name="message" rows="3" required placeholder="Write a reply…"></textarea>
      <button class="btn btn-primary">Send reply</button></form>
  </div></div></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
