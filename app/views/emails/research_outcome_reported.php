<?php
ob_start();
/** @var string $title */
/** @var string $role_label */
/** @var string $actor_name */
/** @var string $outcome */
/** @var int $record_id */
?>

<p>Hi,</p>
<p><?= htmlspecialchars($role_label) ?> <strong><?= htmlspecialchars($actor_name) ?></strong> reported the research <strong><?= htmlspecialchars($title) ?></strong> as <strong><?= htmlspecialchars($outcome) ?></strong>. The record was updated automatically.</p>
<p>You can <?= Mailer::link(ROOT . '/personnel/records?highlight=' . (int) $record_id, 'open the record here') ?>.</p>
<p>- BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>