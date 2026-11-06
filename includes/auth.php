<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function require_login(?string $loginUrl = null): array {
    $u = current_user();
    if (!$u) redirect($loginUrl ?? '/auth/login.php');
    return $u;
}
function require_role(array $roles, ?string $loginUrl = null): array {
    $u = require_login($loginUrl);
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('Access denied for your role.'); }
    return $u;
}
function require_tenant(): int {
    $t = tenant_id();
    if ($t === null) { http_response_code(403); exit('Tenant context required.'); }
    return $t;
}
/** Suspended tenants lose app access immediately (Super Admins unaffected). */
function check_tenant_active(PDO $pdo): void {
    $t = tenant_id(); if ($t === null) return;
    $s = $pdo->prepare('SELECT status FROM tenants WHERE id=?');
    $s->execute([$t]);
    if ($s->fetchColumn() === 'suspended') {
        $_SESSION = [];
        session_destroy();
        redirect('/auth/login.php?error=suspended');
    }
}
/** RBAC: checks the role_permissions matrix seeded in the database. */
function has_permission(PDO $pdo, string $permission): bool {
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'super_admin') return true;
    static $cache = [];
    if (isset($cache[$permission])) return $cache[$permission];
    $s = $pdo->prepare('SELECT COUNT(*) FROM roles r
                        JOIN role_permissions rp ON rp.role_id=r.id
                        JOIN permissions p ON p.id=rp.permission_id
                        WHERE r.name=? AND p.name=?');
    $s->execute([$u['role'], $permission]);
    return $cache[$permission] = ((int)$s->fetchColumn() > 0);
}
function require_permission(PDO $pdo, string $permission): void {
    if (!has_permission($pdo, $permission)) { http_response_code(403); exit('You do not have permission: ' . e($permission)); }
}
/** Home route per role after login. */
function role_home(string $role): string {
    return match ($role) {
        'super_admin' => '/super-admin/index.php',
        'admin'       => '/admin/dashboard.php',
        'instructor'  => '/instructor/dashboard.php',
        'student'     => '/student/dashboard.php',
        default       => '/index.php',
    };
}
/** Store the authenticated user in the session (regenerates the session id). */
function login_user(array $row): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => (int)$row['id'],
        'tenant_id' => $row['tenant_id'] !== null ? (int)$row['tenant_id'] : null,
        'name'      => $row['name'],
        'email'     => $row['email'],
        'role'      => $row['role'],
        'avatar'    => $row['avatar'] ?? null,
    ];
}
