<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your payment for protocol <strong><?= htmlspecialchars($title) ?></strong> has been verified. No action needed. CCARD will now sign your protocol and endorse it to Department of Agriculture – Regional Field Office Cordillera Administrative Region (DA-RFO CAR).</p>
<p>You can <?= Mailer::link(ROOT . '/submissions?highlight=' . $protocol_id, 'view the details here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>