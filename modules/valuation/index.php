<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$wh = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$warehouses = db_all("SELECT * FROM warehouses ORDER BY warehouse_code");
$filter = ""; $params = array();
if ($wh) { $filter = " AND b.warehouse_id = ?"; $params[] = $wh; }

$totQty   = (int) db_val("SELECT COALESCE(SUM(b.qty_remaining),0) FROM inventory_batches b
                          WHERE 1=1" . $filter, $params);
$totCost  = (float) db_val("SELECT COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) FROM inventory_batches b
                            WHERE 1=1" . $filter, $params);
$totSell  = (float) db_val("SELECT COALESCE(SUM(b.qty_remaining*p.sell_idr),0)
                            FROM inventory_batches b JOIN master_parts p ON p.id=b.part_id
                            WHERE 1=1" . $filter, $params);
$expValue = (float) db_val("SELECT COALESCE(SUM(b.qty_remaining*b.purchase_cost),0)
                            FROM inventory_batches b
                            WHERE b.expiration_date < CURDATE()" . $filter, $params);
$nearValue= (float) db_val("SELECT COALESCE(SUM(b.qty_remaining*b.purchase_cost),0)
                            FROM inventory_batches b
                            WHERE b.expiration_date >= CURDATE()
                              AND DATEDIFF(b.expiration_date, CURDATE()) <= ?" . $filter,
                            array_merge(array(NEAR_EXPIRY_DAYS), $params));

$byWarehouse = db_all("SELECT w.warehouse_code, w.warehouse_name,
                              COALESCE(SUM(b.qty_remaining),0) qty,
                              COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) cost,
                              COALESCE(SUM(b.qty_remaining*p.sell_idr),0) sell
                       FROM warehouses w
                       LEFT JOIN inventory_batches b ON b.warehouse_id = w.id
                       LEFT JOIN master_parts p ON p.id = b.part_id
                       GROUP BY w.id ORDER BY w.warehouse_code");

$byPart = db_all("SELECT p.part_code, p.device_type, p.device_model, p.part_category,
                         COALESCE(SUM(b.qty_remaining),0) qty,
                         COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) cost,
                         COALESCE(SUM(b.qty_remaining*p.sell_idr),0) sell
                  FROM master_parts p
                  LEFT JOIN inventory_batches b ON b.part_id = p.id" . ($wh ? " AND b.warehouse_id = ?" : "") . "
                  GROUP BY p.id HAVING qty > 0 ORDER BY p.part_code", $wh ? array($wh) : array());

$byBatch = db_all("SELECT b.batch_code, p.part_code, w.warehouse_code, b.expiration_date,
                          b.qty_remaining, b.purchase_cost,
                          (b.qty_remaining*b.purchase_cost) value
                   FROM inventory_batches b
                   JOIN master_parts p ON p.id = b.part_id
                   JOIN warehouses w ON w.id = b.warehouse_id
                   WHERE b.qty_remaining > 0" . $filter . "
                   ORDER BY b.expiration_date", $params);

$page_title  = 'Inventory Valuation';
$breadcrumbs = array('Monitoring'=>null, 'Inventory Valuation'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <form method="get" action="index.php" style="display:flex;gap:8px;align-items:flex-end">
    <div class="fld"><label>Warehouse</label>
      <select name="warehouse_id"><option value="0">All</option>
      <?php foreach ($warehouses as $w): ?>
        <option value="<?php echo (int)$w['id']; ?>" <?php if ($wh==$w['id']) echo 'selected'; ?>>
          <?php echo e($w['warehouse_code']); ?></option>
      <?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Apply</button>
  </form>
</div>

<div class="kpis" style="grid-template-columns:repeat(5,1fr)">
  <div class="kpi"><div class="kl">Total Qty</div><div class="kv"><?php echo num($totQty); ?></div></div>
  <div class="kpi"><div class="kl">Purchase Value</div><div class="kv small"><?php echo money($totCost); ?></div></div>
  <div class="kpi"><div class="kl">Potential Sales</div><div class="kv small"><?php echo money($totSell); ?></div></div>
  <div class="kpi"><div class="kl">Potential GP</div><div class="kv small"><?php echo money($totSell - $totCost); ?></div></div>
  <div class="kpi"><div class="kl">Expired Value</div><div class="kv small"><?php echo money($expValue); ?></div></div>
</div>
<div class="kpis" style="grid-template-columns:repeat(5,1fr)">
  <div class="kpi"><div class="kl">Near Expiry Value</div><div class="kv small"><?php echo money($nearValue); ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <div class="panel"><div class="panel-h">Warehouse-Level Value</div><div class="panel-b" style="padding:0">
  <table class="grid"><thead><tr><th>Code</th><th>Name</th><th class="num">Qty</th>
    <th class="num">Cost Value</th><th class="num">Sales Value</th></tr></thead><tbody>
  <?php foreach ($byWarehouse as $r): ?>
    <tr><td><?php echo e($r['warehouse_code']); ?></td><td><?php echo e($r['warehouse_name']); ?></td>
        <td class="num"><?php echo num($r['qty']); ?></td>
        <td class="num"><?php echo money($r['cost']); ?></td>
        <td class="num"><?php echo money($r['sell']); ?></td></tr>
  <?php endforeach; ?></tbody></table>
  </div></div>

  <div class="panel"><div class="panel-h">Part-Level Value</div><div class="panel-b" style="padding:0;max-height:420px;overflow:auto">
  <table class="grid"><thead><tr><th>Part</th><th>Model</th><th class="num">Qty</th>
    <th class="num">Cost Value</th><th class="num">Sales Value</th></tr></thead><tbody>
  <?php foreach ($byPart as $r): ?>
    <tr><td><?php echo e($r['part_code']); ?></td><td><?php echo e($r['device_model']); ?></td>
        <td class="num"><?php echo num($r['qty']); ?></td>
        <td class="num"><?php echo money($r['cost']); ?></td>
        <td class="num"><?php echo money($r['sell']); ?></td></tr>
  <?php endforeach; ?></tbody></table>
  </div></div>
</div>

<div class="panel"><div class="panel-h">Batch-Level Value</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Batch</th><th>Part</th><th>Warehouse</th><th>Expiration</th>
    <th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Value</th>
    <th class="center">Status</th></tr></thead>
  <tbody>
  <?php foreach ($byBatch as $r):
      $st = expiry_status($r['expiration_date'], $r['qty_remaining'], 'ACTIVE');
      $cls = ($st === 'EXPIRED') ? 'b-expired' : (($st === 'NEAR EXPIRY') ? 'b-near' : 'b-normal'); ?>
    <tr><td><?php echo e($r['batch_code']); ?></td><td><?php echo e($r['part_code']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td><td><?php echo dmy($r['expiration_date']); ?></td>
        <td class="num"><?php echo num($r['qty_remaining']); ?></td>
        <td class="num"><?php echo money($r['purchase_cost']); ?></td>
        <td class="num"><?php echo money($r['value']); ?></td>
        <td class="center"><span class="badge <?php echo $cls; ?>"><?php echo e($st); ?></span></td></tr>
  <?php endforeach; if (!$byBatch) echo '<tr><td colspan="8" class="center">No batches</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>