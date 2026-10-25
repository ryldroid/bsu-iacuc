<?php
ob_start();
/** @var string $first_name */
/** @var string $reason */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your IACUC training certificate was rejected.</p>
<p><strong>Reason:</strong> <?= htmlspecialchars($reason) ?></p>
<p>Your protocol review is on hold until a new certificate is verified. Press <strong>Resubmit Certificate</strong> on <?= Mailer::link(ROOT . '/submissions', 'My Protocols') ?> or on your protocol. If your protocol is still under review, you can attach a revised file too.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>