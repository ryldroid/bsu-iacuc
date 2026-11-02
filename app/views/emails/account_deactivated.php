<?php
ob_start();
/** @var string $first_name */
/** @var bool $self_reactivate */
/** @var string $reason */
/** @var int $months */
/** @var string $login_url */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<?php if ($self_reactivate): ?>
<p>Your BSU-IACUC account is now <strong>inactive</strong> because your clearance has ended and you have no protocol pending. Your protocols and records are unchanged.</p>
<p>No action needed. When you are ready to submit a new protocol, <?= Mailer::link($login_url, 'log in to the portal') ?> and confirm that you want to reactivate your account.</p>
<?php elseif ($reason === 'dormant'): ?>
<p>Your BSU-IACUC account has been <strong>deactivated</strong> because you have not signed in for <?= (int) $months ?> months. You can no longer sign in.</p>
<p>To use it again, please ask an administrative staff member of BSU-CCARD to reactivate it.</p>
<?php else: ?>
<p>Your BSU-IACUC account has been <strong>deactivated</strong> by an administrator. You can no longer sign in, but your protocols and records are unchanged.</p>
<p>If you did not expect this, or you would like it reactivated, please contact BSU-CCARD.</p>
<?php endif; ?>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>