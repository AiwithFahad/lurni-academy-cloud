<?php
declare(strict_types=1);
$pageTitle = 'Terms of Service';
require_once __DIR__ . '/includes/site-header.php';
?>
<section class="section-pad"><div class="container" style="max-width:820px">
  <h1>Terms of Service</h1>
  <p class="text-muted">Last updated: November 2026</p>
  <h2 class="h5 fw-bold mt-4">1. The service</h2>
  <p class="text-muted"><?= e(APP_NAME) ?> provides a multi-tenant Learning Management System: public course catalog, academy administration, instructor and student portals, subscriptions and billing tools.</p>
  <h2 class="h5 fw-bold mt-4">2. Accounts & tenants</h2>
  <p class="text-muted">Each registered organization receives an isolated tenant workspace. Account owners are responsible for their users, content and compliance with applicable law. The platform operator may suspend tenants that violate these terms or applicable law.</p>
  <h2 class="h5 fw-bold mt-4">3. Subscriptions & payments</h2>
  <p class="text-muted">Plans are billed monthly or yearly. Paid plans may begin with a free trial. Payments are recorded as pending and become completed only after verified payment confirmation. Expired subscriptions enter a grace period before access is limited. Plan limits (students, instructors, courses, storage) apply as published on the pricing page.</p>
  <h2 class="h5 fw-bold mt-4">4. Content</h2>
  <p class="text-muted">Academies and instructors retain ownership of their course content. By publishing content you grant the platform the technical rights needed to host and display it to enrolled students.</p>
  <h2 class="h5 fw-bold mt-4">5. Acceptable use</h2>
  <p class="text-muted">No unlawful content, no attempts to access other tenants' data, no abuse of file uploads or messaging. Uploaded files are validated and stored under randomized names.</p>
  <h2 class="h5 fw-bold mt-4">6. Liability</h2>
  <p class="text-muted">The service is provided “as is”. To the maximum extent permitted by law, liability is limited to amounts paid in the preceding three months.</p>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
