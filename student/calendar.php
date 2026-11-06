<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Learning Calendar'; $active = 'calendar';
$sid = current_student_id($pdo);

// A month grid (server-rendered) of real learning events for this student:
// assignment deadlines, submitted work, quiz attempts, completed lessons
// and certificate issues — all queried from the live records.
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : date('Y-m');
$first = new DateTime($month . '-01');
$daysInMonth = (int)$first->format('t');
$startDow = (int)$first->format('N'); // 1 = Monday
$from = $month . '-01 00:00:00'; $to = date('Y-m-t 23:59:59', strtotime($from));
$events = []; // day => [ ['icon','class','label'] ]
$add = function (string $date, string $icon, string $class, string $label) use (&$events) {
    $events[substr($date, 0, 10)][] = ['icon' => $icon, 'class' => $class, 'label' => $label];
};
if ($sid) {
    $s = $pdo->prepare("SELECT a.title, a.due_date FROM assignments a JOIN enrollments en ON en.course_id=a.course_id AND en.student_id=?
        WHERE a.tenant_id=? AND a.due_date BETWEEN ? AND ?");
    $s->execute([$sid, $tid, $from, $to]);
    foreach ($s->fetchAll() as $r) $add((string)$r['due_date'], 'bi-pencil-square', 'text-danger', 'Due: ' . $r['title']);
    $s = $pdo->prepare("SELECT a.title, sub.submitted_at FROM submissions sub JOIN assignments a ON a.id=sub.assignment_id
        WHERE sub.student_id=? AND sub.tenant_id=? AND sub.submitted_at BETWEEN ? AND ?");
    $s->execute([$sid, $tid, $from, $to]);
    foreach ($s->fetchAll() as $r) $add((string)$r['submitted_at'], 'bi-upload', 'text-primary', 'Submitted: ' . $r['title']);
    $s = $pdo->prepare("SELECT qz.title, qa.submitted_at, qa.percent FROM quiz_attempts qa JOIN quizzes qz ON qz.id=qa.quiz_id
        WHERE qa.student_id=? AND qa.tenant_id=? AND qa.submitted_at BETWEEN ? AND ?");
    $s->execute([$sid, $tid, $from, $to]);
    foreach ($s->fetchAll() as $r) $add((string)$r['submitted_at'], 'bi-patch-question', 'text-success', 'Quiz: ' . $r['title'] . ' (' . round((float)$r['percent']) . '%)');
    $s = $pdo->prepare("SELECT l.title, lp.completed_at FROM lesson_progress lp JOIN lessons l ON l.id=lp.lesson_id
        WHERE lp.student_id=? AND lp.tenant_id=? AND lp.status='completed' AND lp.completed_at BETWEEN ? AND ?");
    $s->execute([$sid, $tid, $from, $to]);
    foreach ($s->fetchAll() as $r) $add((string)$r['completed_at'], 'bi-check-circle', 'text-success', 'Lesson: ' . $r['title']);
    $s = $pdo->prepare("SELECT c.title AS course_title, cert.issued_at FROM certificates cert JOIN courses c ON c.id=cert.course_id
        WHERE cert.student_id=? AND cert.tenant_id=? AND cert.issued_at BETWEEN ? AND ?");
    $s->execute([$sid, $tid, $from, $to]);
    foreach ($s->fetchAll() as $r) $add((string)$r['issued_at'], 'bi-award', 'text-warning', 'Certificate: ' . $r['course_title']);
}
$prev = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');
$today = date('Y-m-d');
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <div><h2 class="h4 fw-bold mb-0">Learning calendar</h2><p class="text-muted mb-0"><?= $first->format('F Y') ?> — deadlines and your learning activity.</p></div>
  <div class="btn-group">
    <a class="btn btn-outline-secondary" href="?m=<?= $prev ?>"><i class="bi bi-chevron-left"></i></a>
    <a class="btn btn-outline-secondary" href="/student/calendar.php">Today</a>
    <a class="btn btn-outline-secondary" href="?m=<?= $next ?>"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>
<div class="card"><div class="card-body">
  <div class="row row-cols-7 g-1 text-center fw-semibold small text-muted mb-1">
    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="col"><?= $d ?></div><?php endforeach; ?>
  </div>
  <div class="row row-cols-7 g-1">
    <?php for ($i = 1; $i < $startDow; $i++): ?><div class="col"></div><?php endfor; ?>
    <?php for ($d = 1; $d <= $daysInMonth; $d++): $dateStr = sprintf('%s-%02d', $month, $d); $dayEvents = $events[$dateStr] ?? []; ?>
    <div class="col"><div class="border rounded p-1 h-100 <?= $dateStr === $today ? 'border-primary bg-primary-subtle' : '' ?>" style="min-height:86px">
      <div class="small fw-bold"><?= $d ?></div>
      <?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
      <div class="small text-truncate <?= e($ev['class']) ?>" title="<?= e($ev['label']) ?>"><i class="bi <?= e($ev['icon']) ?>"></i> <?= e($ev['label']) ?></div>
      <?php endforeach; ?>
      <?php if (count($dayEvents) > 3): ?><div class="small text-muted">+<?= count($dayEvents) - 3 ?> more</div><?php endif; ?>
    </div></div>
    <?php endfor; ?>
  </div>
  <div class="small text-muted mt-3">
    <i class="bi bi-pencil-square text-danger"></i> Assignment due ·
    <i class="bi bi-upload text-primary"></i> Submitted ·
    <i class="bi bi-patch-question text-success"></i> Quiz ·
    <i class="bi bi-check-circle text-success"></i> Lesson completed ·
    <i class="bi bi-award text-warning"></i> Certificate
  </div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
