<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
// Tenant management moved to institutions.php (same data, new name).
require_role(['super_admin']);
redirect('/super-admin/institutions.php');
