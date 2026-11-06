<?php
declare(strict_types=1);
$pageTitle = 'Privacy Policy';
require_once __DIR__ . '/includes/site-header.php';
?>
<section class="section-pad"><div class="container" style="max-width:820px">
  <h1>Privacy Policy</h1>
  <p class="text-muted">Last updated: November 2026</p>
  <h2 class="h5 fw-bold mt-4">1. What we collect</h2>
  <p class="text-muted">Account details (name, email), academy (tenant) information, course and learning activity (enrollments, lesson progress, quiz attempts, submissions), subscription and payment records, and support messages. Payment card details are never stored on our servers — manual payments are recorded with a reference only.</p>
  <h2 class="h5 fw-bold mt-4">2. Tenant isolation</h2>
  <p class="text-muted">Every academy's business records carry a tenant identifier and are isolated at the database and application level. Academy administrators, instructors and students can only access records belonging to their own tenant.</p>
  <h2 class="h5 fw-bold mt-4">3. How we use data</h2>
  <p class="text-muted">To operate the platform: authentication, course delivery, progress tracking, certificates, billing, invoices, notifications and support. Platform-level analytics shown to the Super Admin are aggregated counts and revenue figures.</p>
  <h2 class="h5 fw-bold mt-4">4. Security</h2>
  <p class="text-muted">Passwords are stored using secure one-way hashing. Sessions are protected, forms use CSRF tokens, database access uses prepared statements, and sensitive actions are written to audit logs. See the security documentation in the project repository for details.</p>
  <h2 class="h5 fw-bold mt-4">5. Retention & your rights</h2>
  <p class="text-muted">Academies may request export or deletion of their tenant data at any time via support. Audit logs are retained for security and billing accountability. Contact support@lurni.example for any privacy request.</p>
  <h2 class="h5 fw-bold mt-4">6. Cookies</h2>
  <p class="text-muted">We use a single essential session cookie for sign-in. No advertising trackers are used.</p>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
