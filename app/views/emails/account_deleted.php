<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your BSU-IACUC account has been <strong>deleted</strong> at your request. Your personal details were removed and you can no longer sign in.</p>
<p>If you did not expect this, please contact BSU-CCARD right away.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>