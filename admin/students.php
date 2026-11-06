<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Students'; $active = 'students';
require_permission($pdo, 'students.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'create') {
        if ($err = limit_error($pdo, $tid, 'students')) { flash('error', $err); redirect('/admin/students.php'); }
        $email = strtolower(post('email'));
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
        $chk->execute([$email]);
        if ((int)$chk->fetchColumn() > 0) { flash('error', 'A user with this email already exists.'); redirect('/admin/students.php'); }
        $token = bin2hex(random_bytes(24));
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO users(tenant_id,name,email,password_hash,role,status,activation_token,created_at) VALUES(?,?,?,?,"student","pending",?,NOW())')
            ->execute([$tid, post('name'), $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $token]);
        $uid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO students(tenant_id,user_id,student_code,phone,status) VALUES(?,?,?,?,?)')
            ->execute([$tid, $uid, post('student_code') ?: 'STU-' . $uid, post('phone'), 'active']);
        $pdo->commit();
        audit_log($pdo, 'student_created', 'users', $uid);
        log_activity($pdo, 'Student invited: ' . post('name'));
        flash('success', 'Student created. Invitation link: /auth/activate.php?token=' . $token . ' — share it so they can set their password.');
    } elseif ($action === 'update') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare('UPDATE users SET name=?, phone=? WHERE id=? AND tenant_id=?')->execute([post('name'), post('phone'), $uid, $tid]);
        $pdo->prepare('UPDATE students SET student_code=?, status=? WHERE user_id=? AND tenant_id=?')->execute([post('student_code'), post('status'), $uid, $tid]);
        flash('success', 'Student updated.');
    } elseif ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare('DELETE FROM users WHERE id=? AND tenant_id=? AND role="student"')->execute([$uid, $tid]);
        audit_log($pdo, 'student_deleted', 'users', $uid);
        flash('success', 'Student removed (their enrollments and records were removed too).');
    }
    redirect('/admin/students.php');
}

$q = getp('q');
$where = ['st.tenant_id=?', 'u.role="student"']; $args = [$tid];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR st.student_code LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%", "%$q%"]); }
$s = $pdo->prepare('SELECT COUNT(*) FROM students st JOIN users u ON u.id=st.user_id WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 10);
$s = $pdo->prepare("SELECT st.*, u.name, u.email, u.status AS user_status, u.created_at AS joined,
    (SELECT COUNT(*) FROM enrollments en WHERE en.student_id=st.id) AS course_count
    FROM students st JOIN users u ON u.id=st.user_id WHERE " . implode(' AND ', $where) . " ORDER BY st.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();

if (getp('export') === 'csv') {
    $s = $pdo->prepare('SELECT u.name, u.email, st.student_code, st.phone, st.status, u.created_at FROM students st JOIN users u ON u.id=st.user_id WHERE ' . implode(' AND ', $where));
    $s->execute($args);
    export_csv($s->fetchAll(), 'students.csv');
}
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) {
    $s = $pdo->prepare('SELECT st.*, u.name, u.email, u.phone AS user_phone FROM students st JOIN users u ON u.id=st.user_id WHERE st.user_id=? AND st.tenant_id=?');
    $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null;
}
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="card-header bg-white"><form class="row g-2" method="get">
      <div class="col-md-7"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search students…"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
      <div class="col-md-3"><a class="btn btn-outline-secondary w-100" href="?export=csv&q=<?= e($q) ?>"><i class="bi bi-download"></i> Export CSV</a></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Student</th><th>Code</th><th>Courses</th><th>Status</th><th>Joined</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
      <tr><td><span class="avatar-circle sm"><?= e(initials($r['name'])) ?></span> <strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['email']) ?></small></td>
        <td><code><?= e($r['student_code'] ?? '') ?></code></td><td><?= (int)$r['course_count'] ?></td>
        <td><span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span>
        <?php if ($r['user_status'] === 'pending'): ?><span class="badge bg-warning-subtle text-warning">invite pending</span><?php endif; ?></td>
        <td><small><?= e(date('M j, Y', strtotime($r['joined']))) ?></small></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['user_id'] ?>">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Remove this student and all their records?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No students yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> students</small><?= pagination_html($pg, '?q=' . urlencode($q)) ?></div>
  </div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit student' : 'Add / invite student' ?></div><div class="card-body">
    <?php if ($edit): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int)$edit['user_id'] ?>">
      <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name']) ?>"></div>
      <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($edit['email']) ?>" disabled></div>
      <div class="mb-2"><label class="form-label">Student code</label><input class="form-control" name="student_code" value="<?= e($edit['student_code'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? $edit['user_phone'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['active', 'suspended', 'graduated'] as $st): ?><option value="<?= $st ?>" <?= $edit['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-primary w-100">Update student</button><a class="btn btn-link w-100" href="/admin/students.php">Cancel</a>
    </form>
    <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="name" required></div>
      <div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
      <div class="mb-2"><label class="form-label">Student code (optional)</label><input class="form-control" name="student_code" placeholder="Auto-generated"></div>
      <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
      <button class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i>Create & get invite link</button>
      <p class="text-muted small mt-2 mb-0">The student activates their account and sets a password via the invitation link.</p>
    </form>
    <?php endif; ?>
  </div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
