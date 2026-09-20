<?php
ob_start();
/** @var string $submitter */
/** @var string $title */
/** @var int $protocol_id */
?>

<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted a new protocol: <strong><?= htmlspecialchars($title) ?></strong>.</p>
<p>You can review it at: <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?></a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>