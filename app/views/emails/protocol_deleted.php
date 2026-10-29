<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var string $actor_name */
/** @var string $reason */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($title) ?></strong> has been deleted by <?= htmlspecialchars($actor_name) ?>. No action needed.</p>
<p><strong>Reason:</strong> <?= htmlspecialchars($reason) ?></p>
<p>You can <?= Mailer::link(ROOT . '/submissions', 'view your other protocols here') ?>.</p>
<p>If you have questions about this, please contact BSU-CCARD.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>