<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Instructors'; $active = 'instructors';
require_permission($pdo, 'instructors.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'create') {
        if ($err = limit_error($pdo, $tid, 'instructors')) { flash('error', $err); redirect('/admin/instructors.php'); }
        $email = strtolower(post('email'));
        $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
        $chk->execute([$email]);
        if ((int)$chk->fetchColumn() > 0) { flash('error', 'A user with this email already exists.'); redirect('/admin/instructors.php'); }
        $token = bin2hex(random_bytes(24));
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO users(tenant_id,name,email,password_hash,role,status,activation_token,created_at) VALUES(?,?,?,?,"instructor","pending",?,NOW())')
            ->execute([$tid, post('name'), $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $token]);
        $uid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO instructors(tenant_id,user_id,title,bio,expertise,status) VALUES(?,?,?,?,?,"active")')
            ->execute([$tid, $uid, post('title'), post('bio'), post('expertise')]);
        $pdo->commit();
        audit_log($pdo, 'instructor_created', 'users', $uid);
        log_activity($pdo, 'Instructor invited: ' . post('name'));
        flash('success', 'Instructor created. Invitation link: /auth/activate.php?token=' . $token);
    } elseif ($action === 'update') {
        $iid = (int)($_POST['instructor_id'] ?? 0);
        $s = $pdo->prepare('SELECT user_id FROM instructors WHERE id=? AND tenant_id=?');
        $s->execute([$iid, $tid]); $uid = (int)$s->fetchColumn();
        if ($uid) {
            $pdo->prepare('UPDATE users SET name=? WHERE id=? AND tenant_id=?')->execute([post('name'), $uid, $tid]);
            $pdo->prepare('UPDATE instructors SET title=?, bio=?, expertise=?, status=? WHERE id=? AND tenant_id=?')
                ->execute([post('title'), post('bio'), post('expertise'), post('status'), $iid, $tid]);
            flash('success', 'Instructor updated.');
        }
    } elseif ($action === 'delete') {
        $iid = (int)($_POST['instructor_id'] ?? 0);
        $s = $pdo->prepare('SELECT user_id FROM instructors WHERE id=? AND tenant_id=?');
        $s->execute([$iid, $tid]); $uid = (int)$s->fetchColumn();
        if ($uid) { $pdo->prepare('DELETE FROM users WHERE id=? AND tenant_id=?')->execute([$uid, $tid]); flash('success', 'Instructor removed. Their courses were kept and unassigned.'); }
    }
    redirect('/admin/instructors.php');
}

$q = getp('q');
$where = ['i.tenant_id=?']; $args = [$tid];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR i.expertise LIKE ?)'; $args = array_merge($args, ["%$q%", "%$q%", "%$q%"]); }
$s = $pdo->prepare('SELECT COUNT(*) FROM instructors i JOIN users u ON u.id=i.user_id WHERE ' . implode(' AND ', $where));
$s->execute($args); $total = (int)$s->fetchColumn();
$pg = paginate($total, 10);
$s = $pdo->prepare("SELECT i.*, u.name, u.email, u.status AS user_status,
    (SELECT COUNT(*) FROM courses c WHERE c.instructor_id=i.id) AS course_count,
    (SELECT COUNT(*) FROM enrollments en JOIN courses c ON c.id=en.course_id WHERE c.instructor_id=i.id) AS learners
    FROM instructors i JOIN users u ON u.id=i.user_id WHERE " . implode(' AND ', $where) . " ORDER BY i.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$s->execute($args); $rows = $s->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) {
    $s = $pdo->prepare('SELECT i.*, u.name, u.email FROM instructors i JOIN users u ON u.id=i.user_id WHERE i.id=? AND i.tenant_id=?');
    $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null;
}
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="card-header bg-white"><form class="row g-2" method="get">
      <div class="col-md-9"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search instructors…"></div>
      <div class="col-md-3"><button class="btn btn-primary w-100">Search</button></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Instructor</th><th>Expertise</th><th>Courses</th><th>Learners</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
      <tr><td><span class="avatar-circle sm"><?= e(initials($r['name'])) ?></span> <strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['email']) ?> · <?= e($r['title'] ?? '') ?></small></td>
        <td><small><?= e($r['expertise'] ?? '') ?></small></td><td><?= (int)$r['course_count'] ?></td><td><?= (int)$r['learners'] ?></td>
        <td><span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'danger' ?>-subtle text-<?= $r['status'] === 'active' ? 'success' : 'danger' ?>"><?= e($r['status']) ?></span>
        <?php if ($r['user_status'] === 'pending'): ?><span class="badge bg-warning-subtle text-warning">invite pending</span><?php endif; ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Remove this instructor account?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="instructor_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No instructors yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    <div class="card-footer bg-white d-flex justify-content-between"><small class="text-muted"><?= $total ?> instructors</small><?= pagination_html($pg, '?q=' . urlencode($q)) ?></div>
  </div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit instructor' : 'Add / invite instructor' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if ($edit): ?><input type="hidden" name="instructor_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <div class="mb-2"><label class="form-label">Full name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
      <?php if (!$edit): ?><div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
      <?php else: ?><div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($edit['email']) ?>" disabled></div><?php endif; ?>
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="e.g. Senior Web Instructor"></div>
      <div class="mb-2"><label class="form-label">Expertise (comma separated)</label><input class="form-control" name="expertise" value="<?= e($edit['expertise'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Bio</label><textarea class="form-control" name="bio" rows="3"><?= e($edit['bio'] ?? '') ?></textarea></div>
      <?php if ($edit): ?><div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= $edit['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= $edit['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option></select></div><?php endif; ?>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update instructor' : 'Create & get invite link' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/admin/instructors.php">Cancel</a><?php endif; ?>
    </form>
  </div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
