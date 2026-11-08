<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$df = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dt = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$where = " WHERE 1=1"; $params = array();
if ($df !== '') { $where .= " AND h.incoming_date >= ?"; $params[] = $df; }
if ($dt !== '') { $where .= " AND h.incoming_date <= ?"; $params[] = $dt; }

$rows = db_all("SELECT h.incoming_number, h.incoming_date, w.warehouse_code, s.supplier_name,
                       p.part_code, b.batch_code, d.qty_received, d.purchase_price, d.total_cost,
                       d.expiration_date, h.remarks
                FROM inventory_incoming_headers h
                JOIN warehouses w ON w.id = h.warehouse_id
                LEFT JOIN suppliers s ON s.id = h.supplier_id
                JOIN inventory_incoming_details d ON d.incoming_id = h.id
                JOIN master_parts p ON p.id = d.part_id
                LEFT JOIN inventory_batches b ON b.id = d.batch_id"
                . $where . " ORDER BY h.id DESC, d.id", $params);

$out = array();
foreach ($rows as $r) {
    $out[] = array($r['incoming_number'], $r['incoming_date'], $r['warehouse_code'],
                   $r['supplier_name'], $r['part_code'], $r['batch_code'], $r['qty_received'],
                   $r['purchase_price'], $r['total_cost'], $r['expiration_date'], $r['remarks']);
}
csv_download('incoming_' . date('Ymd') . '.csv',
    array('Incoming_Number','Incoming_Date','Warehouse','Supplier','Part_ID','Batch_ID',
          'Qty_Received','Purchase_Price','Total_Cost','Expiration_Date','Remarks'), $out);