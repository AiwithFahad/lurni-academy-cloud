<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Notifications & Support'; $active = 'notifications';

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE read_at IS NULL AND (user_id=? OR (user_id IS NULL AND tenant_id=?))')->execute([$me['id'], $tid]);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'announce') {
        // Academy announcement to its own people (optionally one role).
        $role = post('audience');
        $targets = [];
        if (in_array($role, ['admin', 'instructor', 'student'], true)) {
            $s = $pdo->prepare('SELECT id FROM users WHERE tenant_id=? AND role=? AND status IN ("active","pending")');
            $s->execute([$tid, $role]); $targets = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $s = $pdo->prepare('SELECT id FROM users WHERE tenant_id=? AND status IN ("active","pending")');
            $s->execute([$tid]); $targets = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        }
        foreach ($targets as $uid) notify_user($pdo, $tid, $uid, post('title'), post('body'), '');
        flash('success', 'Announcement sent to ' . count($targets) . ' academy member(s).');
    } elseif ($action === 'ticket') {
        $pdo->prepare('INSERT INTO support_tickets(tenant_id,user_id,subject,message,priority,status) VALUES(?,?,?,?,?,"open")')
            ->execute([$tid, $me['id'], post('subject'), post('message'), in_array(post('priority'), ['low', 'medium', 'high', 'urgent'], true) ? post('priority') : 'medium']);
        notify_user($pdo, null, null, 'New support ticket', post('subject'), '/super-admin/tickets.php');
        flash('success', 'Support ticket opened. The platform team will reply here and in Tickets.');
    } elseif ($action === 'ticket_reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $own = $pdo->prepare('SELECT COUNT(*) FROM support_tickets WHERE id=? AND tenant_id=?');
        $own->execute([$ticketId, $tid]);
        if ((int)$own->fetchColumn() > 0 && post('message') !== '') {
            $pdo->prepare('INSERT INTO support_ticket_replies(ticket_id,user_id,message,created_at) VALUES(?,?,?,NOW())')->execute([$ticketId, $me['id'], post('message')]);
            $pdo->prepare("UPDATE support_tickets SET status='open' WHERE id=?")->execute([$ticketId]);
            flash('success', 'Reply sent to support.');
        }
    }
    redirect('/admin/notifications.php');
}

$s = $pdo->prepare("SELECT * FROM notifications WHERE (user_id=? OR (user_id IS NULL AND (tenant_id=? OR tenant_id IS NULL))) ORDER BY created_at DESC LIMIT 40");
$s->execute([$me['id'], $tid]); $notes = $s->fetchAll();
$s = $pdo->prepare('SELECT * FROM support_tickets WHERE tenant_id=? ORDER BY created_at DESC LIMIT 10');
$s->execute([$tid]); $tickets = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Notifications</span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-sm btn-outline-secondary">Mark all read</button></form></div>
      <ul class="list-group list-group-flush">
      <?php foreach ($notes as $n): ?>
        <li class="list-group-item <?= $n['read_at'] ? '' : 'bg-primary-subtle bg-opacity-25' ?>">
          <div class="d-flex justify-content-between"><strong><?= e($n['title']) ?></strong><small class="text-muted"><?= e(time_ago($n['created_at'])) ?></small></div>
          <p class="text-muted small mb-0"><?= e($n['body'] ?? '') ?></p>
          <?php if ($n['tenant_id'] === null && $n['user_id'] === null): ?><span class="badge bg-primary-subtle text-primary">Platform announcement</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if (!$notes): ?><li class="list-group-item text-muted">No notifications.</li><?php endif; ?>
      </ul></div>
    <div class="card"><div class="card-header bg-white fw-bold">My support tickets</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($tickets as $t): ?>
        <li class="list-group-item"><div class="d-flex justify-content-between"><strong><?= e($t['subject']) ?></strong>
          <span class="badge bg-<?= match($t['status']) { 'open' => 'danger', 'in_progress' => 'info', 'resolved' => 'success', default => 'secondary' } ?>-subtle text-<?= match($t['status']) { 'open' => 'danger', 'in_progress' => 'info', 'resolved' => 'success', default => 'secondary' } ?>"><?= e(str_replace('_', ' ', $t['status'])) ?></span></div>
          <p class="text-muted small mb-1"><?= e($t['message']) ?></p>
          <form method="post" class="d-flex gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="ticket_reply"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
            <input class="form-control form-control-sm" name="message" placeholder="Add a reply…" required><button class="btn btn-sm btn-outline-primary">Reply</button></form></li>
      <?php endforeach; ?>
      <?php if (!$tickets): ?><li class="list-group-item text-muted">No tickets yet.</li><?php endif; ?>
      </ul></div>
  </div>
  <div class="col-xl-5">
    <div class="card mb-3"><div class="card-header bg-white fw-bold">Announce to my academy</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="announce">
        <div class="mb-2"><label class="form-label">Audience</label><select class="form-select" name="audience">
          <option value="all">Everyone in my academy</option><option value="student">Students only</option><option value="instructor">Instructors only</option><option value="admin">Admins only</option></select></div>
        <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
        <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="3"></textarea></div>
        <button class="btn btn-primary w-100"><i class="bi bi-megaphone me-1"></i>Send announcement</button>
      </form></div></div>
    <div class="card"><div class="card-header bg-white fw-bold">Contact platform support</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ticket">
        <div class="mb-2"><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
        <div class="mb-2"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
        <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="3" required></textarea></div>
        <button class="btn btn-outline-primary w-100"><i class="bi bi-life-preserver me-1"></i>Open support ticket</button>
      </form></div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
