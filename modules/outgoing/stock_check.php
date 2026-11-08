<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();
header('Content-Type: application/json');
$wh = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$pt = isset($_GET['part_id']) ? (int)$_GET['part_id'] : 0;
$qty = $wh && $pt ? stock_on_hand($pt, $wh) : 0;
echo json_encode(array('qty' => $qty));