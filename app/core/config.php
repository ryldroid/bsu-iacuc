<?php
require_once __DIR__ . '/env_loader.php';

EnvLoader::load();

$isLocal = ($_SERVER['SERVER_NAME'] == 'localhost');

if ($isLocal) {
  define('ROOT', EnvLoader::get('LOCAL_ROOT', 'http://localhost/mvc-sept/portal'));
  define('DBNAME', EnvLoader::get('LOCAL_DBNAME', 'bsu_iacuc'));
  define('DBSERVER', EnvLoader::get('LOCAL_DBSERVER', 'localhost'));
  define('DBUSER', EnvLoader::get('LOCAL_DBUSER', 'root'));
  define('DBPASS', EnvLoader::get('LOCAL_DBPASS', ''));
} else {
  define('ROOT', EnvLoader::get('PROD_ROOT', 'https://iacuc.infinityfree.me/portal'));
  define('DBNAME', EnvLoader::get('PROD_DBNAME'));
  define('DBSERVER', EnvLoader::get('PROD_DBSERVER'));
  define('DBUSER', EnvLoader::get('PROD_DBUSER'));
  define('DBPASS', EnvLoader::get('PROD_DBPASS'));
}

define('VIEWSPATH', dirname(__DIR__) . '/views/');
define('CSSPATH', ROOT . '/assets/css');
define('JSPATH', ROOT . '/assets/js');
define('IMGPATH', ROOT . '/assets/images');

define('ASSETS_FS_PATH', dirname(__DIR__, 2) . '/portal/assets');

function asset_css(string $file): string
{
  $path = ASSETS_FS_PATH . '/css/' . $file;
  clearstatcache(true, $path);
  $v = @filemtime($path);
  return CSSPATH . '/' . $file . ($v ? '?v=' . $v : '');
}

function asset_js(string $file): string
{
  $path = ASSETS_FS_PATH . '/js/' . $file;
  clearstatcache(true, $path);
  $v = @filemtime($path);
  return JSPATH . '/' . $file . ($v ? '?v=' . $v : '');
}

define('MAIL_HOST', EnvLoader::get('MAIL_HOST'));
define('MAIL_PORT', EnvLoader::get('MAIL_PORT', 587));
define('MAIL_USERNAME', EnvLoader::get('MAIL_USERNAME'));
define('MAIL_PASSWORD', EnvLoader::get('MAIL_PASSWORD'));
define('MAIL_FROM', EnvLoader::get('MAIL_FROM'));
define('MAIL_FROMNAME', EnvLoader::get('MAIL_FROMNAME'));

// Optional separate sender for account-security emails (verify_email, password_reset),
// so a Brevo per-sender unsubscribe on the notifications sender can't affect these.
// Falls back to the main sender until a second verified sender is set up in Brevo.
define('MAIL_FROM_SECURITY', EnvLoader::get('MAIL_FROM_SECURITY', MAIL_FROM));
define('MAIL_FROMNAME_SECURITY', EnvLoader::get('MAIL_FROMNAME_SECURITY', MAIL_FROMNAME));

// Brevo transactional API access (for resubscribing a contact Brevo has blocked)
// and the shared secret expected on the Brevo unsubscribe webhook URL.
define('BREVO_API_KEY', EnvLoader::get('BREVO_API_KEY'));
define('BREVO_WEBHOOK_SECRET', EnvLoader::get('BREVO_WEBHOOK_SECRET', ''));

if (!$isLocal) {
  $required = [
    'PROD_DBNAME',
    'PROD_DBSERVER',
    'PROD_DBUSER',
    'PROD_DBPASS',
    'MAIL_PASSWORD'
  ];

  foreach ($required as $req) {
    if (!EnvLoader::get($req)) {
      error_log("Missing required env: $req");
      die("Configuration error. Please contact administrator.");
    }
  }
}
