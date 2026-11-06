<?php
declare(strict_types=1);

/**
 * One-time installer: imports database.sql into the configured MySQL
 * database. Delete this file after installation.
 *
 * Usage: copy .env.example to .env, set DB_* values, then open
 * /install.php in the browser (or run: php install.php from the CLI).
 */
require_once __DIR__ . '/config/config.php';

$isCli = PHP_SAPI === 'cli';
$out = static function (string $msg) use ($isCli): void {
    echo $isCli ? $msg . PHP_EOL : '<p style="font-family:system-ui">' . htmlspecialchars($msg) . '</p>' . PHP_EOL;
};

$sqlFile = __DIR__ . '/database.sql';
if (!is_file($sqlFile)) { $out('database.sql not found next to install.php.'); exit(1); }

try {
    $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $dbName = env('DB_NAME', 'lurni_academy');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) { $out('Invalid DB_NAME in .env'); exit(1); }
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");

    $sql = (string)file_get_contents($sqlFile);
    // Execute statement-by-statement so DELIMITER-free dumps import cleanly.
    $statements = array_filter(array_map('trim', preg_split('/;\s*\R/', $sql) ?: []));
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) continue;
        $pdo->exec($stmt);
        $count++;
    }
    $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
    $out("Install complete: {$count} statements executed, {$tables} tables present in `{$dbName}`.");
    $out('Demo logins (password: password) — super@lurni.test, admin@demo.com, instructor@demo.com, student@demo.com');
    $out('IMPORTANT: delete install.php now, and never expose it in production.');
} catch (Throwable $e) {
    $out('Install failed: ' . $e->getMessage());
    exit(1);
}
