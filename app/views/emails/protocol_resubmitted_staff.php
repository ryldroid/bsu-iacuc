<?php
ob_start();
/** @var string $title */
/** @var string $role_label */
/** @var string $actor_name */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><?= htmlspecialchars($role_label) ?> <strong><?= htmlspecialchars($actor_name) ?></strong> resubmitted the protocol <strong><?= htmlspecialchars($title) ?></strong>. It is back under review.</p>
<p>You can review it at: <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?></a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>