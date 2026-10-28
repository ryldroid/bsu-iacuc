<?php
ob_start();
/** @var string $title */
/** @var string $role_label */
/** @var string $actor_name */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><?= htmlspecialchars($role_label) ?> <strong><?= htmlspecialchars($actor_name) ?></strong> resubmitted the protocol <strong><?= htmlspecialchars($title) ?></strong>. It is back under review.</p>
<p>You can <?= Mailer::link(ROOT . '/apply/viewer/' . protocol_ref((int) $protocol_id, $title), 'view it here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>