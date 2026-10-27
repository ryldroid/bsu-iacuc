<?php
ob_start();
/** @var string $submitter */
/** @var int|null $protocol_id */
$link = ROOT . (!empty($protocol_id) ? '/apply/viewer/' . $protocol_id : '/personnel/home');
?>

<p>Hi,</p>
<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted an IACUC training certificate for verification. Opening the protocol asks you to verify it first.</p>
<p>You can <?= Mailer::link($link, 'review it here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>