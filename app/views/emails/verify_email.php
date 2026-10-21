<?php
ob_start();
/** @var string $first_name */
/** @var string $verify_url */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Please verify your email address to start using BSU-IACUC and receive email notifications about your protocol submissions.</p>
<p>
  <a href="<?= $verify_url ?>">Click here to verify your email</a>
</p>
<p>This link expires in <strong>24 hours</strong>. If you didn't create this account, you can safely ignore this email.</p>
<p>- BSU-CCARD Staff</p>
<?php return ob_get_clean(); ?>