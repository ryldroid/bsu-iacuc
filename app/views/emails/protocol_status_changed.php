<?php
ob_start();
/** @var string $first_name */
/** @var string $title */
/** @var string $status */
/** @var string $next_step */
/** @var int $protocol_id */
$link = strtolower($status) === 'needs revision'
  ? ROOT . '/apply/viewer/' . $protocol_id
  : ROOT . '/submissions?highlight=' . $protocol_id;
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your protocol <strong><?= htmlspecialchars($title) ?></strong> <?= htmlspecialchars($next_step) ?></p>
<?php if (strtolower($status) === 'approved'): ?>
  <p>Office hours: Monday to Friday, 8:00 AM to 5:00 PM. The office location is on our <?= Mailer::link(ROOT . '/contact', 'contact page') ?>.</p>
  <p>Note that your account will be marked inactive after your clearance expires and you have no pending protocols. You may reactivate it at any time by logging in to the portal.</p>
<?php endif; ?>
<p>You can <?= Mailer::link($link, 'view the details here') ?>.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>