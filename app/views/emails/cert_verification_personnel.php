<?php
ob_start();
/** @var string $submitter */
/** @var int $user_id */
?>

<p>Hi,</p>
<p><strong><?= htmlspecialchars($submitter) ?></strong> submitted an IACUC training certificate for verification.</p>
<p>You can review it at: <a href="<?= ROOT ?>/personnel/home?open_researcher=<?= $user_id ?>"><?= ROOT ?>/personnel/home?open_researcher=<?= $user_id ?></a></p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>