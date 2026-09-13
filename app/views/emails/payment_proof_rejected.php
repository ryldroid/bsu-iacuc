<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var string $reason */
/** @var int $protocol_id */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your payment proof for protocol <strong><?= htmlspecialchars($title) ?></strong> was rejected.</p>
<p><strong>Reason:</strong> <?= htmlspecialchars($reason) ?></p>
<p>Please resubmit your proof of payment at: <a href="<?= ROOT ?>/submissions?status=reviewed"><?= ROOT ?>/submissions?status=reviewed</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>