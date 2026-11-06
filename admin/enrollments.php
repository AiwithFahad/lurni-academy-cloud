<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Enrollments'; $active = 'enrollments';
require_permission($pdo, 'enrollments.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'enroll') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        try {
            $pdo->prepare('INSERT INTO enrollments(tenant_id,student_id,course_id) VALUES(?,?,?)')->execute([$tid, $studentId, $courseId]);
            log_activity($pdo, 'Student enrolled (manual) in course #' . $courseId);
            flash('success', 'Student enrolled.');
        } catch (Throwable $ex) {
            flash('error', 'Could not enroll — the student may already be enrolled in this course.');
        }
    } elseif ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $st = in_array(post('status'), ['active', 'completed', 'cancelled'], true) ? post('status') : 'active';
        $pdo->prepare('UPDATE enrollments SET status=?, completed_at=? WHERE id=? AND tenant_id=?')
            ->execute([$st, $st === 'completed' ? date('Y-m-d H:i:s') : null, $id, $tid]);
        flash('success', 'Enrollment updated.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM enrollments WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Enrollment removed.');
    }
    redirect('/admin/enrollments.php');
}

$q = getp('q'); $courseF = (int)($_GET['course_id'] ?? 0);
$where = ['en.tenant_id=?']; $args = [$tid];
if ($q !== '') { $where[] = '(u.name LIKE ? OR c.title LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%"]); }
if ($courseF) { $where[] = 'en.course_id=?'; $args[] = $courseF; }
$s = $pdo->prepare('SELECT COUNT(*) FROM enrollments en JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 12);
$s = $pdo->prepare("SELECT en.*, u.name AS student_name, u.email, c.title AS course_title FROM enrollments en
    JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id
    WHERE " . implode(' AND ', $where) . " ORDER BY en.enrolled_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();

$students = $pdo->prepare('SELECT st.id, u.name FROM students st JOIN users u ON u.id=st.user_id WHERE st.tenant_id=? ORDER BY u.name');
$students->execute([$tid]); $students = $students->fetchAll();
$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();

if (getp('export') === 'csv') {
    $s = $pdo->prepare('SELECT u.name AS student, u.email, c.title AS course, en.progress_percent, en.status, en.enrolled_at FROM enrollments en JOIN students st ON st.id=en.student_id JOIN users u ON u.id=st.user_id JOIN courses c ON c.id=en.course_id WHERE ' . implode(' AND ', $where));
    $s->execute($args);
    export_csv($s->fetchAll(), 'enrollments.csv');
}
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="card-header bg-white"><form class="row g-2" method="get">
      <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search student or course…"></div>
      <div class="col-md-4"><select class="form-select" name="course_id"><option value="">All courses</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseF === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Filter</button>
      <a class="btn btn-outline-secondary" href="?export=csv&q=<?= e($q) ?>&course_id=<?= $courseF ?>"><i class="bi bi-download"></i></a></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Student</th><th>Course</th><th>Progress</th><th>Status</th><th>Enrolled</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
      <tr><td><strong><?= e($r['student_name']) ?></strong><br><small class="text-muted"><?= e($r['email']) ?></small></td>
        <td><small><?= e($r['course_title']) ?></small></td>
        <td style="min-width:130px"><div class="progress progress-slim"><div class="progress-bar" style="width:<?= (float)$r['progress_percent'] ?>%"></div></div><small class="text-muted"><?= (float)$r['progress_percent'] ?>%</small></td>
        <td><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <select class="form-select form-select-sm" name="status" onchange="this.form.submit()"><?php foreach (['active', 'completed', 'cancelled'] as $st): ?><option value="<?= $st ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></form></td>
        <td><small><?= e(date('M j, Y', strtotime($r['enrolled_at']))) ?></small></td>
        <td class="text-end"><form method="post" class="d-inline" onsubmit="return confirm('Remove this enrollment?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No enrollments found.</td></tr><?php endif; ?>
      </tbody></table></div>
    <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> enrollments</small><?= pagination_html($pg, '?' . http_build_query(['q' => $q, 'course_id' => $courseF])) ?></div>
  </div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold">Enroll a student</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="enroll">
      <div class="mb-2"><label class="form-label">Student</label><select class="form-select" name="student_id" required>
        <?php foreach ($students as $st): ?><option value="<?= (int)$st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label">Course</label><select class="form-select" name="course_id" required>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-primary w-100"><i class="bi bi-person-check me-1"></i>Enroll student</button>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
