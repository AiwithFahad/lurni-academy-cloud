<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Payment History'; $active = 'payments';

// The signed-in student's own payments (course purchases + any others),
// with invoice numbers and refund records. Statuses are real: a payment
// shows 'completed' only after an admin verified it.
$s = $pdo->prepare("SELECT p.*, c.title AS course_title, i.invoice_number,
        (SELECT COALESCE(SUM(r.amount),0) FROM payment_refunds r WHERE r.payment_id=p.id) AS refunded_total
    FROM payments p
    LEFT JOIN courses c ON c.id=p.course_id
    LEFT JOIN invoices i ON i.id=p.invoice_id
    WHERE p.tenant_id=? AND p.user_id=? ORDER BY p.created_at DESC");
$s->execute([$tid, $me['id']]); $payments = $s->fetchAll();

$s = $pdo->prepare("SELECT en.status, c.title, c.id AS course_id FROM enrollments en JOIN courses c ON c.id=en.course_id
    WHERE en.student_id=? AND en.tenant_id=? AND en.status='pending'");
$s->execute([current_student_id($pdo), $tid]); $pendingEnrollments = $s->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-3"><h2 class="h4 fw-bold">Payment history</h2>
<p class="text-muted mb-0">Your course purchases and invoices. Courses unlock as soon as a payment is verified by your academy.</p></div>

<?php if ($pendingEnrollments): ?>
<div class="alert alert-warning"><i class="bi bi-hourglass-split me-1"></i>Awaiting payment verification for:
<?= implode(', ', array_map(fn($r) => '<strong>' . e($r['title']) . '</strong>', $pendingEnrollments)) ?>.</div>
<?php endif; ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Date</th><th>Item</th><th>Amount</th><th>Discount</th><th>Invoice</th><th>Reference</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($payments as $p): $net = (float)$p['amount'] - (float)$p['discount_amount']; ?>
  <tr>
    <td><small><?= e(date('M j, Y H:i', strtotime((string)$p['created_at']))) ?></small></td>
    <td><?= $p['kind'] === 'course' ? e($p['course_title'] ?? 'Course') : 'Subscription' ?><?php if ($p['coupon_code']): ?><br><small class="text-muted">Coupon <?= e($p['coupon_code']) ?></small><?php endif; ?></td>
    <td><?= money($net) ?><?php if ((float)$p['refunded_total'] > 0): ?><br><small class="text-danger">Refunded <?= money((float)$p['refunded_total']) ?></small><?php endif; ?></td>
    <td><?= (float)$p['discount_amount'] > 0 ? '−' . money((float)$p['discount_amount']) : '—' ?></td>
    <td><?= $p['invoice_number'] ? e($p['invoice_number']) : '—' ?></td>
    <td><small><?= e($p['reference'] ?? '—') ?></small></td>
    <td><span class="badge bg-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning text-dark' : ($p['status'] === 'refunded' ? 'info' : 'danger')) ?>-subtle text-<?= $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning-emphasis' : ($p['status'] === 'refunded' ? 'info' : 'danger')) ?>"><?= e($p['status']) ?></span>
    <?php if ($p['status'] === 'pending'): ?><br><small class="text-muted">Awaiting verification</small><?php elseif ($p['status'] === 'completed' && !empty($p['verified_at'])): ?><br><small class="text-muted">Verified <?= e(date('M j', strtotime((string)$p['verified_at']))) ?></small><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-muted py-4">No payments yet. Paid courses you buy will appear here.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
