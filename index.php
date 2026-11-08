<?php
require_once dirname(__FILE__) . '/includes/auth.php';
require_login();

$totalParts    = (int) db_val("SELECT COUNT(*) FROM master_parts WHERE is_active = 1");
$totalWh       = (int) db_val("SELECT COUNT(*) FROM warehouses WHERE is_active = 1");
$totalQty      = (int) db_val("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_batches");
$totalValue    = (float) db_val("SELECT COALESCE(SUM(qty_remaining*purchase_cost),0) FROM inventory_batches");
$nearQty       = (int) db_val("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_batches
                               WHERE qty_remaining > 0 AND expiration_date >= CURDATE()
                                 AND DATEDIFF(expiration_date, CURDATE()) <= ?", array(NEAR_EXPIRY_DAYS));
$expiredQty    = (int) db_val("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_batches
                               WHERE qty_remaining > 0 AND expiration_date < CURDATE()");
$outOfStock    = (int) db_val("SELECT COUNT(*) FROM (
                                 SELECT p.id FROM master_parts p
                                 LEFT JOIN inventory_batches b ON b.part_id = p.id
                                 WHERE p.is_active = 1
                                 GROUP BY p.id HAVING COALESCE(SUM(b.qty_remaining),0) = 0) x");

$stockByWh = db_all("SELECT w.warehouse_code, w.warehouse_name,
                            COALESCE(SUM(b.qty_remaining),0) qty,
                            COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) val
                     FROM warehouses w
                     LEFT JOIN inventory_batches b ON b.warehouse_id = w.id
                     GROUP BY w.id ORDER BY w.warehouse_code");

$topIssued = db_all("SELECT p.part_code, p.device_type, p.device_model, p.part_category,
                            COALESCE(SUM(d.qty),0) qty
                     FROM inventory_outgoing_details d
                     JOIN inventory_outgoing_headers h ON h.id = d.outgoing_id
                     JOIN master_parts p ON p.id = d.part_id
                     WHERE h.status = 'POSTED'
                     GROUP BY p.id ORDER BY qty DESC LIMIT 10");

$nearList = db_all("SELECT b.batch_code, p.part_code, b.expiration_date, b.qty_remaining,
                           w.warehouse_code, DATEDIFF(b.expiration_date, CURDATE()) days
                    FROM inventory_batches b
                    JOIN master_parts p ON p.id = b.part_id
                    JOIN warehouses w ON w.id = b.warehouse_id
                    WHERE b.qty_remaining > 0 AND b.expiration_date >= CURDATE()
                      AND DATEDIFF(b.expiration_date, CURDATE()) <= ?
                    ORDER BY b.expiration_date ASC LIMIT 10", array(NEAR_EXPIRY_DAYS));

$expiredList = db_all("SELECT b.batch_code, p.part_code, b.expiration_date, b.qty_remaining,
                              w.warehouse_code
                       FROM inventory_batches b
                       JOIN master_parts p ON p.id = b.part_id
                       JOIN warehouses w ON w.id = b.warehouse_id
                       WHERE b.qty_remaining > 0 AND b.expiration_date < CURDATE()
                       ORDER BY b.expiration_date ASC LIMIT 10");

$recentIn  = db_all("SELECT id, incoming_number, incoming_date, total_cost, status
                     FROM inventory_incoming_headers ORDER BY id DESC LIMIT 6");
$recentOut = db_all("SELECT id, outgoing_number, outgoing_date, outgoing_type,
                            total_selling_price, status
                     FROM inventory_outgoing_headers ORDER BY id DESC LIMIT 6");
$recentTr  = db_all("SELECT id, transfer_number, transfer_date, status
                     FROM inventory_transfers ORDER BY id DESC LIMIT 6");
$recentSt  = db_all("SELECT id, stocktaking_number, stocktaking_date, status
                     FROM stocktaking_headers ORDER BY id DESC LIMIT 6");
$monthly = db_all("SELECT DATE_FORMAT(trx_date,'%Y-%m') ym,
                          SUM(qty_in) tin, SUM(qty_out) tout
                   FROM inventory_transactions
                   WHERE trx_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                   GROUP BY ym ORDER BY ym");

$page_title  = 'Dashboard';
$breadcrumbs = array('Dashboard' => null);
require dirname(__FILE__) . '/includes/header.php';
?>
<div class="kpis">
  <div class="kpi"><div class="kl">Active Parts</div><div class="kv"><?php echo num($totalParts); ?></div></div>
  <div class="kpi"><div class="kl">Warehouses</div><div class="kv"><?php echo num($totalWh); ?></div></div>
  <div class="kpi"><div class="kl">Total Stock Qty</div><div class="kv"><?php echo num($totalQty); ?></div></div>
  <div class="kpi"><div class="kl">Inventory Value</div><div class="kv small"><?php echo money($totalValue); ?></div></div>
  <div class="kpi"><div class="kl">Near Expiry Qty</div><div class="kv"><?php echo num($nearQty); ?></div></div>
  <div class="kpi"><div class="kl">Expired Qty</div><div class="kv"><?php echo num($expiredQty); ?></div></div>
  <div class="kpi"><div class="kl">Out of Stock</div><div class="kv"><?php echo num($outOfStock); ?></div></div>
</div>

<div class="panel">
  <div class="panel-h">Stock by Warehouse</div>
  <div class="panel-b" style="padding:0">
    <table class="grid">
      <thead><tr><th>Code</th><th>Warehouse</th><th class="num">Quantity</th><th class="num">Value</th></tr></thead>
      <tbody>
      <?php foreach ($stockByWh as $r): ?>
        <tr><td><?php echo e($r['warehouse_code']); ?></td>
            <td><?php echo e($r['warehouse_name']); ?></td>
            <td class="num"><?php echo num($r['qty']); ?></td>
            <td class="num"><?php echo money($r['val']); ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <div class="panel">
    <div class="panel-h">Top 10 Most Issued Parts</div>
    <div class="panel-b" style="padding:0">
      <table class="grid"><thead><tr><th>Part</th><th>Model</th><th>Category</th><th class="num">Qty</th></tr></thead>
      <tbody>
      <?php foreach ($topIssued as $r): ?>
        <tr><td><?php echo e($r['part_code']); ?></td><td><?php echo e($r['device_model']); ?></td>
            <td><?php echo e($r['part_category']); ?></td><td class="num"><?php echo num($r['qty']); ?></td></tr>
      <?php endforeach; if (!$topIssued) echo '<tr><td colspan="4" class="center">No data</td></tr>'; ?>
      </tbody></table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-h">Near Expiry (next <?php echo (int)NEAR_EXPIRY_DAYS; ?> days)</div>
    <div class="panel-b" style="padding:0">
      <table class="grid"><thead><tr><th>Batch</th><th>Part</th><th>WH</th><th>Expiry</th><th class="num">Days</th><th class="num">Qty</th></tr></thead>
      <tbody>
      <?php foreach ($nearList as $r): ?>
        <tr><td><?php echo e($r['batch_code']); ?></td><td><?php echo e($r['part_code']); ?></td>
            <td><?php echo e($r['warehouse_code']); ?></td><td><?php echo dmy($r['expiration_date']); ?></td>
            <td class="num"><?php echo (int)$r['days']; ?></td><td class="num"><?php echo num($r['qty_remaining']); ?></td></tr>
      <?php endforeach; if (!$nearList) echo '<tr><td colspan="6" class="center">None</td></tr>'; ?>
      </tbody></table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-h">Expired Stock (still on hand)</div>
    <div class="panel-b" style="padding:0">
      <table class="grid"><thead><tr><th>Batch</th><th>Part</th><th>WH</th><th>Expiry</th><th class="num">Qty</th></tr></thead>
      <tbody>
      <?php foreach ($expiredList as $r): ?>
        <tr><td><?php echo e($r['batch_code']); ?></td><td><?php echo e($r['part_code']); ?></td>
            <td><?php echo e($r['warehouse_code']); ?></td><td><?php echo dmy($r['expiration_date']); ?></td>
            <td class="num"><?php echo num($r['qty_remaining']); ?></td></tr>
      <?php endforeach; if (!$expiredList) echo '<tr><td colspan="5" class="center">None</td></tr>'; ?>
      </tbody></table>
    </div>
  </div>

  <div class="panel">
    <div class="panel-h">Monthly Inventory Movement</div>
    <div class="panel-b" style="padding:0">
      <table class="grid"><thead><tr><th>Month</th><th class="num">Qty In</th><th class="num">Qty Out</th></tr></thead>
      <tbody>
      <?php foreach ($monthly as $r): ?>
        <tr><td><?php echo e($r['ym']); ?></td><td class="num"><?php echo num($r['tin']); ?></td>
            <td class="num"><?php echo num($r['tout']); ?></td></tr>
      <?php endforeach; if (!$monthly) echo '<tr><td colspan="3" class="center">No data</td></tr>'; ?>
      </tbody></table>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
  <?php
  $blocks = array(
    'Recent Incoming' => array($recentIn,  array('incoming_number','incoming_date','total_cost'),
                               array('Number','Date','Total'), 'modules/incoming/view.php?id='),
    'Recent Outgoing' => array($recentOut, array('outgoing_number','outgoing_date','total_selling_price'),
                               array('Number','Date','Total'), 'modules/outgoing/view.php?id='),
    'Recent Transfers'=> array($recentTr,  array('transfer_number','transfer_date','status'),
                               array('Number','Date','Status'), 'modules/transfers/view.php?id='),
    'Recent Stocktaking' => array($recentSt, array('stocktaking_number','stocktaking_date','status'),
                               array('Number','Date','Status'), 'modules/stocktaking/view.php?id=')
  );
  foreach ($blocks as $title => $cfg):
      list($rows, $cols, $heads, $link) = $cfg; ?>
  <div class="panel">
    <div class="panel-h"><?php echo e($title); ?></div>
    <div class="panel-b" style="padding:0">
      <table class="grid"><thead><tr>
        <?php foreach ($heads as $h): ?><th><?php echo e($h); ?></th><?php endforeach; ?>
      </tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?php echo base_url() . '/' . $link . (int)$r['id']; ?>"><?php echo e($r[$cols[0]]); ?></a></td>
          <td><?php echo dmy($r[$cols[1]]); ?></td>
          <td><?php echo is_numeric($r[$cols[2]]) ? money($r[$cols[2]]) : e($r[$cols[2]]); ?></td>
        </tr>
      <?php endforeach; if (!$rows) echo '<tr><td colspan="3" class="center">None</td></tr>'; ?>
      </tbody></table>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php require dirname(__FILE__) . '/includes/footer.php'; ?>