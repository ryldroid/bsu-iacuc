<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your IACUC training certificate has been verified. No action needed. You can now submit protocols at: <a href="<?= ROOT ?>/apply"><?= ROOT ?>/apply</a></p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>