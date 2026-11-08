<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$wh = db_one("SELECT * FROM warehouses WHERE id = ?", array($id));
if (!$wh) die('Warehouse not found.');

$rows = db_all("SELECT p.part_code, p.device_type, p.device_model, p.part_category,
                       COALESCE(SUM(b.qty_remaining),0) qty,
                       COALESCE(SUM(b.qty_remaining*b.purchase_cost),0) val
                FROM master_parts p
                LEFT JOIN inventory_batches b ON b.part_id = p.id AND b.warehouse_id = ?
                GROUP BY p.id HAVING qty > 0 ORDER BY p.part_code", array($id));

$page_title  = 'Warehouse - ' . $wh['warehouse_code'];
$breadcrumbs = array('Master Data'=>null,
                     'Warehouses'=>base_url().'/modules/warehouses/index.php', $wh['warehouse_code']=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Warehouse Information</div><div class="panel-b">
<table class="grid" style="width:600px">
  <tr><th style="width:160px">Code</th><td><?php echo e($wh['warehouse_code']); ?></td></tr>
  <tr><th>Name</th><td><?php echo e($wh['warehouse_name']); ?></td></tr>
  <tr><th>Address</th><td><?php echo e($wh['warehouse_address']); ?></td></tr>
  <tr><th>Description</th><td><?php echo e($wh['description']); ?></td></tr>
  <tr><th>Status</th><td><?php echo (int)$wh['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?></td></tr>
</table>
</div></div>

<div class="panel"><div class="panel-h">Stock Summary</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part ID</th><th>Device Type</th><th>Model</th><th>Category</th>
    <th class="num">Qty</th><th class="num">Value</th></tr></thead>
  <tbody>
  <?php $tq = 0; $tv = 0;
  foreach ($rows as $r): $tq += (int)$r['qty']; $tv += (float)$r['val']; ?>
    <tr><td><?php echo e($r['part_code']); ?></td><td><?php echo e($r['device_type']); ?></td>
        <td><?php echo e($r['device_model']); ?></td><td><?php echo e($r['part_category']); ?></td>
        <td class="num"><?php echo num($r['qty']); ?></td>
        <td class="num"><?php echo money($r['val']); ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="center">No stock</td></tr>'; ?>
  </tbody>
  <tfoot><tr><th colspan="4">TOTAL</th>
    <th class="num"><?php echo num($tq); ?></th>
    <th class="num"><?php echo money($tv); ?></th></tr></tfoot>
</table>
</div></div>
<a class="btn" href="index.php">&laquo; Back</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>