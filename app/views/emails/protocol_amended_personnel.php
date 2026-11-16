<?php
ob_start();
/** @var string $title */
/** @var string $actor_name */
/** @var string $note */
/** @var int $protocol_id */
?>

<p>Hi,</p>
<p><strong><?= htmlspecialchars($actor_name) ?></strong> submitted an amendment for the approved protocol <strong><?= htmlspecialchars($title) ?></strong>. No further review is required.</p>
<p><strong>What changed:</strong><br><?= nl2br(htmlspecialchars($note)) ?></p>
<p>You can <?= Mailer::link(ROOT . '/apply/viewer/' . protocol_ref((int) $protocol_id, $title), 'view it here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>