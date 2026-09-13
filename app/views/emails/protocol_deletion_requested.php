<?php
ob_start();
/** @var string $title */
/** @var string $role_label */
/** @var string $actor_name */
/** @var string $reason */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><?= htmlspecialchars($role_label) ?> <strong><?= htmlspecialchars($actor_name) ?></strong> has requested the deletion of protocol <strong><?= htmlspecialchars($title) ?></strong>.</p>
<p><strong>Reason:</strong> <?= htmlspecialchars($reason) ?></p>
<p>You can review and act on this request at: <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>?open=deletion_request"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?>?open=deletion_request</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>