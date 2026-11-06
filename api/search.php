<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

// GET /api/search.php?q=... — JSON course search scoped to the caller's
// tenant (used by the topbar search suggestions).
header('Content-Type: application/json');
$me = current_user();
if (!$me || empty($me['tenant_id'])) { echo json_encode(['results' => []]); exit; }
$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) { echo json_encode(['results' => []]); exit; }
$s = $pdo->prepare("SELECT id, title, level, rating FROM courses
    WHERE tenant_id=? AND status='published' AND approval_status='approved' AND visibility<>'private' AND (title LIKE ? OR subtitle LIKE ?)
    ORDER BY rating DESC LIMIT 8");
$s->execute([(int)$me['tenant_id'], "%$q%", "%$q%"]);
echo json_encode(['results' => array_map(fn($r) => [
    'id' => (int)$r['id'],
    'title' => $r['title'],
    'level' => $r['level'],
    'rating' => (float)$r['rating'],
    'url' => '/student/browse.php?q=' . urlencode($q),
], $s->fetchAll())]);
