<?php
declare(strict_types=1);
$pageTitle = 'Contact Us';
$sitePage = 'contact';
require_once __DIR__ . '/includes/site-header.php';
$pdo = db();
$sent = false;
if (is_post()) {
    verify_csrf();
    $name = post('name'); $email = strtolower(post('email'));
    $subject = post('subject', 'Contact'); $message = post('message');
    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $message !== '') {
        $s = $pdo->prepare('INSERT INTO contact_messages(name,email,subject,message,created_at) VALUES(?,?,?,?,NOW())');
        $s->execute([$name, $email, $subject, $message]);
        $sent = true;
    } else {
        flash('error', 'Please complete your name, a valid email and a message.');
    }
}
?>
<section class="page-hero"><div class="container">
  <h1>Contact us</h1>
  <p class="text-muted mb-0">Questions about the platform, demos for your institution, or support — we reply within one business day.</p>
</div></section>
<section class="section-pad pt-4"><div class="container">
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card p-4 h-100">
        <h2 class="h6 fw-bold mb-3">Reach the team</h2>
        <p class="small text-muted"><i class="bi bi-envelope text-primary me-2"></i>support@lurni.example</p>
        <p class="small text-muted"><i class="bi bi-telephone text-primary me-2"></i>+1 (555) 010-2400</p>
        <p class="small text-muted"><i class="bi bi-geo-alt text-primary me-2"></i>Lurni Academy Cloud — Remote-first team</p>
        <hr>
        <h3 class="h6 fw-bold">Request a demo</h3>
        <p class="small text-muted mb-0">Tell us about your academy in the form and choose “Demo request” as the subject — we will walk you through the Super Admin, academy and student portals live.</p>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card p-4">
        <?php if ($sent): ?>
          <div class="text-center py-4"><span class="brand-mark mx-auto mb-3"><i class="bi bi-send-check"></i></span>
          <h2 class="h5 fw-bold">Message sent</h2><p class="text-muted mb-0">Thanks — your message is in our inbox and a ticket has been logged.</p></div>
        <?php else: ?>
        <h2 class="h6 fw-bold mb-3">Send a message</h2>
        <form method="post" novalidate><?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Your name *</label><input class="form-control" id="name" name="name" required value="<?= e(post('name')) ?>"></div>
            <div class="col-md-6"><label class="form-label" for="email">Email *</label><input class="form-control" type="email" id="email" name="email" required value="<?= e(post('email')) ?>"></div>
            <div class="col-12"><label class="form-label" for="subject">Subject</label>
              <select class="form-select" id="subject" name="subject">
                <?php foreach (['General question', 'Demo request', 'Sales', 'Support', 'Partnership', 'Newsletter signup'] as $opt): ?><option <?= post('subject') === $opt ? 'selected' : '' ?>><?= $opt ?></option><?php endforeach; ?>
              </select></div>
            <div class="col-12"><label class="form-label" for="message">Message *</label><textarea class="form-control" id="message" name="message" rows="5" required><?= e(post('message')) ?></textarea></div>
          </div>
          <button class="btn btn-gradient mt-4" type="submit"><i class="bi bi-send me-1"></i>Send message</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div></section>
<?php require_once __DIR__ . '/includes/site-footer.php'; ?>
