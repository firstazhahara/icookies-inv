<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    require_perm('stocktaking.approve');
    if (!csrf_validate()) die('Invalid CSRF token.');
    $s = db_one("SELECT * FROM stocktaking_headers WHERE id = ?", array($id));
    if (!$s || $s['status'] !== 'COUNTED') die('Session cannot be approved.');

    $db = db();
    mysqli_begin_transaction($db);
    try {
        $details = db_all("SELECT * FROM stocktaking_details WHERE stocktaking_id = ? AND variance <> 0",
                          array($id));
        foreach ($details as $d) {
            $v = (int)$d['variance'];
            if ($v < 0) {
                consume_from_batch($d['batch_id'], abs($v), 'STOCK_ADJUSTMENT_OUT',
                                   $s['stocktaking_number'], 'Stocktaking variance adjustment');
            } else {
                $b = db_one("SELECT * FROM inventory_batches WHERE id = ?", array((int)$d['batch_id']));
                add_stock($d['part_id'], (int)$s['warehouse_id'], $b['batch_code'], $v,
                          (float)$b['purchase_cost'], $b['expiration_date'], $b['incoming_date'],
                          $b['supplier_id'], null, 'STOCK_ADJUSTMENT_IN',
                          $s['stocktaking_number'], 'Stocktaking variance adjustment');
            }
        }
        db_exec("UPDATE stocktaking_headers SET status='APPROVED', approved_by=?, approved_at=NOW()
                 WHERE id=?", array(
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $id));
        mysqli_commit($db);
        audit('APPROVE', 'stocktaking', $s['stocktaking_number'], 'Adjustments posted');
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Stocktaking approved; adjustments posted.');
        redirect('view.php?id=' . $id);
    } catch (Exception $ex) {
        mysqli_rollback($db);
        die('Approval failed: ' . e($ex->getMessage()));
    }
}

$s = db_one("SELECT s.*, w.warehouse_code, u1.full_name counted_name, u2.full_name approved_name
             FROM stocktaking_headers s
             LEFT JOIN warehouses w ON w.id = s.warehouse_id
             LEFT JOIN users u1 ON u1.id = s.counted_by
             LEFT JOIN users u2 ON u2.id = s.approved_by
             WHERE s.id = ?", array($id));
if (!$s) die('Session not found.');

$details = db_all("SELECT d.*, p.part_code, p.device_model, b.batch_code
                   FROM stocktaking_details d
                   JOIN master_parts p ON p.id = d.part_id
                   JOIN inventory_batches b ON b.id = d.batch_id
                   WHERE d.stocktaking_id = ? ORDER BY p.part_code", array($id));

$page_title  = 'Stocktaking ' . $s['stocktaking_number'];
$breadcrumbs = array('Inventory'=>null,
                     'Stock Taking'=>base_url().'/modules/stocktaking/index.php',
                     $s['stocktaking_number']=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Session Details</div><div class="panel-b">
<table class="grid">
  <tr><th style="width:150px">Number</th><td><?php echo e($s['stocktaking_number']); ?></td>
      <th style="width:150px">Date</th><td><?php echo dmy($s['stocktaking_date']); ?></td></tr>
  <tr><th>Warehouse</th><td><?php echo e($s['warehouse_code']); ?></td>
      <th>Status</th><td><?php echo e($s['status']); ?></td></tr>
  <tr><th>Counted By</th><td><?php echo e($s['counted_name']); ?></td>
      <th>Approved By</th><td><?php echo e($s['approved_name']); ?></td></tr>
  <tr><th>Remarks</th><td colspan="3"><?php echo e($s['remarks']); ?></td></tr>
</table>
</div></div>

<div class="panel"><div class="panel-h">Count Lines</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part</th><th>Model</th><th>Batch</th><th class="num">System</th>
    <th class="num">Physical</th><th class="num">Variance</th></tr></thead>
  <tbody>
  <?php foreach ($details as $d): ?>
    <tr><td><?php echo e($d['part_code']); ?></td><td><?php echo e($d['device_model']); ?></td>
        <td><?php echo e($d['batch_code']); ?></td>
        <td class="num"><?php echo num($d['system_qty']); ?></td>
        <td class="num"><?php echo num($d['physical_qty']); ?></td>
        <td class="num"><?php echo num($d['variance']); ?></td></tr>
  <?php endforeach; if (!$details) echo '<tr><td colspan="6" class="center">No lines</td></tr>'; ?>
  </tbody>
</table>
</div></div>

<?php if ($s['status'] === 'COUNTED' && can('stocktaking.approve')): ?>
<form method="post" action="view.php" style="display:inline">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <input type="hidden" name="action" value="approve">
  <button class="btn btn-success" type="submit"
          data-confirm="Approve and post adjustments?">Approve Adjustments</button>
</form>
<?php endif; ?>
<?php if ($s['status'] === 'OPEN'): ?>
<a class="btn btn-primary" href="count.php?id=<?php echo (int)$id; ?>">Continue Counting</a>
<?php endif; ?>
<a class="btn" href="index.php">&laquo; Back</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>