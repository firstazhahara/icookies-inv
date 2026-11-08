<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$h = db_one("SELECT h.*, w.warehouse_code, w.warehouse_name, u.full_name
             FROM inventory_outgoing_headers h
             LEFT JOIN warehouses w ON w.id = h.warehouse_id
             LEFT JOIN users u ON u.id = h.created_by
             WHERE h.id = ?", array($id));
if (!$h) die('Transaction not found.');

$details = db_all("SELECT d.*, p.part_code, p.device_model, p.part_category, b.batch_code,
                          b.expiration_date
                   FROM inventory_outgoing_details d
                   JOIN master_parts p ON p.id = d.part_id
                   LEFT JOIN inventory_batches b ON b.id = d.batch_id
                   WHERE d.outgoing_id = ? ORDER BY d.id", array($id));

$page_title  = 'Outgoing ' . $h['outgoing_number'];
$breadcrumbs = array('Inventory'=>null,
                     'Outgoing'=>base_url().'/modules/outgoing/index.php', $h['outgoing_number']=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Transaction Header</div><div class="panel-b">
<table class="grid">
  <tr><th style="width:150px">Number</th><td><?php echo e($h['outgoing_number']); ?></td>
      <th style="width:150px">Date</th><td><?php echo dmy($h['outgoing_date']); ?></td></tr>
  <tr><th>Warehouse</th><td><?php echo e($h['warehouse_code'].' - '.$h['warehouse_name']); ?></td>
      <th>Type</th><td><?php echo e($h['outgoing_type']); ?></td></tr>
  <tr><th>Reference</th><td><?php echo e($h['customer_reference']); ?></td>
      <th>Created By</th><td><?php echo e($h['full_name']); ?></td></tr>
  <tr><th>Remarks</th><td colspan="3"><?php echo e($h['remarks']); ?></td></tr>
</table>
</div></div>

<div class="panel"><div class="panel-h">Batch Allocations</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part</th><th>Model</th><th>Category</th><th>Batch</th><th>Batch Expiry</th>
    <th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Unit Sell</th>
    <th class="num">Total Cost</th><th class="num">Total Sell</th><th class="num">Gross Profit</th>
    <th>Warranty End</th></tr></thead>
  <tbody>
  <?php foreach ($details as $d): ?>
    <tr><td><?php echo e($d['part_code']); ?></td><td><?php echo e($d['device_model']); ?></td>
        <td><?php echo e($d['part_category']); ?></td><td><?php echo e($d['batch_code']); ?></td>
        <td><?php echo dmy($d['expiration_date']); ?></td>
        <td class="num"><?php echo num($d['qty']); ?></td>
        <td class="num"><?php echo money($d['purchase_cost']); ?></td>
        <td class="num"><?php echo money($d['selling_price']); ?></td>
        <td class="num"><?php echo money($d['total_purchase_cost']); ?></td>
        <td class="num"><?php echo money($d['total_selling_price']); ?></td>
        <td class="num"><?php echo money($d['gross_profit']); ?></td>
        <td><?php echo dmy($d['warranty_end_date']); ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="8">TOTAL</th>
    <th class="num"><?php echo money($h['total_purchase_cost']); ?></th>
    <th class="num"><?php echo money($h['total_selling_price']); ?></th>
    <th class="num"><?php echo money($h['gross_profit']); ?></th><th></th></tr></tfoot>
</table>
</div></div>
<a class="btn" href="index.php">&laquo; Back</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>