<link rel="stylesheet" href="<?= asset_css('footer.css') ?>">

<?php if (!defined('FORM_ALERT_JS_LOADED')) include __DIR__ . '/flash.php'; ?>

<footer>
  <div class="footer-links">
    <?php if (!empty($welcomeSteps)): ?>
      <button type="button" data-guide-start="welcome">User Guide</button>
    <?php endif; ?>
    <a href="<?= ROOT ?>/assets/manuals/<?= ($role ?? '') ?: 'researcher' ?>-manual.pdf" target="_blank" rel="noopener noreferrer">User Manual</a>
  </div>
  <p>&copy; 2026 BSU-IACUC. All rights reserved.</p>
</footer>
</body>

</html>