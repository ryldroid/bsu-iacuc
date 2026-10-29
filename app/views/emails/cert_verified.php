<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your IACUC training certificate has been verified. No action needed. Your protocol review continues, and you can now <?= Mailer::link(ROOT . '/apply', 'submit protocols here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>