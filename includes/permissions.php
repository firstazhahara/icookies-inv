<?php
$GLOBALS['PERM_MATRIX'] = array(
    'ADMIN' => array('*'),
    'WAREHOUSE STAFF' => array(
        'inventory.view','incoming.create','incoming.view','outgoing.create','outgoing.view',
        'transfer.create','transfer.view','stocktaking.create','stocktaking.count','stocktaking.view',
        'disposal.request','disposal.view','reports.view','expiration.view','valuation.view','trx.view'
    ),
    'SUPERVISOR' => array(
        'inventory.view','incoming.view','outgoing.view','transfer.view','transfer.approve',
        'stocktaking.view','stocktaking.approve','disposal.view','disposal.approve',
        'reports.view','expiration.view','valuation.view','trx.view'
    ),
    'SALES' => array(
        'inventory.view','sales.create','sales.view','outgoing.view','reports.view','expiration.view'
    )
);

function can($perm) {
    $roles = current_roles();
    foreach ($roles as $r) {
        if ($r === 'ADMIN') return true;
        if (isset($GLOBALS['PERM_MATRIX'][$r])) {
            if (in_array($perm, $GLOBALS['PERM_MATRIX'][$r])) return true;
        }
    }
    return false;
}

function require_perm($perm) {
    require_login();
    if (!can($perm)) {
        http_response_code(403);
        die('<div style="padding:20px;font-family:Tahoma">Access denied: missing permission <b>'
            . e($perm) . '</b>. <a href="' . base_url() . '/index.php">Back to dashboard</a></div>');
    }
}