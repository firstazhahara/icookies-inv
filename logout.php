<?php
require_once dirname(__FILE__) . '/includes/auth.php';
if (is_logged_in()) { audit('LOGOUT', 'auth', $_SESSION['username'], 'User logged out'); }
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
redirect(base_url() . '/login.php');