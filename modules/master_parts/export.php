<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$rows = db_all("SELECT part_code, device_type, device_model, part_category, part_variant,
                       warranty_month, purch_idr, sell_idr, is_active, created_at
                FROM master_parts ORDER BY part_code");
$out = array();
foreach ($rows as $r) {
    $out[] = array($r['part_code'], $r['device_type'], $r['device_model'], $r['part_category'],
                   $r['part_variant'], $r['warranty_month'], $r['purch_idr'], $r['sell_idr'],
                   $r['is_active'] ? 'ACTIVE' : 'INACTIVE', $r['created_at']);
}
audit('EXPORT', 'master_parts', 'ALL', 'Exported part master CSV');
csv_download('part_master_' . date('Ymd') . '.csv',
    array('Part_ID','Device_Type','Device_Model','Part_Category','Part_Variant',
          'Warranty_month','Purch_IDR','Sell_IDR','Is_Active','Created_At'), $out);