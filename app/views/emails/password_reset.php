<?php
ob_start();
/** @var string $first_name */
/** @var string $reset_url */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>We received a request to reset your BSU-IACUC password.</p>
<p>
    <a href="<?= $reset_url ?>">Click here to reset your password</a>
</p>
<p>This link expires in <strong>30 minutes</strong>. If you didn't request this, you can safely ignore this email.</p>
<p>BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>