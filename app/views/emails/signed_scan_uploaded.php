<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($title) ?></strong> has been signed by the IACUC chairperson. No action needed. It will be endorsed to DA-CARFU next.</p>
<p>You can <?= Mailer::link(ROOT . '/submissions?highlight=' . $protocol_id, 'view it here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>