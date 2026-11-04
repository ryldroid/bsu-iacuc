<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your BSU-IACUC personnel account has been <strong>approved</strong>!</p>
<p>You can now log in through <?= Mailer::link(ROOT . '/personnel', ROOT . '/personnel') ?>. Personnel do not use the researcher login.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>