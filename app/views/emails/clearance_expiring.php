<?php
ob_start();
/** @var string $first_name */
/** @var string $expiry_date */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your animal research clearance expires on <strong><?= htmlspecialchars($expiry_date) ?></strong>.</p>
<p>After that date, your account will be marked inactive unless you have a pending protocol. No action needed. You may reactivate it at any time by <?= Mailer::link(ROOT . '/user/login', 'logging in to the portal here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>