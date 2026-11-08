<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$rows = db_all("SELECT t.*, ws.warehouse_code src_code, wd.warehouse_code dst_code,
                       (SELECT COALESCE(SUM(qty),0) FROM inventory_transfer_details d
                        WHERE d.transfer_id=t.id) qty
                FROM inventory_transfers t
                LEFT JOIN warehouses ws ON ws.id = t.source_warehouse_id
                LEFT JOIN warehouses wd ON wd.id = t.destination_warehouse_id
                ORDER BY t.id DESC LIMIT 300");

$page_title  = 'Stock Transfers';
$breadcrumbs = array('Inventory'=>null, 'Stock Transfer'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <div><b>Inter-warehouse stock movement</b></div>
  <div style="margin-left:auto">
    <?php if (can('transfer.create')): ?>
    <a class="btn btn-success" href="create.php">+ New Transfer</a>
    <?php endif; ?>
  </div>
</div>
<div class="panel"><div class="panel-h">Transfers</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Number</th><th>Date</th><th>Source</th><th>Destination</th>
    <th class="num">Qty</th><th class="center">Status</th><th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $cls = 'b-pending';
      if ($r['status'] === 'COMPLETED') $cls = 'b-approved';
      elseif ($r['status'] === 'CANCELLED') $cls = 'b-disposed'; ?>
    <tr><td><a href="view.php?id=<?php echo (int)$r['id']; ?>"><?php echo e($r['transfer_number']); ?></a></td>
        <td><?php echo dmy($r['transfer_date']); ?></td>
        <td><?php echo e($r['src_code']); ?></td><td><?php echo e($r['dst_code']); ?></td>
        <td class="num"><?php echo num($r['qty']); ?></td>
        <td class="center"><span class="badge <?php echo $cls; ?>"><?php echo e($r['status']); ?></span></td>
        <td class="center"><a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="center">No transfers</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>