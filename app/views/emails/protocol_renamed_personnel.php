<?php
ob_start();
/** @var string $old_title */
/** @var string $new_title */
/** @var string $role_label */
/** @var string $actor_name */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><?= htmlspecialchars($role_label) ?> <strong><?= htmlspecialchars($actor_name) ?></strong> renamed the protocol <strong><?= htmlspecialchars($old_title) ?></strong> to <strong><?= htmlspecialchars($new_title) ?></strong>.</p>
<p>You can view it at: <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?></a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>