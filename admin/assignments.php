<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Assignments'; $active = 'assignments';
require_permission($pdo, 'assignments.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $due = post('due_date') !== '' ? str_replace('T', ' ', post('due_date')) . ':00' : null;
        if ($id) {
            $pdo->prepare('UPDATE assignments SET course_id=?, title=?, instructions=?, due_date=?, max_points=? WHERE id=? AND tenant_id=?')
                ->execute([(int)($_POST['course_id'] ?? 0), post('title'), post('instructions'), $due, max(1, (int)($_POST['max_points'] ?? 100)), $id, $tid]);
            flash('success', 'Assignment updated.');
        } else {
            $pdo->prepare('INSERT INTO assignments(tenant_id,course_id,created_by,title,instructions,due_date,max_points) VALUES(?,?,?,?,?,?,?)')
                ->execute([$tid, (int)($_POST['course_id'] ?? 0), $me['id'], post('title'), post('instructions'), $due, max(1, (int)($_POST['max_points'] ?? 100))]);
            flash('success', 'Assignment created.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM assignments WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Assignment deleted (submissions removed).');
    }
    redirect('/admin/assignments.php');
}
$courses = $pdo->prepare('SELECT id, title FROM courses WHERE tenant_id=? ORDER BY title');
$courses->execute([$tid]); $courses = $courses->fetchAll();
$s = $pdo->prepare("SELECT a.*, c.title AS course_title, u.name AS author,
    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id) AS submission_count,
    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id AND s.status IN ('submitted','late')) AS pending_count
    FROM assignments a JOIN courses c ON c.id=a.course_id LEFT JOIN users u ON u.id=a.created_by
    WHERE a.tenant_id=? ORDER BY a.due_date IS NULL, a.due_date");
$s->execute([$tid]); $rows = $s->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM assignments WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Assignment</th><th>Course</th><th>Due</th><th>Submissions</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $a): ?>
    <tr><td><strong><?= e($a['title']) ?></strong><br><small class="text-muted">by <?= e($a['author'] ?? 'Admin') ?> · max <?= (int)$a['max_points'] ?> pts</small></td>
      <td><small><?= e($a['course_title']) ?></small></td>
      <td><small><?= $a['due_date'] ? e(date('M j, Y H:i', strtotime($a['due_date']))) : 'No deadline' ?></small></td>
      <td><?= (int)$a['submission_count'] ?><?php if ((int)$a['pending_count']): ?> <span class="badge bg-warning-subtle text-warning"><?= (int)$a['pending_count'] ?> to grade</span><?php endif; ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/grading.php?assignment_id=<?= (int)$a['id'] ?>">Grade</a>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$a['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this assignment?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No assignments yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit assignment' : 'Create assignment' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Course</label><select class="form-select" name="course_id" required>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['course_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label">Instructions</label><textarea class="form-control" name="instructions" rows="3"><?= e($edit['instructions'] ?? '') ?></textarea></div>
      <div class="row g-2">
        <div class="col-7 mb-2"><label class="form-label">Due date</label><input class="form-control" type="datetime-local" name="due_date" value="<?= $edit && $edit['due_date'] ? e(date('Y-m-d\TH:i', strtotime($edit['due_date']))) : '' ?>"></div>
        <div class="col-5 mb-2"><label class="form-label">Max points</label><input class="form-control" type="number" min="1" name="max_points" value="<?= e($edit['max_points'] ?? '100') ?>"></div>
      </div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update assignment' : 'Create assignment' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/admin/assignments.php">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
