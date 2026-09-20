<?php
ob_start();
/** @var string $first_name */
?>

<p>Hi <?= htmlspecialchars($first_name) ?>,</p>
<p>Unfortunately, your BSU-IACUC personnel application has been <strong>rejected</strong>.</p>
<p>If you believe this is a mistake, please contact an administrator.</p>
<p>— BSU-IACUC Team</p>
<?php return ob_get_clean(); ?>