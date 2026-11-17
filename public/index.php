<?php
/**
 * Front controller — the single web entry point.
 * All requests are routed through this file.
 */

use App\Core\Router;

define('APP_ROOT', dirname(__DIR__));

// Composer autoload
require APP_ROOT . '/vendor/autoload.php';

// Load config
$config = require APP_ROOT . '/config.php';

// Timezone
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');

// Start session
session_name($config['session_name'] ?? 'crm_session');

// Hardened session cookie parameters:
// - HttpOnly: no JS access.
// - SameSite=Lax: prevents CSRF via top-level cross-site requests while
//   still allowing safe cross-origin GETs (e.g. returning from OAuth).
// - Secure: only on HTTPS so a stray local/HTTP request never gets pinned.
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) === 443;
if (PHP_SAPI === 'cli-server') {
    // PHP built-in dev server: treat localhost as safe (no cookie secure flag).
    $secureCookie = false;
}
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Autoload helpers
require APP_ROOT . '/src/Core/View.php';

// Register the global view() helper (namespace-free).
if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        return \App\Core\View::render($view, $data);
    }
}

// Dispatch (Router::dispatch sets security headers before any route runs).
Router::dispatch();
