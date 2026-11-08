<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('inventory.view');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$part = db_one("SELECT * FROM master_parts WHERE id = ?", array($id));
if (!$part) { die('Part not found.'); }

$batches = db_all("SELECT b.*, w.warehouse_code, w.warehouse_name
                   FROM inventory_batches b JOIN warehouses w ON w.id = b.warehouse_id
                   WHERE b.part_id = ? ORDER BY b.expiration_date", array($id));

$totalQty = 0; $totalValue = 0;
foreach ($batches as $b) { $totalQty += (int)$b['qty_remaining'];
                           $totalValue += (int)$b['qty_remaining'] * (float)$b['purchase_cost']; }

$gp = (float)$part['sell_idr'] - (float)$part['purch_idr'];
$margin = (float)$part['sell_idr'] > 0 ? $gp / (float)$part['sell_idr'] * 100 : 0;
$markup = (float)$part['purch_idr'] > 0 ? $gp / (float)$part['purch_idr'] * 100 : 0;

$page_title  = 'Part Detail - ' . $part['part_code'];
$breadcrumbs = array('Master Data' => null,
                     'Part Master' => base_url().'/modules/master_parts/index.php',
                     $part['part_code'] => null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <div class="panel"><div class="panel-h">Part Information</div><div class="panel-b">
    <table class="grid">
      <tr><th style="width:160px">Part ID</th><td><?php echo e($part['part_code']); ?></td></tr>
      <tr><th>Device Type</th><td><?php echo e($part['device_type']); ?></td></tr>
      <tr><th>Device Model</th><td><?php echo e($part['device_model']); ?></td></tr>
      <tr><th>Category</th><td><?php echo e($part['part_category']); ?></td></tr>
      <tr><th>Variant</th><td><?php echo e($part['part_variant']); ?></td></tr>
      <tr><th>Warranty</th><td><?php echo (int)$part['warranty_month']; ?> month(s)</td></tr>
      <tr><th>Status</th><td><?php echo (int)$part['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?></td></tr>
    </table>
  </div></div>

  <div class="panel"><div class="panel-h">Pricing &amp; Profitability</div><div class="panel-b">
    <table class="grid">
      <tr><th style="width:160px">Purchase Price</th><td class="num"><?php echo money($part['purch_idr']); ?></td></tr>
      <tr><th>Selling Price</th><td class="num"><?php echo money($part['sell_idr']); ?></td></tr>
      <tr><th>Gross Profit / unit</th><td class="num"><?php echo money($gp); ?></td></tr>
      <tr><th>Gross Profit Margin</th><td class="num"><?php echo number_format($margin,2); ?> %</td></tr>
      <tr><th>Markup</th><td class="num"><?php echo number_format($markup,2); ?> %</td></tr>
      <tr><th>Stock on Hand</th><td class="num"><?php echo num($totalQty); ?> unit(s)</td></tr>
      <tr><th>Inventory Value</th><td class="num"><?php echo money($totalValue); ?></td></tr>
    </table>
  </div></div>
</div>

<div class="panel">
  <div class="panel-h">Batch History</div>
  <div class="panel-b" style="padding:0">
  <table class="grid">
    <thead><tr><th>Batch ID</th><th>Warehouse</th><th>Incoming Date</th><th>Expiration</th>
      <th class="num">Received</th><th class="num">Remaining</th><th class="num">Unit Cost</th>
      <th class="num">Value</th><th class="center">Status</th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b):
        $st = expiry_status($b['expiration_date'], $b['qty_remaining'], $b['status']);
        $cls = 'b-normal';
        if ($st === 'NEAR EXPIRY') $cls = 'b-near';
        elseif ($st === 'EXPIRED') $cls = 'b-expired';
        elseif ($st === 'DEPLETED') $cls = 'b-depleted';
        elseif ($st === 'DISPOSED') $cls = 'b-disposed'; ?>
      <tr>
        <td><?php echo e($b['batch_code']); ?></td>
        <td><?php echo e($b['warehouse_code']); ?></td>
        <td><?php echo dmy($b['incoming_date']); ?></td>
        <td><?php echo dmy($b['expiration_date']); ?></td>
        <td class="num"><?php echo num($b['qty_received']); ?></td>
        <td class="num"><?php echo num($b['qty_remaining']); ?></td>
        <td class="num"><?php echo money($b['purchase_cost']); ?></td>
        <td class="num"><?php echo money($b['qty_remaining'] * $b['purchase_cost']); ?></td>
        <td class="center"><span class="badge <?php echo $cls; ?>"><?php echo e($st); ?></span></td>
      </tr>
    <?php endforeach; if (!$batches) echo '<tr><td colspan="9" class="center">No batches</td></tr>'; ?>
    </tbody>
  </table>
  </div>
</div>
<a class="btn" href="index.php">&laquo; Back to list</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>