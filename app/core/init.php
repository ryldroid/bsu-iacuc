<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_name('bsu_iacuc');
session_start();

define('SESSION_TIMEOUT', 1800);

if (isset($_SESSION['last_activity'])) {
    $idle = time() - $_SESSION['last_activity'];
    if ($idle > SESSION_TIMEOUT) {
        $hadUser = isset($_SESSION['user']);

        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);

        if ($hadUser) {
            $_SESSION['flash_error'] = 'Your session expired due to inactivity. Please log in again.';
        }
    }
}

$_SESSION['last_activity'] = time();

require_once 'config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once 'ErrorPage.php';
require_once 'Mailer.php';
require_once 'Notifier.php';
require_once 'Model.php';
require_once 'Controller.php';
require_once 'App.php';
