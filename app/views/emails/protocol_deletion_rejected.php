<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var string $actor_name */
/** @var string $reason */
/** @var int $protocol_id */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your request to delete protocol <strong><?= htmlspecialchars($title) ?></strong> was rejected by <?= htmlspecialchars($actor_name) ?>.</p>
<p><strong>Reason:</strong> <?= htmlspecialchars($reason) ?></p>
<p>The protocol remains active. You can view it at: <a href="<?= ROOT ?>/apply/viewer/<?= $protocol_id ?>"><?= ROOT ?>/apply/viewer/<?= $protocol_id ?></a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>