<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var string $status */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($title) ?></strong> status has changed to: <strong><?= htmlspecialchars($status) ?></strong>.</p>
<p>You can view the details at: <a href="<?= ROOT ?>/submissions"><?= ROOT ?>/submissions</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>