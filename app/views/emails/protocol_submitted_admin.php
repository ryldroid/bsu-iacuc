<?php
ob_start();
/** @var string $submitter */
/** @var string $title */
?>

<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted a new protocol: <strong><?= htmlspecialchars($title) ?></strong>.</p>
<p>You can review it at: <a href="<?= ROOT ?>/admin/records"><?= ROOT ?>/admin/records</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>