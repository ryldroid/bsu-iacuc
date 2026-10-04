<?php
ob_start();
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p>The protocol <strong><?= htmlspecialchars($title) ?></strong> has been paid. Assign its IPN, then print and sign it.</p>
<p>You can review it at: <a href="<?= ROOT ?>/personnel/home?status=reviewed"><?= ROOT ?>/personnel/home?status=reviewed</a></p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>