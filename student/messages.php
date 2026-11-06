<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messenger.php';
$me = require_role(['student']);
$pdo = db(); check_tenant_active($pdo);
$tid = require_tenant();
$pageTitle = 'Messages'; $active = 'messages';
handle_messenger_post($pdo, $me, $tid, '/student/messages.php');
require __DIR__ . '/../includes/header.php';
render_messenger($pdo, $me, $tid);
require __DIR__ . '/../includes/footer.php';
