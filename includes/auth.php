<?php
if (session_id() === '') {
    session_name('LEGACY_INV_SID');
    session_start();
}
require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(__FILE__) . '/functions.php';

function current_user_id() { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; }
function is_logged_in()    { return current_user_id() > 0; }
function current_roles()   { return isset($_SESSION['roles']) ? $_SESSION['roles'] : array(); }
function has_role($r)      { return in_array($r, current_roles()); }

function require_login() {
    if (!is_logged_in()) { redirect(base_url() . '/login.php'); }
}

require_once dirname(__FILE__) . '/permissions.php';