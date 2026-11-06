<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <a class="d-flex align-items-center gap-2 text-decoration-none mb-3" href="/index.php">
          <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
          <span class="fw-bold text-white">Lurni Academy Cloud</span>
        </a>
        <p class="text-white-50">The complete Learning Management SaaS for academies, schools and training centers — courses, instructors, quizzes, certificates and subscriptions in one platform.</p>
        <div class="d-flex gap-2">
          <a class="social-btn" href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
          <a class="social-btn" href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
          <a class="social-btn" href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
          <a class="social-btn" href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="text-white">Platform</h6>
        <ul class="footer-links">
          <li><a href="/courses.php">Courses</a></li>
          <li><a href="/categories.php">Categories</a></li>
          <li><a href="/instructors.php">Instructors</a></li>
          <li><a href="/pricing.php">Pricing</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="text-white">Company</h6>
        <ul class="footer-links">
          <li><a href="/about.php">About us</a></li>
          <li><a href="/blog.php">Blog</a></li>
          <li><a href="/contact.php">Contact</a></li>
          <li><a href="/auth/register.php">Start free trial</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="text-white">Portals</h6>
        <ul class="footer-links">
          <li><a href="/auth/login.php">Academy Admin</a></li>
          <li><a href="/auth/login.php">Instructor</a></li>
          <li><a href="/auth/login.php">Student</a></li>
          <li><a href="/auth/login.php">Super Admin</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="text-white">Legal</h6>
        <ul class="footer-links">
          <li><a href="/privacy.php">Privacy policy</a></li>
          <li><a href="/terms.php">Terms of service</a></li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary my-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 text-white-50 small">
      <span>© <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</span>
      <span>Built with Core PHP 8 · MySQL · Bootstrap 5</span>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
