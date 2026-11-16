<?php
ob_start();
/** @var string $title */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p>The protocol <strong><?= htmlspecialchars($title) ?></strong> has been paid. Assign the IPN, then print and sign the protocol.</p>
<p>You can <?= Mailer::link(ROOT . '/personnel/home', 'review it here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>