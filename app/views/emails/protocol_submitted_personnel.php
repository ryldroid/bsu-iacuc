<?php
ob_start();
/** @var string $submitter */
/** @var string $title */
/** @var int $protocol_id */
?>

<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted a new protocol: <strong><?= htmlspecialchars($title) ?></strong>.</p>
<p>You can <?= Mailer::link(ROOT . '/apply/viewer/' . protocol_ref((int) $protocol_id, $title), 'view it here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>