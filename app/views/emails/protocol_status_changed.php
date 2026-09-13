<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var string $status */
/** @var int $protocol_id */
$link = strtolower($status) === 'needs revision'
  ? ROOT . '/apply/viewer/' . $protocol_id
  : ROOT . '/submissions?status=' . strtolower(str_replace(' ', '-', $status));
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($title) ?></strong> status has changed to: <strong><?= htmlspecialchars($status) ?></strong>.</p>
<p>You can view the details at: <a href="<?= $link ?>"><?= $link ?></a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>