<?php
ob_start();
/** @var string $submitter */
/** @var int|null $protocol_id */
$link = ROOT . (!empty($protocol_id) ? '/apply/viewer/' . $protocol_id : '/personnel/home');
?>

<p>Hi,</p>
<p><strong><?= htmlspecialchars($submitter) ?></strong> resubmitted a previously rejected IACUC training certificate.</p>
<p>You can <?= Mailer::link($link, 'review the certificate here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>