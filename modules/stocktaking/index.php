<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$rows = db_all("SELECT s.*, w.warehouse_code,
                       (SELECT COUNT(*) FROM stocktaking_details d
                        WHERE d.stocktaking_id = s.id) AS line_count,
                       (SELECT COALESCE(SUM(ABS(d.variance)),0) FROM stocktaking_details d
                        WHERE d.stocktaking_id = s.id) AS variance
                FROM stocktaking_headers s
                LEFT JOIN warehouses w ON w.id = s.warehouse_id
                ORDER BY s.id DESC LIMIT 300");
$page_title  = 'Stock Taking';
$breadcrumbs = array('Inventory'=>null, 'Stock Taking'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <div><b>Physical inventory counting</b></div>
  <div style="margin-left:auto">
    <?php if (can('stocktaking.create')): ?>
    <a class="btn btn-success" href="create.php">+ New Session</a>
    <?php endif; ?>
  </div>
</div>
<div class="panel"><div class="panel-h">Sessions</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Number</th><th>Date</th><th>Warehouse</th><th class="num">Lines</th>
    <th class="num">Variance</th><th class="center">Status</th><th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $cls = 'b-open';
      if ($r['status'] === 'APPROVED') $cls = 'b-approved';
      elseif ($r['status'] === 'CANCELLED') $cls = 'b-disposed'; ?>
    <tr><td><a href="view.php?id=<?php echo (int)$r['id']; ?>"><?php echo e($r['stocktaking_number']); ?></a></td>
        <td><?php echo dmy($r['stocktaking_date']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td>
        <td class="num"><?php echo num($r['line_count']); ?></td>
        <td class="num"><?php echo num($r['variance']); ?></td>
        <td class="center"><span class="badge <?php echo $cls; ?>"><?php echo e($r['status']); ?></span></td>
        <td class="center">
          <a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a>
          <?php if ($r['status'] === 'OPEN'): ?>
          <a class="btn btn-sm" href="count.php?id=<?php echo (int)$r['id']; ?>">Count</a>
          <?php endif; ?>
        </td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="center">No sessions</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>