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

// IPs of reverse proxies/load balancers this app sits behind, comma-separated.
// Only requests coming from one of these are allowed to override the
// connection's REMOTE_ADDR with an X-Forwarded-For/X-Client-IP header, since
// those headers are otherwise just client-supplied and trivially spoofed.
// Empty by default: with no trusted proxy configured, REMOTE_ADDR is used
// as-is everywhere (including here on infinityfree, until its proxy IP is known).
//
// A static property instead of a define() constant: the list has to be built
// at runtime from the env var, and Intelephense can only resolve define()
// constants whose value is a literal, so a computed define() always shows as
// "undefined" elsewhere even though it works fine at runtime.
final class TrustedProxies
{
  public static array $ips = [];
}

TrustedProxies::$ips = array_filter(array_map(
  'trim',
  explode(',', EnvLoader::get('TRUSTED_PROXIES', ''))
));

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
