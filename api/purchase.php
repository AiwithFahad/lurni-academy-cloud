<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// POST /api/purchase.php — start a paid-course purchase for the signed-in
// student. Creates a PENDING payment + unpaid invoice + a 'pending'
// enrollment. Nothing is marked paid here: the enrollment activates only
// when a tenant admin verifies the payment in /admin/payments.php
// (verified confirmation rule — no fake success).
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
verify_csrf();

$courseId = (int)($_POST['course_id'] ?? 0);
$sid = current_student_id($pdo);
$s = $pdo->prepare("SELECT id, title, price FROM courses WHERE id=? AND tenant_id=? AND status='published' AND approval_status='approved'");
$s->execute([$courseId, $tid]); $course = $s->fetch() ?: null;
if (!$sid || !$course) { flash('error', 'Course not available for purchase.'); redirect('/student/browse.php'); }
$price = (float)$course['price'];
if ($price <= 0) { flash('info', 'This course is free — use the enroll button.'); redirect('/student/browse.php'); }

// Existing enrollment?
$s = $pdo->prepare('SELECT id, status FROM enrollments WHERE student_id=? AND course_id=? AND tenant_id=?');
$s->execute([$sid, $courseId, $tid]); $enr = $s->fetch() ?: null;
if ($enr && in_array($enr['status'], ['active', 'completed'], true)) {
    flash('success', 'You already own this course.');
    redirect('/student/course.php?id=' . $courseId);
}
// Existing pending payment for this course?
$s = $pdo->prepare("SELECT id FROM payments WHERE tenant_id=? AND user_id=? AND course_id=? AND kind='course' AND status='pending'");
$s->execute([$tid, (int)$me['id'], $courseId]);
if ($s->fetch()) {
    flash('info', 'Your payment for this course is already recorded and awaiting verification.');
    redirect('/student/payments.php');
}

$couponCode = post('coupon_code');
$applied = $couponCode !== '' ? apply_coupon($pdo, $couponCode, $price) : null;
if ($couponCode !== '' && !$applied) { flash('error', 'That coupon code is not valid for this course.'); redirect('/student/browse.php'); }
$discount = $applied ? (float)$applied['discount'] : 0.0;
$payable = round($price - $discount, 2);

$pdo->beginTransaction();
try {
    if (!$enr) {
        $pdo->prepare("INSERT INTO enrollments(tenant_id,student_id,course_id,status,source) VALUES(?,?,?,'pending','purchase')")
            ->execute([$tid, $sid, $courseId]);
    } else {
        $pdo->prepare("UPDATE enrollments SET status='pending' WHERE id=?")->execute([(int)$enr['id']]);
    }
    $reference = 'COURSE-' . $courseId . '-' . strtoupper(bin2hex(random_bytes(3)));
    $pdo->prepare("INSERT INTO payments(tenant_id,user_id,kind,course_id,amount,discount_amount,coupon_code,method,reference,status,created_at)
                   VALUES(?,?, 'course', ?, ?, ?, ?, 'manual', ?, 'pending', NOW())")
        ->execute([$tid, (int)$me['id'], $courseId, $price, $discount, $applied ? strtoupper($couponCode) : null, $reference]);
    $paymentId = (int)$pdo->lastInsertId();
    if ($applied) record_coupon_redemption($pdo, (int)$applied['coupon']['id'], (int)$me['id'], $paymentId, $discount);
    $invoiceId = create_invoice($pdo, $tid, null, $paymentId, $payable, 'Course purchase: ' . $course['title']);
    // The payment is only pending, so the invoice must stay unpaid until verification.
    $pdo->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL WHERE id=?")->execute([$invoiceId]);
    $pdo->prepare('UPDATE payments SET invoice_id=? WHERE id=?')->execute([$invoiceId, $paymentId]);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    flash('error', 'Could not record the purchase. Please try again.');
    redirect('/student/browse.php');
}
notify_user($pdo, $tid, (int)$me['id'], 'Purchase recorded', 'Your payment of ' . money($payable) . ' for "' . $course['title'] . '" is awaiting verification. The course unlocks as soon as it is confirmed.', '/student/payments.php');
queue_email($pdo, $tid, (string)$me['email'], 'Purchase recorded — ' . $course['title'],
    "Hi {$me['name']},\n\nYour payment of " . money($payable) . " for the course \"{$course['title']}\" has been recorded with reference {$reference} and is awaiting verification. The course will unlock automatically once the payment is confirmed.\n\n— " . APP_NAME);
log_activity($pdo, 'Course purchase recorded (pending verification): ' . $course['title']);
flash('success', 'Purchase recorded. Your course unlocks as soon as the payment is verified — see Payment history for status.');
redirect('/student/payments.php');
