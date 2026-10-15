<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your payment for protocol <strong><?= htmlspecialchars($title) ?></strong> has been verified. No action needed. CCARD will now sign your protocol and endorse it to DA-CARFU.</p>
<p>You can view the details at: <a href="<?= ROOT ?>/submissions?highlight=<?= $protocol_id ?>"><?= ROOT ?>/submissions?highlight=<?= $protocol_id ?></a></p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>