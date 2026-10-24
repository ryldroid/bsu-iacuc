<?php
ob_start();
/** @var string $first_name */
/** @var string $reason */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your IACUC training certificate was rejected.</p>
<p><strong>Reason:</strong> <?= htmlspecialchars($reason) ?></p>
<p>Open My Account and upload a new certificate at: <a href="<?= ROOT ?>/submissions"><?= ROOT ?>/submissions</a></p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>