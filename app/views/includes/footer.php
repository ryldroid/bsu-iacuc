<link rel="stylesheet" href="<?= asset_css('footer.css') ?>">

<?php if (!defined('FORM_ALERT_JS_LOADED')) include __DIR__ . '/flash.php'; ?>

<footer>
  <div class="footer-links">
    <?php if (!empty($welcomeSteps)): ?>
      <button type="button" data-guide-start="welcome">User Guide</button>
    <?php endif; ?>
    <a href="<?= ROOT ?>/assets/manuals/<?= ($role ?? '') ?: 'researcher' ?>-manual.pdf" target="_blank" rel="noopener noreferrer">User Manual</a>
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