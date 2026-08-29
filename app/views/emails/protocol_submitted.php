<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($title) ?></strong> has been submitted and is now under review.</p>
<p>You can track its status at: <a href="<?= ROOT ?>/submissions"><?= ROOT ?>/submissions</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>