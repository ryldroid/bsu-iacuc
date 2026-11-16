<?php
ob_start();
/** @var string $submitter */
/** @var string $title */
/** @var int $protocol_id */
/** @var bool $first_submission */
?>

<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted a new protocol: <strong><?= htmlspecialchars($title) ?></strong>.</p>
<?php if (!empty($first_submission)): ?>
  <p>This is their first submission, with a training certificate to verify.</p>
<?php endif; ?>
<p>You can <?= Mailer::link(ROOT . '/apply/viewer/' . protocol_ref((int) $protocol_id, $title), 'view the protocol and training certificate here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>