<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your BSU-IACUC personnel account has been <strong>approved</strong>!</p>
<p>You can now log in and <?= Mailer::link(ROOT . '/user/account', 'view your account here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>