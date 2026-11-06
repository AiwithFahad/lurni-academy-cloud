<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Categories'; $active = 'categories';
require_permission($pdo, 'categories.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $slug = slugify(post('name'));
        if ($id) {
            $pdo->prepare('UPDATE categories SET name=?, icon=?, color=?, description=?, status=? WHERE id=? AND tenant_id=?')
                ->execute([post('name'), post('icon', 'bi-grid'), post('color', '#4f46e5'), post('description'), post('status', 'active'), $id, $tid]);
            flash('success', 'Category updated.');
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE tenant_id=? AND slug=?');
            $chk->execute([$tid, $slug]);
            if ((int)$chk->fetchColumn() > 0) { flash('error', 'A category with this name already exists.'); redirect('/admin/categories.php'); }
            $pdo->prepare('INSERT INTO categories(tenant_id,name,slug,icon,color,description,status) VALUES(?,?,?,?,?,?,?)')
                ->execute([$tid, post('name'), $slug, post('icon', 'bi-grid'), post('color', '#4f46e5'), post('description'), post('status', 'active')]);
            flash('success', 'Category created.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Category deleted. Its courses were kept and uncategorized.');
    }
    redirect('/admin/categories.php');
}
$rows = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM courses co WHERE co.category_id=c.id) AS course_count
    FROM categories c WHERE c.tenant_id=? ORDER BY c.name");
$rows->execute([$tid]); $rows = $rows->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM categories WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="row g-3">
    <?php foreach ($rows as $c): ?>
      <div class="col-md-6"><div class="card h-100"><div class="card-body d-flex gap-3">
        <span class="cat-icon" style="--cat:<?= e($c['color'] ?: '#4f46e5') ?>"><i class="bi <?= e($c['icon'] ?: 'bi-grid') ?>"></i></span>
        <div class="flex-grow-1"><strong><?= e($c['name']) ?></strong>
          <span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?> ms-1"><?= e($c['status']) ?></span>
          <p class="text-muted small mb-1"><?= e($c['description'] ?? '') ?></p>
          <small class="text-muted"><?= (int)$c['course_count'] ?> courses · slug: <?= e($c['slug']) ?></small>
          <div class="mt-2 d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
          <form method="post" onsubmit="return confirm('Delete category?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div>
        </div></div></div></div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="col-12"><div class="empty-state"><img src="/assets/img/empty.svg" alt=""><p class="text-muted mt-2">No categories yet — create your first one.</p></div></div><?php endif; ?>
    </div>
  </div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit category' : 'Create category' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
      <div class="row g-2">
        <div class="col-7 mb-2"><label class="form-label">Icon (Bootstrap Icons)</label><input class="form-control" name="icon" value="<?= e($edit['icon'] ?? 'bi-grid') ?>" placeholder="bi-code-slash"></div>
        <div class="col-5 mb-2"><label class="form-label">Color</label><input class="form-control form-control-color w-100" type="color" name="color" value="<?= e($edit['color'] ?? '#4f46e5') ?>"></div>
      </div>
      <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="hidden" <?= ($edit['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update category' : 'Create category' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/admin/categories.php">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
