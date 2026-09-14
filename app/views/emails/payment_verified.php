<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your payment for protocol <strong><?= htmlspecialchars($title) ?></strong> has been verified.</p>
<p>You can view the details at: <a href="<?= ROOT ?>/submissions?status=reviewed"><?= ROOT ?>/submissions?status=reviewed</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>