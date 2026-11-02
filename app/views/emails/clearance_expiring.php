<?php
ob_start();
/** @var string $first_name */
/** @var string $expiry_date */
/** @var string $inactive_date */
/** @var bool $ended */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your animal research clearance <?= $ended ? 'ended' : 'ends' ?> on <strong><?= htmlspecialchars($expiry_date) ?></strong>.</p>
<p>On <strong><?= htmlspecialchars($inactive_date) ?></strong>, your account will be marked inactive unless you have a pending protocol. No action needed. You may reactivate it at any time by <?= Mailer::link(ROOT . '/user/login', 'logging in to the portal here') ?> and confirming.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>