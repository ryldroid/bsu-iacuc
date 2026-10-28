<link rel="stylesheet" href="<?= asset_css('footer.css') ?>">

<?php if (!defined('FORM_ALERT_JS_LOADED')) include __DIR__ . '/flash.php'; ?>

<footer>
  <div class="footer-links">
    <?php if (!empty($welcomeSteps)): ?>
      <button type="button" data-guide-start="welcome">User Guide</button>
    <?php endif; ?>
    <a href="<?= ROOT ?>/assets/manuals/<?= ($role ?? '') ?: 'researcher' ?>-manual.pdf" target="_blank" rel="noopener noreferrer">User Manual</a>
  </div>
  <!-- ===== Developer contact ===== -->
  <div class="footer-contact">
    <span class="footer-note">Found an issue or have comments/suggestions? Contact the developers:</span>
    <?php foreach (['meryl.arnobit@gmail.com', 'malipe.sheldon01@gmail.com'] as $devEmail): ?>
      <a <?= email_link_attributes($devEmail) ?> class="footer-contact-icon" aria-label="Email <?= $devEmail ?>" title="<?= $devEmail ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#email-icon" />
        </svg>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="footer-copy">&copy; 2026 BSU-IACUC. All rights reserved.</p>
</footer>
<script>
  // Touch screens open their own mail app instead of Gmail in the browser
  if (matchMedia('(hover: none)').matches) {
    document.querySelectorAll('a[data-mailto]').forEach((link) => {
      link.href = link.dataset.mailto;
      link.removeAttribute('target');
    });
  }
</script>
</body>

</html>