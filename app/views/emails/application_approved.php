<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Your BSU-IACUC personnel account has been <strong>approved</strong>!</p>
<p>You can now log in and view your account at: <a href="<?= ROOT ?>/users/account"><?= ROOT ?>/users/account</a></p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>