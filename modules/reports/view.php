<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('reports.view');

$type = isset($_GET['type']) ? $_GET['type'] : 'current_stock';
$df   = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dt   = isset($_GET['date_to'])   ? $_GET['date_to']   : '';
$wh   = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$pt   = isset($_GET['part_id']) ? (int)$_GET['part_id'] : 0;
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$dateW = ""; $dateP = array();
if ($df !== '') { $dateW .= " AND {D} >= ?"; $dateP[] = $df; }
if ($dt !== '') { $dateW .= " AND {D} <= ?"; $dateP[] = $dt; }
$whW = $wh ? " AND {W} = ?" : "";
$ptW = $pt ? " AND {P} = ?" : "";

$title = ''; $headers = array(); $rows = array(); $numericCols = array();

switch ($type) {
case 'current_stock':
case 'stock_by_part':
    $title = 'Current Stock / Stock by Part';
    $sql = "SELECT p.part_code, p.device_type, p.device_model, p.part_category,
                   COALESCE(SUM(b.qty_remaining),0) qty,
                   COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) val
            FROM master_parts p
            LEFT JOIN inventory_batches b ON b.part_id = p.id
                 " . ($wh ? " AND b.warehouse_id = ?" : "") . "
            " . ($pt ? " WHERE p.id = ?" : "") . "
            GROUP BY p.id HAVING qty > 0 ORDER BY p.part_code";
    $rows = db_all($sql, $wh && $pt ? array($wh,$pt) : ($wh ? array($wh) : ($pt ? array($pt) : array())));
    $headers = array('Part_ID','Device_Type','Device_Model','Category','Qty','Value');
    $numericCols = array(4,5);
    break;

case 'stock_by_warehouse':
    $title = 'Stock by Warehouse';
    $rows = db_all("SELECT w.warehouse_code, w.warehouse_name, p.part_code,
                           COALESCE(SUM(b.qty_remaining),0) qty,
                           COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) val
                    FROM warehouses w
                    LEFT JOIN inventory_batches b ON b.warehouse_id = w.id
                    LEFT JOIN master_parts p ON p.id = b.part_id
                    " . ($wh ? " WHERE w.id = ?" : "") . "
                    " . ($wh ? " AND" : " WHERE") . " 1=1
                    " . ($pt ? " AND p.id = ?" : "") . "
                    GROUP BY w.id, p.id HAVING qty > 0
                    ORDER BY w.warehouse_code, p.part_code",
                    array_merge($wh ? array($wh) : array(), $pt ? array($pt) : array()));
    $headers = array('Warehouse_Code','Warehouse','Part_ID','Qty','Value');
    $numericCols = array(3,4);
    break;

case 'batch_inventory':
    $title = 'Batch Inventory';
    $rows = db_all("SELECT b.batch_code, p.part_code, w.warehouse_code, b.incoming_date,
                           b.expiration_date, b.qty_received, b.qty_remaining, b.purchase_cost,
                           (b.qty_remaining*b.purchase_cost) val
                    FROM inventory_batches b
                    JOIN master_parts p ON p.id=b.part_id
                    JOIN warehouses w ON w.id=b.warehouse_id
                    WHERE 1=1 " . ($wh ? " AND b.warehouse_id = ?" : "") . "
                    " . ($pt ? " AND b.part_id = ?" : "") . "
                    ORDER BY b.expiration_date",
                    array_merge($wh ? array($wh) : array(), $pt ? array($pt) : array()));
    $headers = array('Batch','Part','Warehouse','Incoming','Expiration','Received','Remaining','Unit Cost','Value');
    $numericCols = array(5,6,7,8);
    break;

case 'incoming_history':
    $title = 'Incoming History';
    $rows = db_all("SELECT h.incoming_number, h.incoming_date, w.warehouse_code, s.supplier_name,
                           p.part_code, b.batch_code, d.qty_received, d.purchase_price, d.total_cost,
                           d.expiration_date
                    FROM inventory_incoming_headers h
                    JOIN warehouses w ON w.id=h.warehouse_id
                    LEFT JOIN suppliers s ON s.id=h.supplier_id
                    JOIN inventory_incoming_details d ON d.incoming_id=h.id
                    JOIN master_parts p ON p.id=d.part_id
                    LEFT JOIN inventory_batches b ON b.id=d.batch_id
                    WHERE 1=1 " . ($df ? " AND h.incoming_date >= ?" : "")
                                 . ($dt ? " AND h.incoming_date <= ?" : "")
                                 . ($wh ? " AND h.warehouse_id = ?" : "")
                                 . ($pt ? " AND d.part_id = ?" : "") . "
                    ORDER BY h.id DESC",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Number','Date','Warehouse','Supplier','Part','Batch','Qty','Price','Total','Expiration');
    $numericCols = array(6,7,8);
    break;

case 'outgoing_history':
case 'sales':
case 'issuance':
    $title = ($type === 'sales') ? 'Sales Report'
           : (($type === 'issuance') ? 'Internal Issuance Report' : 'Outgoing History');
    $extra = ($type === 'sales') ? " AND h.outgoing_type='SALE'"
           : (($type === 'issuance') ? " AND h.outgoing_type='ISSUE'" : "");
    $rows = db_all("SELECT h.outgoing_number, h.outgoing_date, h.outgoing_type, w.warehouse_code,
                           p.part_code, b.batch_code, d.qty, d.purchase_cost, d.selling_price,
                           d.total_purchase_cost, d.total_selling_price, d.gross_profit,
                           d.warranty_end_date, h.customer_reference
                    FROM inventory_outgoing_headers h
                    JOIN warehouses w ON w.id=h.warehouse_id
                    JOIN inventory_outgoing_details d ON d.outgoing_id=h.id
                    JOIN master_parts p ON p.id=d.part_id
                    LEFT JOIN inventory_batches b ON b.id=d.batch_id
                    WHERE 1=1 $extra " . ($df ? " AND h.outgoing_date >= ?" : "")
                                     . ($dt ? " AND h.outgoing_date <= ?" : "")
                                     . ($wh ? " AND h.warehouse_id = ?" : "")
                                     . ($pt ? " AND d.part_id = ?" : "") . "
                    ORDER BY h.id DESC",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Number','Date','Type','Warehouse','Part','Batch','Qty','Unit Cost','Unit Sell',
                     'Total Cost','Total Sell','GP','Warranty End','Reference');
    $numericCols = array(6,7,8,9,10,11);
    break;

case 'transfers':
    $title = 'Stock Transfer Report';
    $rows = db_all("SELECT t.transfer_number, t.transfer_date, ws.warehouse_code src,
                           wd.warehouse_code dst, p.part_code, d.batch_code, d.qty,
                           d.purchase_cost, d.expiration_date, t.status
                    FROM inventory_transfers t
                    JOIN warehouses ws ON ws.id=t.source_warehouse_id
                    JOIN warehouses wd ON wd.id=t.destination_warehouse_id
                    JOIN inventory_transfer_details d ON d.transfer_id=t.id
                    JOIN master_parts p ON p.id=d.part_id
                    WHERE 1=1 " . ($df ? " AND t.transfer_date >= ?" : "")
                               . ($dt ? " AND t.transfer_date <= ?" : "")
                               . ($wh ? " AND (t.source_warehouse_id = ? OR t.destination_warehouse_id = ?)" : "")
                               . ($pt ? " AND d.part_id = ?" : "") . "
                    ORDER BY t.id DESC",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh,$wh):array(), $pt?array($pt):array()));
    $headers = array('Number','Date','Source','Destination','Part','Batch','Qty','Unit Cost',
                     'Expiration','Status');
    $numericCols = array(6,7);
    break;

case 'stocktaking':
    $title = 'Stocktaking Variance Report';
    $rows = db_all("SELECT s.stocktaking_number, s.stocktaking_date, w.warehouse_code,
                           p.part_code, b.batch_code, d.system_qty, d.physical_qty, d.variance, s.status
                    FROM stocktaking_headers s
                    JOIN warehouses w ON w.id=s.warehouse_id
                    JOIN stocktaking_details d ON d.stocktaking_id=s.id
                    JOIN master_parts p ON p.id=d.part_id
                    JOIN inventory_batches b ON b.id=d.batch_id
                    WHERE 1=1 " . ($df ? " AND s.stocktaking_date >= ?" : "")
                               . ($dt ? " AND s.stocktaking_date <= ?" : "")
                               . ($wh ? " AND s.warehouse_id = ?" : "")
                               . ($pt ? " AND d.part_id = ?" : "") . "
                    ORDER BY s.id DESC",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Number','Date','Warehouse','Part','Batch','System','Physical','Variance','Status');
    $numericCols = array(5,6,7);
    break;

case 'disposal':
    $title = 'Disposal Report';
    $rows = db_all("SELECT h.disposal_number, h.disposal_date, w.warehouse_code, h.reason,
                           p.part_code, b.batch_code, d.qty, d.purchase_cost, d.total_value, h.status
                    FROM disposal_headers h
                    JOIN warehouses w ON w.id=h.warehouse_id
                    JOIN disposal_details d ON d.disposal_id=h.id
                    JOIN master_parts p ON p.id=d.part_id
                    JOIN inventory_batches b ON b.id=d.batch_id
                    WHERE 1=1 " . ($df ? " AND h.disposal_date >= ?" : "")
                               . ($dt ? " AND h.disposal_date <= ?" : "")
                               . ($wh ? " AND h.warehouse_id = ?" : "")
                               . ($pt ? " AND d.part_id = ?" : "") . "
                    ORDER BY h.id DESC",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Number','Date','Warehouse','Reason','Part','Batch','Qty','Unit Cost','Value','Status');
    $numericCols = array(6,7,8);
    break;

case 'near_expiry':
    $title = 'Near Expiry Report';
    $rows = db_all("SELECT p.part_code, p.device_type, p.device_model, p.part_category,
                           w.warehouse_code, b.batch_code, b.incoming_date, b.expiration_date,
                           b.qty_remaining, b.purchase_cost,
                           (b.qty_remaining*b.purchase_cost) val,
                           DATEDIFF(b.expiration_date, CURDATE()) days
                    FROM inventory_batches b
                    JOIN master_parts p ON p.id=b.part_id
                    JOIN warehouses w ON w.id=b.warehouse_id
                    WHERE b.qty_remaining > 0 AND b.expiration_date >= CURDATE()
                      AND DATEDIFF(b.expiration_date, CURDATE()) <= ?
                      " . ($wh ? " AND b.warehouse_id = ?" : "")
                         . ($pt ? " AND b.part_id = ?" : "") . "
                    ORDER BY b.expiration_date",
                    array_merge(array(NEAR_EXPIRY_DAYS), $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Part_ID','Device_Type','Model','Category','Warehouse','Batch','Incoming',
                     'Expiration','Qty','Price','Value','Days');
    $numericCols = array(8,9,10,11);
    break;

case 'expired':
    $title = 'Expired Stock Report';
    $rows = db_all("SELECT p.part_code, p.device_type, p.device_model, p.part_category,
                           w.warehouse_code, b.batch_code, b.incoming_date, b.expiration_date,
                           b.qty_remaining, b.purchase_cost,
                           (b.qty_remaining*b.purchase_cost) val
                    FROM inventory_batches b
                    JOIN master_parts p ON p.id=b.part_id
                    JOIN warehouses w ON w.id=b.warehouse_id
                    WHERE b.qty_remaining > 0 AND b.expiration_date < CURDATE()
                      " . ($wh ? " AND b.warehouse_id = ?" : "")
                         . ($pt ? " AND b.part_id = ?" : "") . "
                    ORDER BY b.expiration_date",
                    array_merge($wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Part_ID','Device_Type','Model','Category','Warehouse','Batch','Incoming',
                     'Expiration','Qty','Price','Value');
    $numericCols = array(8,9,10);
    break;

case 'valuation':
    $title = 'Inventory Valuation Report';
    $rows = db_all("SELECT w.warehouse_code, p.part_code, b.batch_code, b.expiration_date,
                           b.qty_remaining, b.purchase_cost,
                           (b.qty_remaining*b.purchase_cost) cost_value,
                           (b.qty_remaining*p.sell_idr) sell_value
                    FROM inventory_batches b
                    JOIN master_parts p ON p.id=b.part_id
                    JOIN warehouses w ON w.id=b.warehouse_id
                    WHERE b.qty_remaining > 0
                      " . ($wh ? " AND b.warehouse_id = ?" : "")
                         . ($pt ? " AND b.part_id = ?" : "") . "
                    ORDER BY w.warehouse_code, p.part_code",
                    array_merge($wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Warehouse','Part','Batch','Expiration','Qty','Unit Cost','Cost Value','Sales Value');
    $numericCols = array(4,5,6,7);
    break;

case 'gross_profit':
    $title = 'Gross Profit Report';
    $rows = db_all("SELECT h.outgoing_number, h.outgoing_date, w.warehouse_code, p.part_code,
                           d.qty, d.purchase_cost, d.selling_price,
                           d.total_purchase_cost, d.total_selling_price, d.gross_profit
                    FROM inventory_outgoing_headers h
                    JOIN warehouses w ON w.id=h.warehouse_id
                    JOIN inventory_outgoing_details d ON d.outgoing_id=h.id
                    JOIN master_parts p ON p.id=d.part_id
                    WHERE h.outgoing_type='SALE'
                      " . ($df ? " AND h.outgoing_date >= ?" : "")
                         . ($dt ? " AND h.outgoing_date <= ?" : "")
                         . ($wh ? " AND h.warehouse_id = ?" : "")
                         . ($pt ? " AND d.part_id = ?" : "") . "
                    ORDER BY h.id DESC",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Number','Date','Warehouse','Part','Qty','Unit Cost','Unit Sell',
                     'Total Cost','Total Sell','Gross Profit');
    $numericCols = array(4,5,6,7,8,9);
    break;

case 'stock_movement':
default:
    $title = 'Stock Movement Report';
    $rows = db_all("SELECT t.trx_number, t.trx_date, t.trx_type, p.part_code, w.warehouse_code,
                           b.batch_code, t.qty_in, t.qty_out, t.balance_after, t.reference_number
                    FROM inventory_transactions t
                    LEFT JOIN master_parts p ON p.id=t.part_id
                    LEFT JOIN warehouses w ON w.id=t.warehouse_id
                    LEFT JOIN inventory_batches b ON b.id=t.batch_id
                    WHERE 1=1 " . ($df ? " AND DATE(t.trx_date) >= ?" : "")
                               . ($dt ? " AND DATE(t.trx_date) <= ?" : "")
                               . ($wh ? " AND t.warehouse_id = ?" : "")
                               . ($pt ? " AND t.part_id = ?" : "") . "
                    ORDER BY t.id DESC LIMIT 5000",
                    array_merge($df?array($df):array(), $dt?array($dt):array(),
                                $wh?array($wh):array(), $pt?array($pt):array()));
    $headers = array('Trx No','Date','Type','Part','Warehouse','Batch','In','Out','Balance','Reference');
    $numericCols = array(6,7,8);
    break;
}

if ($export) {
    $out = array();
    foreach ($rows as $r) { $out[] = array_values($r); }
    csv_download($type . '_' . date('Ymd') . '.csv', $headers, $out);
}

$warehouses = db_all("SELECT * FROM warehouses ORDER BY warehouse_code");
$parts      = db_all("SELECT * FROM master_parts ORDER BY part_code");

$page_title  = $title;
$breadcrumbs = array('Reports'=>base_url().'/modules/reports/index.php', $title=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
<form method="get" action="view.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="type" value="<?php echo e($type); ?>">
  <div class="fld"><label>From</label><input type="date" name="date_from" value="<?php echo e($df); ?>"></div>
  <div class="fld"><label>To</label><input type="date" name="date_to" value="<?php echo e($dt); ?>"></div>
  <div class="fld"><label>Warehouse</label><select name="warehouse_id"><option value="0">All</option>
    <?php foreach ($warehouses as $w): ?><option value="<?php echo (int)$w['id']; ?>"
      <?php if ($wh==$w['id']) echo 'selected'; ?>><?php echo e($w['warehouse_code']); ?></option><?php endforeach; ?>
  </select></div>
  <div class="fld"><label>Part</label><select name="part_id"><option value="0">All</option>
    <?php foreach ($parts as $x): ?><option value="<?php echo (int)$x['id']; ?>"
      <?php if ($pt==$x['id']) echo 'selected'; ?>><?php echo e($x['part_code']); ?></option><?php endforeach; ?>
  </select></div>
  <button class="btn btn-primary" type="submit">Apply</button>
  <a class="btn" href="view.php?type=<?php echo e($type); ?>">Reset</a>
</form>
<div style="margin-left:auto" class="actions">
  <a class="btn" href="view.php?type=<?php echo e($type); ?>&export=csv&date_from=<?php echo e($df); ?>&date_to=<?php echo e($dt); ?>&warehouse_id=<?php echo $wh; ?>&part_id=<?php echo $pt; ?>">Export CSV</a>
  <a class="btn" href="javascript:window.print()">Print</a>
</div>
</div>

<div class="panel"><div class="panel-h"><?php echo e($title); ?> (<?php echo count($rows); ?> rows)</div>
<div class="panel-b" style="padding:0;overflow:auto">
<table class="grid">
  <thead><tr><?php foreach ($headers as $h): ?><th><?php echo e($h); ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
    <?php $i = 0; foreach ($r as $k => $val): ?>
      <td class="<?php echo in_array($i, $numericCols) ? 'num' : ''; ?>">
        <?php
        if (in_array($i, $numericCols)) {
            echo (strpos($k, 'val') !== false || strpos($k, 'price') !== false
                  || strpos($k, 'cost') !== false || strpos($k, 'profit') !== false) ? money($val) : num($val);
        } else {
            echo e($val);
        }
        ?>
      </td>
    <?php $i++; endforeach; ?>
    </tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="'.count($headers).'" class="center">No data</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>