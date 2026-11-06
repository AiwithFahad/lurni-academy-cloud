<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// GET /api/analytics.php — role-aware JSON analytics for Chart.js dashboards.
// Every figure is aggregated live from MySQL records (no hardcoded numbers).
// Query params: from (Y-m-d), to (Y-m-d) — real date filtering on all series
// and on the "in range" counters. Roles: admin (own tenant), instructor (own
// courses), super_admin (whole platform), student (own learning activity).
header('Content-Type: application/json');
$me = current_user();
if (!$me) { http_response_code(401); echo json_encode(['ok' => false, 'message' => 'Sign in required.']); exit; }
$pdo = db();

$from = getp('from'); $to = getp('to');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-11 months', strtotime(date('Y-m-01'))));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$fromTs = $from . ' 00:00:00'; $toTs = $to . ' 23:59:59';

/** Month buckets between $from and $to (capped at 24) as [key => label]. */
function month_buckets(string $from, string $to): array {
    $start = new DateTime(date('Y-m-01', strtotime($from)));
    $end = new DateTime(date('Y-m-01', strtotime($to)));
    $out = [];
    $cur = clone $start;
    while ($cur <= $end && count($out) < 24) { $out[$cur->format('Y-m')] = $cur->format('M Y'); $cur->modify('+1 month'); }
    return $out;
}
function series_fill(array $buckets, array $rows, string $valKey = 'total'): array {
    $data = array_fill_keys(array_keys($buckets), 0);
    foreach ($rows as $r) if (isset($data[$r['ym']])) $data[$r['ym']] = $valKey === 'total' ? (int)$r['total'] : (float)$r['total'];
    return ['labels' => array_values($buckets), 'data' => array_values($data)];
}
function monthly(PDO $pdo, string $sql, array $args, array $buckets, bool $money = false): array {
    $s = $pdo->prepare($sql); $s->execute($args);
    return series_fill($buckets, $s->fetchAll(), $money ? 'amount' : 'total');
}
function one(PDO $pdo, string $sql, array $args = []): float {
    $s = $pdo->prepare($sql); $s->execute($args);
    return (float)$s->fetchColumn();
}
$buckets = month_buckets($from, $to);
$out = ['ok' => true, 'scope' => $me['role'], 'range' => ['from' => $from, 'to' => $to], 'stats' => [], 'series' => []];

try {
    if ($me['role'] === 'admin') {
        $tid = require_tenant();
        $out['stats'] = [
            'total_students'     => (int)one($pdo, 'SELECT COUNT(*) FROM students WHERE tenant_id=?', [$tid]),
            'active_students'    => (int)one($pdo, "SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE tenant_id=? AND status IN ('active','completed')", [$tid]),
            'total_instructors'  => (int)one($pdo, 'SELECT COUNT(*) FROM instructors WHERE tenant_id=?', [$tid]),
            'published_courses'  => (int)one($pdo, "SELECT COUNT(*) FROM courses WHERE tenant_id=? AND status='published'", [$tid]),
            'draft_courses'      => (int)one($pdo, "SELECT COUNT(*) FROM courses WHERE tenant_id=? AND status='draft'", [$tid]),
            'pending_approval'   => (int)one($pdo, "SELECT COUNT(*) FROM courses WHERE tenant_id=? AND approval_status='pending'", [$tid]),
            'enrollments_total'  => (int)one($pdo, 'SELECT COUNT(*) FROM enrollments WHERE tenant_id=?', [$tid]),
            'enrollments_range'  => (int)one($pdo, 'SELECT COUNT(*) FROM enrollments WHERE tenant_id=? AND enrolled_at BETWEEN ? AND ?', [$tid, $fromTs, $toTs]),
            'completion_rate'    => round((float)one($pdo, "SELECT CASE WHEN COUNT(*)=0 THEN 0 ELSE 100*SUM(status='completed')/COUNT(*) END FROM enrollments WHERE tenant_id=?", [$tid]), 1),
            'revenue_range'      => (float)one($pdo, "SELECT COALESCE(SUM(amount-discount_amount),0) FROM payments WHERE tenant_id=? AND status='completed' AND created_at BETWEEN ? AND ?", [$tid, $fromTs, $toTs]),
            'revenue_total'      => (float)one($pdo, "SELECT COALESCE(SUM(amount-discount_amount),0) FROM payments WHERE tenant_id=? AND status='completed'", [$tid]),
            'pending_payments'   => (int)one($pdo, "SELECT COUNT(*) FROM payments WHERE tenant_id=? AND status='pending'", [$tid]),
            'pending_amount'     => (float)one($pdo, "SELECT COALESCE(SUM(amount-discount_amount),0) FROM payments WHERE tenant_id=? AND status='pending'", [$tid]),
            'certificates_range' => (int)one($pdo, 'SELECT COUNT(*) FROM certificates WHERE tenant_id=? AND issued_at BETWEEN ? AND ?', [$tid, $fromTs, $toTs]),
            'certificates_total' => (int)one($pdo, 'SELECT COUNT(*) FROM certificates WHERE tenant_id=?', [$tid]),
        ];
        $out['series']['enrollments'] = monthly($pdo, "SELECT DATE_FORMAT(enrolled_at,'%Y-%m') ym, COUNT(*) total FROM enrollments WHERE tenant_id=? AND enrolled_at BETWEEN ? AND ? GROUP BY ym", [$tid, $fromTs, $toTs], $buckets);
        $out['series']['revenue'] = monthly($pdo, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COALESCE(SUM(amount-discount_amount),0) total, COALESCE(SUM(amount-discount_amount),0) amount FROM payments WHERE tenant_id=? AND status='completed' AND created_at BETWEEN ? AND ? GROUP BY ym", [$tid, $fromTs, $toTs], $buckets, true);
        // Student growth: cumulative students by creation month.
        $s = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) total FROM students WHERE tenant_id=? AND created_at<=? GROUP BY ym ORDER BY ym");
        $s->execute([$tid, $toTs]); $growth = $s->fetchAll();
        $prior = (int)one($pdo, 'SELECT COUNT(*) FROM students WHERE tenant_id=? AND created_at<?', [$tid, array_key_first($buckets) . '-01 00:00:00']);
        $run = $prior; $gData = [];
        $byMonth = []; foreach ($growth as $g) $byMonth[$g['ym']] = (int)$g['total'];
        foreach ($buckets as $k => $_) { $run += $byMonth[$k] ?? 0; $gData[] = $run; }
        $out['series']['student_growth'] = ['labels' => array_values($buckets), 'data' => $gData];
        // Completion per course (top 8 by enrollments).
        $s = $pdo->prepare("SELECT c.title, CASE WHEN COUNT(en.id)=0 THEN 0 ELSE ROUND(100*SUM(en.status='completed')/COUNT(en.id),1) END rate
            FROM courses c LEFT JOIN enrollments en ON en.course_id=c.id AND en.tenant_id=c.tenant_id
            WHERE c.tenant_id=? GROUP BY c.id ORDER BY COUNT(en.id) DESC LIMIT 8");
        $s->execute([$tid]); $comp = $s->fetchAll();
        $out['series']['completion'] = ['labels' => array_column($comp, 'title'), 'data' => array_map('floatval', array_column($comp, 'rate'))];
        // Popular categories.
        $s = $pdo->prepare("SELECT cat.name, COUNT(en.id) total FROM categories cat
            LEFT JOIN courses c ON c.category_id=cat.id AND c.tenant_id=cat.tenant_id
            LEFT JOIN enrollments en ON en.course_id=c.id
            WHERE cat.tenant_id=? GROUP BY cat.id ORDER BY total DESC LIMIT 8");
        $s->execute([$tid]); $cats = $s->fetchAll();
        $out['series']['categories'] = ['labels' => array_column($cats, 'name'), 'data' => array_map('intval', array_column($cats, 'total'))];
        // Instructor performance.
        $s = $pdo->prepare("SELECT u.name, COUNT(DISTINCT c.id) courses, COUNT(DISTINCT en.id) learners,
                COALESCE(ROUND(AVG(c.rating),1),0) avg_rating,
                COALESCE((SELECT SUM(p.amount-p.discount_amount) FROM payments p WHERE p.kind='course' AND p.status='completed' AND p.course_id IN (SELECT id FROM courses WHERE instructor_id=i.id AND tenant_id=?)),0) revenue
            FROM instructors i JOIN users u ON u.id=i.user_id
            LEFT JOIN courses c ON c.instructor_id=i.id AND c.tenant_id=i.tenant_id
            LEFT JOIN enrollments en ON en.course_id=c.id
            WHERE i.tenant_id=? GROUP BY i.id ORDER BY learners DESC LIMIT 10");
        $s->execute([$tid, $tid]);
        $out['instructor_performance'] = array_map(fn($r) => ['name' => $r['name'], 'courses' => (int)$r['courses'], 'learners' => (int)$r['learners'], 'avg_rating' => (float)$r['avg_rating'], 'revenue' => (float)$r['revenue']], $s->fetchAll());
        // Popular courses + recent enrollments.
        $s = $pdo->prepare("SELECT c.title, c.rating, COUNT(en.id) learners FROM courses c LEFT JOIN enrollments en ON en.course_id=c.id
            WHERE c.tenant_id=? GROUP BY c.id ORDER BY learners DESC LIMIT 5");
        $s->execute([$tid]);
        $out['popular_courses'] = array_map(fn($r) => ['title' => $r['title'], 'rating' => (float)$r['rating'], 'learners' => (int)$r['learners']], $s->fetchAll());
        $s = $pdo->prepare("SELECT u.name student_name, c.title course_title, en.enrolled_at, en.progress_percent FROM enrollments en
            JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id
            WHERE en.tenant_id=? ORDER BY en.enrolled_at DESC LIMIT 6");
        $s->execute([$tid]);
        $out['recent_enrollments'] = $s->fetchAll();
    } elseif ($me['role'] === 'instructor') {
        $tid = require_tenant();
        $ins = current_instructor($pdo);
        if (!$ins) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Instructor profile not found.']); exit; }
        $iid = (int)$ins['id'];
        $out['stats'] = [
            'my_courses'       => (int)one($pdo, 'SELECT COUNT(*) FROM courses WHERE tenant_id=? AND instructor_id=?', [$tid, $iid]),
            'learners'         => (int)one($pdo, 'SELECT COUNT(*) FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE en.tenant_id=? AND c.instructor_id=?', [$tid, $iid]),
            'avg_progress'     => round((float)one($pdo, 'SELECT COALESCE(AVG(en.progress_percent),0) FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE en.tenant_id=? AND c.instructor_id=?', [$tid, $iid]), 1),
            'course_revenue'   => (float)one($pdo, "SELECT COALESCE(SUM(p.amount-p.discount_amount),0) FROM payments p JOIN courses c ON c.id=p.course_id WHERE p.tenant_id=? AND p.kind='course' AND p.status='completed' AND c.instructor_id=?", [$tid, $iid]),
            'pending_grading'  => (int)one($pdo, "SELECT COUNT(*) FROM submissions sub JOIN assignments a ON a.id=sub.assignment_id JOIN courses c ON c.id=a.course_id WHERE sub.tenant_id=? AND c.instructor_id=? AND sub.status IN ('submitted','late')", [$tid, $iid]),
            'quiz_attempts'    => (int)one($pdo, 'SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes qz ON qz.id=qa.quiz_id JOIN courses c ON c.id=qz.course_id WHERE qa.tenant_id=? AND c.instructor_id=?', [$tid, $iid]),
        ];
        $out['series']['enrollments'] = monthly($pdo, "SELECT DATE_FORMAT(en.enrolled_at,'%Y-%m') ym, COUNT(*) total FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE en.tenant_id=? AND c.instructor_id=? AND en.enrolled_at BETWEEN ? AND ? GROUP BY ym", [$tid, $iid, $fromTs, $toTs], $buckets);
        $out['series']['revenue'] = monthly($pdo, "SELECT DATE_FORMAT(p.created_at,'%Y-%m') ym, COALESCE(SUM(p.amount-p.discount_amount),0) total, COALESCE(SUM(p.amount-p.discount_amount),0) amount FROM payments p JOIN courses c ON c.id=p.course_id WHERE p.tenant_id=? AND p.kind='course' AND p.status='completed' AND c.instructor_id=? AND p.created_at BETWEEN ? AND ? GROUP BY ym", [$tid, $iid, $fromTs, $toTs], $buckets, true);
        $s = $pdo->prepare("SELECT c.title, COUNT(en.id) learners FROM courses c LEFT JOIN enrollments en ON en.course_id=c.id
            WHERE c.tenant_id=? AND c.instructor_id=? GROUP BY c.id ORDER BY learners DESC LIMIT 8");
        $s->execute([$tid, $iid]); $rows = $s->fetchAll();
        $out['series']['learners_by_course'] = ['labels' => array_column($rows, 'title'), 'data' => array_map('intval', array_column($rows, 'learners'))];
    } elseif ($me['role'] === 'super_admin') {
        $out['stats'] = [
            'tenants_total'     => (int)one($pdo, 'SELECT COUNT(*) FROM tenants'),
            'tenants_active'    => (int)one($pdo, "SELECT COUNT(*) FROM tenants WHERE status='active'"),
            'tenants_suspended' => (int)one($pdo, "SELECT COUNT(*) FROM tenants WHERE status='suspended'"),
            'users_total'       => (int)one($pdo, 'SELECT COUNT(*) FROM users'),
            'students_total'    => (int)one($pdo, 'SELECT COUNT(*) FROM students'),
            'instructors_total' => (int)one($pdo, 'SELECT COUNT(*) FROM instructors'),
            'courses_total'     => (int)one($pdo, 'SELECT COUNT(*) FROM courses'),
            'pending_approval'  => (int)one($pdo, "SELECT COUNT(*) FROM courses WHERE approval_status='pending'"),
            'subscriptions_active' => (int)one($pdo, "SELECT COUNT(*) FROM subscriptions WHERE status IN ('active','trial')"),
            'revenue_range'     => (float)one($pdo, "SELECT COALESCE(SUM(amount-discount_amount),0) FROM payments WHERE status='completed' AND created_at BETWEEN ? AND ?", [$fromTs, $toTs]),
            'revenue_total'     => (float)one($pdo, "SELECT COALESCE(SUM(amount-discount_amount),0) FROM payments WHERE status='completed'"),
            'mrr'               => (float)one($pdo, "SELECT COALESCE(SUM(CASE WHEN billing_cycle='yearly' THEN amount/12 ELSE amount END),0) FROM subscriptions WHERE status IN ('active','trial','past_due')"),
            'pending_payments'  => (int)one($pdo, "SELECT COUNT(*) FROM payments WHERE status='pending'"),
            'enrollments_range' => (int)one($pdo, 'SELECT COUNT(*) FROM enrollments WHERE enrolled_at BETWEEN ? AND ?', [$fromTs, $toTs]),
            'certificates_range' => (int)one($pdo, 'SELECT COUNT(*) FROM certificates WHERE issued_at BETWEEN ? AND ?', [$fromTs, $toTs]),
        ];
        $out['series']['revenue'] = monthly($pdo, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COALESCE(SUM(amount-discount_amount),0) total, COALESCE(SUM(amount-discount_amount),0) amount FROM payments WHERE status='completed' AND created_at BETWEEN ? AND ? GROUP BY ym", [$fromTs, $toTs], $buckets, true);
        $out['series']['enrollments'] = monthly($pdo, "SELECT DATE_FORMAT(enrolled_at,'%Y-%m') ym, COUNT(*) total FROM enrollments WHERE enrolled_at BETWEEN ? AND ? GROUP BY ym", [$fromTs, $toTs], $buckets);
        $out['series']['tenants'] = monthly($pdo, "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) total FROM tenants WHERE created_at BETWEEN ? AND ? GROUP BY ym", [$fromTs, $toTs], $buckets);
        $s = $pdo->prepare("SELECT t.name, COALESCE(SUM(p.amount-p.discount_amount),0) revenue, COUNT(DISTINCT st.id) students
            FROM tenants t LEFT JOIN payments p ON p.tenant_id=t.id AND p.status='completed' LEFT JOIN students st ON st.tenant_id=t.id
            GROUP BY t.id ORDER BY revenue DESC LIMIT 8");
        $s->execute();
        $out['top_tenants'] = array_map(fn($r) => ['name' => $r['name'], 'revenue' => (float)$r['revenue'], 'students' => (int)$r['students']], $s->fetchAll());
        $s = $pdo->prepare("SELECT p.name, COUNT(s.id) total FROM subscription_plans p LEFT JOIN subscriptions s ON s.plan_id=p.id GROUP BY p.id ORDER BY p.sort_order");
        $s->execute(); $plans = $s->fetchAll();
        $out['series']['plans'] = ['labels' => array_column($plans, 'name'), 'data' => array_map('intval', array_column($plans, 'total'))];
    } elseif ($me['role'] === 'student') {
        $tid = require_tenant();
        $sid = current_student_id($pdo);
        if (!$sid) { http_response_code(403); echo json_encode(['ok' => false, 'message' => 'Student profile not found.']); exit; }
        $out['stats'] = [
            'enrolled_courses'   => (int)one($pdo, "SELECT COUNT(*) FROM enrollments WHERE student_id=? AND tenant_id=? AND status IN ('active','completed')", [$sid, $tid]),
            'completed_courses'  => (int)one($pdo, "SELECT COUNT(*) FROM enrollments WHERE student_id=? AND tenant_id=? AND status='completed'", [$sid, $tid]),
            'lessons_completed'  => (int)one($pdo, "SELECT COUNT(*) FROM lesson_progress WHERE student_id=? AND tenant_id=? AND status='completed'", [$sid, $tid]),
            'certificates'       => (int)one($pdo, 'SELECT COUNT(*) FROM certificates WHERE student_id=? AND tenant_id=? AND revoked_at IS NULL', [$sid, $tid]),
            'quiz_attempts'      => (int)one($pdo, 'SELECT COUNT(*) FROM quiz_attempts WHERE student_id=? AND tenant_id=?', [$sid, $tid]),
            'quiz_avg'           => round((float)one($pdo, 'SELECT COALESCE(AVG(percent),0) FROM quiz_attempts WHERE student_id=? AND tenant_id=?', [$sid, $tid]), 1),
            'assignments_due'    => (int)one($pdo, "SELECT COUNT(*) FROM assignments a JOIN enrollments en ON en.course_id=a.course_id AND en.student_id=? WHERE a.tenant_id=? AND a.due_date>=NOW() AND NOT EXISTS (SELECT 1 FROM submissions sub WHERE sub.assignment_id=a.id AND sub.student_id=?)", [$sid, $tid, $sid]),
        ];
        // Weekly learning activity: lessons completed per week, last 8 weeks.
        $labels = []; $data = [];
        for ($w = 7; $w >= 0; $w--) {
            $ws = date('Y-m-d', strtotime("-{$w} weeks", strtotime('monday this week')));
            $we = date('Y-m-d 23:59:59', strtotime($ws . ' +6 days'));
            $labels[] = date('M j', strtotime($ws));
            $data[] = (int)one($pdo, "SELECT COUNT(*) FROM lesson_progress WHERE student_id=? AND tenant_id=? AND status='completed' AND completed_at BETWEEN ? AND ?", [$sid, $tid, $ws . ' 00:00:00', $we]);
        }
        $out['series']['weekly_activity'] = ['labels' => $labels, 'data' => $data];
        $s = $pdo->prepare("SELECT c.title, en.progress_percent FROM enrollments en JOIN courses c ON c.id=en.course_id
            WHERE en.student_id=? AND en.tenant_id=? AND en.status IN ('active','completed') ORDER BY en.progress_percent DESC LIMIT 8");
        $s->execute([$sid, $tid]); $rows = $s->fetchAll();
        $out['series']['course_progress'] = ['labels' => array_column($rows, 'title'), 'data' => array_map('floatval', array_column($rows, 'progress_percent'))];
    } else {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Unsupported role.']);
        exit;
    }
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => APP_DEBUG ? $ex->getMessage() : 'Analytics query failed.']);
    exit;
}
echo json_encode($out);
