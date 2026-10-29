<?php
ob_start();
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p>The protocol <strong><?= htmlspecialchars($title) ?></strong> has been paid. Assign its IPN, then print and sign it.</p>
<p>You can <?= Mailer::link(ROOT . '/personnel/home?status=reviewed', 'review it here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>