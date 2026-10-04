<?php
ob_start();
/** @var string $title */
/** @var string $actor_name */
/** @var string $note */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><strong><?= htmlspecialchars($actor_name) ?></strong> submitted an amendment for the approved protocol <strong><?= htmlspecialchars($title) ?></strong>. It does not need to be reviewed again.</p>
<p><strong>What changed:</strong><br><?= nl2br(htmlspecialchars($note)) ?></p>
<p>You can view it at: <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?></a></p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>