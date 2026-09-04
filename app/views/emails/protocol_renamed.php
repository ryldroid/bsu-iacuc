<?php
ob_start();
/** @var string $first_name */
/** @var string $old_title */
/** @var string $new_title */
/** @var string $role_label */
/** @var string $actor_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($old_title) ?></strong> was renamed to <strong><?= htmlspecialchars($new_title) ?></strong> by <?= htmlspecialchars($role_label) ?> - <?= htmlspecialchars($actor_name) ?>.</p>
<p>You can view the details at: <a href="<?= ROOT ?>/submissions"><?= ROOT ?>/submissions</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>