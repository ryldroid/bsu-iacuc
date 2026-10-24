<?php
ob_start();
/** @var string $submitter */
/** @var string $title */
/** @var int $protocol_id */
/** @var string $link_intro */
?>

<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted a new protocol: <strong><?= htmlspecialchars($title) ?></strong>.</p>
<p><?= htmlspecialchars($link_intro ?? 'You can view it at:') ?> <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?></a></p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>