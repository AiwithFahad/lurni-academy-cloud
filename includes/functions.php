<?php
declare(strict_types=1);

/* ---------- Output / request helpers ---------- */
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function post(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function getp(string $key, string $default = ''): string { return trim((string)($_GET[$key] ?? $default)); }
function money($n): string { return '$' . number_format((float)$n, 2); }
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= strtoupper(substr($p, 0, 1));
    return $out ?: 'U';
}
function stars(float $rating): string {
    $full = (int)round($rating);
    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', 5 - max(0, min(5, $full)));
}
function time_ago(?string $dt): string {
    if (!$dt) return '';
    $s = time() - strtotime($dt);
    if ($s < 60) return 'just now';
    if ($s < 3600) return (int)($s / 60) . 'm ago';
    if ($s < 86400) return (int)($s / 3600) . 'h ago';
    if ($s < 2592000) return (int)($s / 86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}

/* ---------- CSRF / flash ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(419); exit('CSRF token mismatch.'); }
}
function flash(string $key, ?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return $msg; }
    $v = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $v;
}
function flash_html(): string {
    $out = '';
    foreach (['success' => 'success', 'error' => 'danger', 'info' => 'info'] as $k => $cls) {
        $m = flash($k);
        if ($m) $out .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">' . e($m)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return $out;
}

/* ---------- Session identity ---------- */
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function tenant_id(): ?int {
    $u = current_user();
    return isset($u['tenant_id']) && $u['tenant_id'] !== null ? (int)$u['tenant_id'] : null;
}

/* ---------- Logging / notifications ---------- */
function audit_log(PDO $pdo, string $action, string $entity = '', ?int $entityId = null): void {
    try {
        $u = current_user();
        $s = $pdo->prepare('INSERT INTO audit_logs(tenant_id,user_id,action,entity,entity_id,ip_address,created_at) VALUES(?,?,?,?,?,?,NOW())');
        $s->execute([$u['tenant_id'] ?? null, $u['id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $ignored) {}
}
function log_activity(PDO $pdo, string $text, ?int $tenantId = null): void {
    try {
        $s = $pdo->prepare('INSERT INTO activity_logs(tenant_id,user_id,description,created_at) VALUES(?,?,?,NOW())');
        $s->execute([$tenantId ?? tenant_id(), current_user()['id'] ?? null, $text]);
    } catch (Throwable $ignored) {}
}
function notify_user(PDO $pdo, ?int $tenantId, ?int $userId, string $title, string $body = '', string $link = ''): void {
    try {
        $s = $pdo->prepare('INSERT INTO notifications(tenant_id,user_id,title,body,link,created_at) VALUES(?,?,?,?,?,NOW())');
        $s->execute([$tenantId, $userId, $title, $body, $link]);
    } catch (Throwable $ignored) {}
}

/* ---------- Settings ---------- */
function tenant_setting(PDO $pdo, int $tenantId, string $key, string $default = ''): string {
    $s = $pdo->prepare('SELECT setting_value FROM tenant_settings WHERE tenant_id=? AND setting_key=?');
    $s->execute([$tenantId, $key]);
    $v = $s->fetchColumn();
    return $v === false ? $default : (string)$v;
}
function set_tenant_setting(PDO $pdo, int $tenantId, string $key, string $value): void {
    $s = $pdo->prepare('INSERT INTO tenant_settings(tenant_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $s->execute([$tenantId, $key, $value]);
}
function platform_setting(PDO $pdo, string $key, string $default = ''): string {
    $s = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');
    $s->execute([$key]);
    $v = $s->fetchColumn();
    return $v === false ? $default : (string)$v;
}
function set_platform_setting(PDO $pdo, string $key, string $value): void {
    $s = $pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $s->execute([$key, $value]);
}

/* ---------- Subscriptions & plan limits ---------- */
function refresh_subscription_status(PDO $pdo, int $tenantId): void {
    // Automatic expiry: past current_period_end + grace_days => expired.
    $s = $pdo->prepare("UPDATE subscriptions SET status='expired'
        WHERE tenant_id=? AND status IN ('trial','active','past_due')
          AND current_period_end IS NOT NULL
          AND DATE_ADD(current_period_end, INTERVAL grace_days DAY) < NOW()");
    $s->execute([$tenantId]);
}
function current_subscription(PDO $pdo, int $tenantId): ?array {
    refresh_subscription_status($pdo, $tenantId);
    $s = $pdo->prepare('SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.price_monthly, p.price_yearly,
                               p.max_students, p.max_instructors, p.max_courses, p.storage_mb, p.features
                        FROM subscriptions s JOIN subscription_plans p ON p.id=s.plan_id
                        WHERE s.tenant_id=? ORDER BY s.id DESC LIMIT 1');
    $s->execute([$tenantId]);
    $row = $s->fetch();
    return $row ?: null;
}
function usage_count(PDO $pdo, int $tenantId, string $metric): int {
    $map = [
        'students'    => "SELECT COUNT(*) FROM students WHERE tenant_id=?",
        'instructors' => "SELECT COUNT(*) FROM instructors WHERE tenant_id=?",
        'courses'     => "SELECT COUNT(*) FROM courses WHERE tenant_id=?",
        'users'       => "SELECT COUNT(*) FROM users WHERE tenant_id=?",
        'storage_mb'  => "SELECT COALESCE(ROUND(SUM(file_size)/1048576),0) FROM resources WHERE tenant_id=?",
    ];
    if (!isset($map[$metric])) return 0;
    $s = $pdo->prepare($map[$metric]);
    $s->execute([$tenantId]);
    return (int)$s->fetchColumn();
}
function sync_subscription_usage(PDO $pdo, int $tenantId): void {
    foreach (['students', 'instructors', 'courses', 'storage_mb'] as $m) {
        $s = $pdo->prepare('INSERT INTO subscription_usage(tenant_id,metric,used_value,recorded_at) VALUES(?,?,?,NOW())
                            ON DUPLICATE KEY UPDATE used_value=VALUES(used_value), recorded_at=NOW()');
        $s->execute([$tenantId, $m, usage_count($pdo, $tenantId, $m)]);
    }
}
/** Returns an error message when the tenant is at/over the plan limit for $metric, else null. -1 limit = unlimited. */
function limit_error(PDO $pdo, int $tenantId, string $metric): ?string {
    $sub = current_subscription($pdo, $tenantId);
    if (!$sub) return 'No active subscription. Please choose a plan to continue.';
    if (!in_array($sub['status'], ['trial', 'active', 'past_due'], true)) {
        return 'Your subscription has ' . $sub['status'] . '. Renew or upgrade your plan to add more.';
    }
    $col = 'max_' . $metric;
    $limit = isset($sub[$col]) ? (int)$sub[$col] : -1;
    if ($metric === 'storage_mb') $limit = (int)($sub['storage_mb'] ?? -1);
    if ($limit < 0) return null;
    if (usage_count($pdo, $tenantId, $metric) >= $limit) {
        return 'Plan limit reached: your ' . e($sub['plan_name']) . ' plan allows ' . $limit . ' ' . str_replace('_', ' ', $metric) . '. Upgrade to add more.';
    }
    return null;
}

/* ---------- Pagination / search ---------- */
function paginate(int $total, int $perPage = 10): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage];
}
function pagination_html(array $pg, string $base = '?'): string {
    if ($pg['pages'] <= 1) return '';
    $sep = (strpos($base, '?') === false) ? '?' : '&';
    $out = '<nav><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        $out .= '<li class="page-item ' . ($i === $pg['page'] ? 'active' : '') . '"><a class="page-link" href="'
            . e($base . $sep . 'page=' . $i) . '">' . $i . '</a></li>';
    }
    return $out . '</ul></nav>';
}

/* ---------- Files / export ---------- */
function handle_upload(array $file, string $subdir, array $allowedExt, int $maxMb = 25): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    if ($file['size'] > $maxMb * 1048576) throw new RuntimeException('File exceeds ' . $maxMb . 'MB limit.');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) throw new RuntimeException('File type not allowed: ' . $ext);
    $dir = UPLOAD_DIR . '/' . trim($subdir, '/');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not store upload.');
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}
function export_csv(array $rows, string $filename): void {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    foreach ($rows as $i => $r) { if ($i === 0) fputcsv($out, array_keys($r)); fputcsv($out, $r); }
    fclose($out); exit;
}

/* ---------- Invoicing ---------- */
function next_invoice_number(PDO $pdo): string {
    $s = $pdo->query("SELECT COUNT(*) FROM invoices");
    return 'INV-' . date('Y') . '-' . str_pad((string)((int)$s->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}
function create_invoice(PDO $pdo, int $tenantId, ?int $subscriptionId, ?int $paymentId, float $amount, string $description): int {
    $s = $pdo->prepare('INSERT INTO invoices(tenant_id,subscription_id,payment_id,invoice_number,description,amount,status,issued_at,due_at,created_at)
                        VALUES(?,?,?,?,?,?,?,?,?,NOW())');
    $s->execute([$tenantId, $subscriptionId, $paymentId, next_invoice_number($pdo), $description, $amount,
                 $paymentId ? 'paid' : 'unpaid', date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+7 days'))]);
    return (int)$pdo->lastInsertId();
}

/* ---------- Identity shortcuts (upgrade) ---------- */
/** The students.id for the signed-in student user, or 0 when not a student. */
function current_student_id(PDO $pdo): int {
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== 'student' || empty($u['tenant_id'])) return 0;
    static $cache = null;
    if ($cache !== null) return $cache;
    $s = $pdo->prepare('SELECT id FROM students WHERE user_id=? AND tenant_id=?');
    $s->execute([$u['id'], $u['tenant_id']]);
    return $cache = (int)$s->fetchColumn();
}
/** The instructors row for the signed-in instructor user, or null. */
function current_instructor(PDO $pdo): ?array {
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== 'instructor' || empty($u['tenant_id'])) return null;
    $s = $pdo->prepare('SELECT * FROM instructors WHERE user_id=? AND tenant_id=?');
    $s->execute([$u['id'], $u['tenant_id']]);
    $row = $s->fetch();
    return $row ?: null;
}

/* ---------- Email notification architecture (upgrade) ---------- */
/**
 * Queue an email for asynchronous delivery by the mail worker / cron sender.
 * Nothing is "sent" from the request path; rows land in email_queue with
 * status 'queued' and are flipped to 'sent'/'failed' by the sender.
 */
function queue_email(PDO $pdo, ?int $tenantId, string $toEmail, string $subject, string $body): void {
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return;
    try {
        $s = $pdo->prepare("INSERT INTO email_queue(tenant_id,to_email,subject,body,status,created_at) VALUES(?,?,?,?,'queued',NOW())");
        $s->execute([$tenantId, $toEmail, $subject, $body]);
    } catch (Throwable $ignored) {}
}
/** Queue an email to a platform user by id (looks up their address). */
function queue_email_to_user(PDO $pdo, int $userId, string $subject, string $body): void {
    try {
        $s = $pdo->prepare('SELECT email, tenant_id FROM users WHERE id=?');
        $s->execute([$userId]);
        $row = $s->fetch();
        if ($row) queue_email($pdo, $row['tenant_id'] !== null ? (int)$row['tenant_id'] : null, (string)$row['email'], $subject, $body);
    } catch (Throwable $ignored) {}
}

/* ---------- Coupons (upgrade: shared by subscription + course checkout) ---------- */
/**
 * Validate a coupon against an amount. Returns
 * ['coupon' => row, 'discount' => float] or null when invalid/exhausted/expired.
 */
function apply_coupon(PDO $pdo, string $code, float $amount): ?array {
    $code = strtoupper(trim($code));
    if ($code === '' || $amount <= 0) return null;
    $s = $pdo->prepare('SELECT * FROM coupons WHERE code=? AND is_active=1');
    $s->execute([$code]);
    $c = $s->fetch();
    if (!$c) return null;
    if (!empty($c['expires_at']) && strtotime((string)$c['expires_at']) < time()) return null;
    if ((int)$c['max_uses'] > 0 && (int)$c['times_used'] >= (int)$c['max_uses']) return null;
    if ($amount < (float)$c['min_amount']) return null;
    $discount = $c['discount_type'] === 'percent'
        ? round($amount * (float)$c['discount_value'] / 100, 2)
        : min((float)$c['discount_value'], $amount);
    return ['coupon' => $c, 'discount' => $discount];
}
function record_coupon_redemption(PDO $pdo, int $couponId, ?int $userId, ?int $paymentId, float $discount): void {
    try {
        // coupon_redemptions has (coupon_id, tenant_id, payment_id) — no
        // user_id/discount_amount columns; tenant comes from the payment row.
        $tenantId = null;
        if ($paymentId) {
            $s = $pdo->prepare('SELECT tenant_id FROM payments WHERE id=?');
            $s->execute([$paymentId]);
            $tenantId = $s->fetchColumn() ?: null;
        }
        if ($tenantId) {
            $pdo->prepare('INSERT INTO coupon_redemptions(coupon_id,tenant_id,payment_id,created_at) VALUES(?,?,?,NOW())')
                ->execute([$couponId, (int)$tenantId, $paymentId]);
        }
        $pdo->prepare('UPDATE coupons SET times_used=times_used+1 WHERE id=?')->execute([$couponId]);
    } catch (Throwable $ignored) {}
}
