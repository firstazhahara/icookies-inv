<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$rows = db_all("SELECT d.*, w.warehouse_code,
                       (SELECT COALESCE(SUM(qty),0) FROM disposal_details x WHERE x.disposal_id=d.id) qty
                FROM disposal_headers d
                LEFT JOIN warehouses w ON w.id = d.warehouse_id
                ORDER BY d.id DESC LIMIT 300");
$page_title  = 'Stock Disposal';
$breadcrumbs = array('Inventory'=>null, 'Disposal'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <div><b>Write-off / disposal of stock</b></div>
  <div style="margin-left:auto">
    <?php if (can('disposal.request')): ?>
    <a class="btn btn-success" href="create.php">+ New Request</a>
    <?php endif; ?>
  </div>
</div>
<div class="panel"><div class="panel-h">Disposal Requests</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Number</th><th>Date</th><th>Warehouse</th><th>Reason</th>
    <th class="num">Qty</th><th class="num">Value</th><th class="center">Status</th>
    <th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $cls = 'b-pending';
      if ($r['status'] === 'APPROVED') $cls = 'b-approved';
      elseif ($r['status'] === 'CANCELLED') $cls = 'b-disposed'; ?>
    <tr><td><a href="view.php?id=<?php echo (int)$r['id']; ?>"><?php echo e($r['disposal_number']); ?></a></td>
        <td><?php echo dmy($r['disposal_date']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td>
        <td><?php echo e($r['reason']); ?></td>
        <td class="num"><?php echo num($r['qty']); ?></td>
        <td class="num"><?php echo money($r['total_value']); ?></td>
        <td class="center"><span class="badge <?php echo $cls; ?>"><?php echo e($r['status']); ?></span></td>
        <td class="center"><a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="center">No disposal records</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>