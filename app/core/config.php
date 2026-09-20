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
