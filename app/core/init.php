<?php
// ===== ERROR REPORTING & TIMEZONE =====
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

// ===== SESSION SETUP =====
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

define('SESSION_TIMEOUT', 1800);
ini_set('session.gc_maxlifetime', (string) (SESSION_TIMEOUT + 300));

session_name('bsu_iacuc');
session_start();

// ===== IDLE SESSION TIMEOUT =====
$isBackgroundPoll = in_array(trim($_GET['url'] ?? '', '/'), [
    'notifications',
    'notifications/index',
    'submissions/checkupdates',
    'personnel/checkupdates',
    'user/verification_status',
], true);

define('EMAIL_VERIFY_SNOOZE', 3600); // "Remind me later" on the verify email modal: seconds before it asks again

if (isset($_SESSION['last_activity'])) {
    $idle = time() - $_SESSION['last_activity'];
    if ($idle > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}

if (!$isBackgroundPoll) {
    $_SESSION['last_activity'] = time();
}

// ===== LOAD APP FILES =====
require_once 'config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once 'TextSanitizer.php';

// ===== CLASS AUTOLOAD =====
spl_autoload_register(function (string $class) {
    if (str_contains($class, '\\')) {
        return;
    }

    foreach (['core', 'models'] as $dir) {
        $file = dirname(__DIR__) . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
