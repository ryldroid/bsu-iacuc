<?php
ob_start();
/** @var string $first_name */
/** @var string $login_url */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your BSU-IACUC account has been <strong>reactivated</strong> by an administrator. You can <?= Mailer::link($login_url, 'log in to the portal here') ?> again.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>