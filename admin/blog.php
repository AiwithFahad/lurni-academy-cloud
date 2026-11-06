<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
$me = require_role(['admin']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Blog'; $active = 'blog';
require_permission($pdo, 'courses.manage');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = post('title');
        $slug = slugify($title);
        $status = post('status') === 'published' ? 'published' : 'draft';
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $cover = post('cover_image');
        if (!empty($_FILES['cover_file']['name'])) {
            try { $up = handle_upload($_FILES['cover_file'], 'blog', ['png', 'jpg', 'jpeg', 'webp'], 3); if ($up) $cover = '/' . $up; }
            catch (Throwable $ex) { flash('error', $ex->getMessage()); redirect('/admin/blog.php'); }
        }
        if ($id) {
            $pdo->prepare('UPDATE blog_posts SET title=?, category=?, excerpt=?, content=?, cover_image=?, status=?, published_at=COALESCE(published_at, ?) WHERE id=? AND tenant_id=?')
                ->execute([$title, post('category', 'General'), post('excerpt'), post('content'), $cover ?: null, $status, $publishedAt, $id, $tid]);
            flash('success', 'Post updated.');
        } else {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE slug=?');
            $chk->execute([$slug]);
            if ((int)$chk->fetchColumn() > 0) $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            $pdo->prepare('INSERT INTO blog_posts(tenant_id,author_id,title,slug,category,excerpt,content,cover_image,status,published_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([$tid, $me['id'], $title, $slug, post('category', 'General'), post('excerpt'), post('content'), $cover ?: null, $status, $publishedAt]);
            flash('success', 'Post created.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM blog_posts WHERE id=? AND tenant_id=?')->execute([(int)($_POST['id'] ?? 0), $tid]);
        flash('success', 'Post deleted.');
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE blog_posts SET status=IF(status='published','draft','published'), published_at=IF(status='published', published_at, NOW()) WHERE id=? AND tenant_id=?")
            ->execute([$id, $tid]);
        flash('success', 'Post status changed.');
    }
    redirect('/admin/blog.php');
}

$s = $pdo->prepare("SELECT bp.*, u.name AS author_name FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id
    WHERE bp.tenant_id=? ORDER BY bp.id DESC");
$s->execute([$tid]); $rows = $s->fetchAll();
$edit = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = $pdo->prepare('SELECT * FROM blog_posts WHERE id=? AND tenant_id=?'); $s->execute([$eid, $tid]); $edit = $s->fetch() ?: null; }
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
  <div class="col-xl-8"><div class="card">
    <div class="card-header bg-white"><input class="form-control" type="search" placeholder="Live search posts…" data-live-search="#blogTable"></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0" id="blogTable">
      <thead><tr><th>Post</th><th>Category</th><th>Author</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
      <tr><td><strong><?= e($r['title']) ?></strong><br><small class="text-muted">/blog/<?= e($r['slug']) ?><?= $r['published_at'] ? ' · ' . e(date('M j, Y', strtotime((string)$r['published_at']))) : '' ?></small></td>
        <td><span class="badge bg-primary-subtle text-primary"><?= e($r['category'] ?? 'General') ?></span></td>
        <td><small><?= e($r['author_name'] ?? '—') ?></small></td>
        <td><span class="badge bg-<?= $r['status'] === 'published' ? 'success' : 'secondary' ?>-subtle text-<?= $r['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
        <td class="text-end text-nowrap">
          <?php if ($r['status'] === 'published'): ?><a class="btn btn-sm btn-outline-secondary" href="/blog-post.php?slug=<?= e($r['slug']) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-primary"><?= $r['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form>
          <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this post?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No posts yet — write the first one.</td></tr><?php endif; ?>
      </tbody></table></div></div></div>
  <div class="col-xl-4"><div class="card"><div class="card-header bg-white fw-bold"><?= $edit ? 'Edit post' : 'New post' ?></div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($edit['category'] ?? 'General') ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft" <?= ($edit['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($edit['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></div>
      </div>
      <div class="mb-2"><label class="form-label">Excerpt</label><textarea class="form-control" name="excerpt" rows="2"><?= e($edit['excerpt'] ?? '') ?></textarea></div>
      <div class="mb-2"><label class="form-label">Content</label><textarea class="form-control" name="content" rows="8"><?= e($edit['content'] ?? '') ?></textarea></div>
      <div class="mb-3"><label class="form-label">Cover image URL (or upload)</label><input class="form-control mb-1" name="cover_image" value="<?= e($edit['cover_image'] ?? '') ?>" placeholder="/assets/img/hero.svg">
        <input class="form-control" type="file" name="cover_file" accept=".png,.jpg,.jpeg,.webp"></div>
      <button class="btn btn-primary w-100"><?= $edit ? 'Update post' : 'Create post' ?></button>
      <?php if ($edit): ?><a class="btn btn-link w-100" href="/admin/blog.php">Cancel</a><?php endif; ?>
    </form></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
