<?php
declare(strict_types=1);
/*
 * Shared inbox/compose UI for instructor & student portals.
 * Expects: $pdo, $me (session user), $tid (tenant id), $selfUrl (form target).
 * Handles its own POST (action=send_message / mark_read) then redirects.
 */
function handle_messenger_post(PDO $pdo, array $me, int $tid, string $selfUrl): void {
    if (!is_post()) return;
    verify_csrf();
    if (post('action') === 'send_message') {
        $receiver = (int)($_POST['receiver_id'] ?? 0);
        // Tenant-aware authorization: receiver must belong to the same academy.
        $s = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id=? AND tenant_id=?');
        $s->execute([$receiver, $tid]);
        if ((int)$s->fetchColumn() > 0 && post('body') !== '') {
            $pdo->prepare('INSERT INTO messages(tenant_id,sender_id,receiver_id,subject,body,created_at) VALUES(?,?,?,?,?,NOW())')
                ->execute([$tid, $me['id'], $receiver, post('subject'), post('body')]);
            flash('success', 'Message sent.');
        } else {
            flash('error', 'Could not send — recipient not found in your academy.');
        }
    } elseif (post('action') === 'mark_read') {
        $pdo->prepare('UPDATE messages SET read_at=NOW() WHERE receiver_id=? AND read_at IS NULL')->execute([$me['id']]);
        flash('success', 'All messages marked as read.');
    }
    redirect($selfUrl);
}

function render_messenger(PDO $pdo, array $me, int $tid): void {
    $inbox = $pdo->prepare("SELECT m.*, u.name AS sender_name, u.role AS sender_role FROM messages m JOIN users u ON u.id=m.sender_id
        WHERE m.receiver_id=? AND m.tenant_id=? ORDER BY m.created_at DESC LIMIT 40");
    $inbox->execute([$me['id'], $tid]); $inbox = $inbox->fetchAll();
    $sent = $pdo->prepare("SELECT m.*, u.name AS receiver_name FROM messages m JOIN users u ON u.id=m.receiver_id
        WHERE m.sender_id=? AND m.tenant_id=? ORDER BY m.created_at DESC LIMIT 20");
    $sent->execute([$me['id'], $tid]); $sent = $sent->fetchAll();
    $people = $pdo->prepare("SELECT id, name, role FROM users WHERE tenant_id=? AND id<>? AND status IN ('active','pending') ORDER BY role, name");
    $people->execute([$tid, $me['id']]); $people = $people->fetchAll();
    ?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><span class="fw-bold">Inbox</span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_read"><button class="btn btn-sm btn-outline-secondary">Mark all read</button></form></div>
      <ul class="list-group list-group-flush">
      <?php foreach ($inbox as $m): ?>
        <li class="list-group-item <?= $m['read_at'] ? '' : 'bg-primary-subtle bg-opacity-25' ?>">
          <div class="d-flex justify-content-between"><strong><?= e($m['sender_name']) ?></strong><small class="text-muted"><?= e(time_ago($m['created_at'])) ?></small></div>
          <?php if ($m['subject']): ?><div class="fw-semibold small"><?= e($m['subject']) ?></div><?php endif; ?>
          <p class="text-muted small mb-0"><?= nl2br(e($m['body'])) ?></p>
          <span class="badge bg-secondary-subtle text-secondary text-capitalize"><?= e(str_replace('_', ' ', $m['sender_role'])) ?></span>
        </li>
      <?php endforeach; ?>
      <?php if (!$inbox): ?><li class="list-group-item text-muted">No messages yet.</li><?php endif; ?>
      </ul></div>
    <div class="card"><div class="card-header bg-white fw-bold">Sent</div>
      <ul class="list-group list-group-flush">
      <?php foreach ($sent as $m): ?>
        <li class="list-group-item"><div class="d-flex justify-content-between"><span>To <strong><?= e($m['receiver_name']) ?></strong></span><small class="text-muted"><?= e(time_ago($m['created_at'])) ?></small></div>
        <?php if ($m['subject']): ?><div class="fw-semibold small"><?= e($m['subject']) ?></div><?php endif; ?>
        <p class="text-muted small mb-0"><?= nl2br(e($m['body'])) ?></p></li>
      <?php endforeach; ?>
      <?php if (!$sent): ?><li class="list-group-item text-muted">Nothing sent yet.</li><?php endif; ?>
      </ul></div>
  </div>
  <div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">New message</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_message">
      <div class="mb-2"><label class="form-label">Recipient</label><select class="form-select" name="receiver_id" required>
        <?php foreach ($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e(str_replace('_', ' ', $p['role'])) ?>)</option><?php endforeach; ?>
      </select></div>
      <div class="mb-2"><label class="form-label">Subject</label><input class="form-control" name="subject"></div>
      <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="4" required></textarea></div>
      <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Send message</button>
    </form></div></div></div>
</div>
    <?php
}
