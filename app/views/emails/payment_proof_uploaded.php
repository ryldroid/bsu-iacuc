<?php
ob_start();
/** @var string $title */
/** @var string $actor_name */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><strong><?= htmlspecialchars($actor_name) ?></strong> uploaded proof of payment for the protocol <strong><?= htmlspecialchars($title) ?></strong>.</p>
<p>You can review it at: <a href="<?= ROOT ?>/personnel/home?status=reviewed&open_payment=<?= $protocol_id ?>"><?= ROOT ?>/personnel/home?status=reviewed&open_payment=<?= $protocol_id ?></a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>