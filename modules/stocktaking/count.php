<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('stocktaking.count');

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$s = db_one("SELECT s.*, w.warehouse_code FROM stocktaking_headers s
             LEFT JOIN warehouses w ON w.id = s.warehouse_id WHERE s.id = ?", array($id));
if (!$s) die('Session not found.');
if ($s['status'] !== 'OPEN') die('Session is no longer open.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $db = db();
    mysqli_begin_transaction($db);
    try {
        $counts = isset($_POST['physical']) ? $_POST['physical'] : array();
        foreach ($counts as $detailId => $phys) {
            $detailId = (int)$detailId;
            $phys = (int)$phys;
            $d = db_one("SELECT system_qty FROM stocktaking_details WHERE id = ? AND stocktaking_id = ?",
                        array($detailId, $id));
            if (!$d) continue;
            db_exec("UPDATE stocktaking_details SET physical_qty = ?, variance = ? WHERE id = ?",
                    array($phys, $phys - (int)$d['system_qty'], $detailId));
        }
        db_exec("UPDATE stocktaking_headers SET status = 'COUNTED' WHERE id = ?", array($id));
        mysqli_commit($db);
        audit('COUNT', 'stocktaking', $s['stocktaking_number'], 'Physical count saved');
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Count saved. Awaiting supervisor approval.');
        redirect('view.php?id=' . $id);
    } catch (Exception $ex) {
        mysqli_rollback($db);
        die('Save failed: ' . e($ex->getMessage()));
    }
}

$details = db_all("SELECT d.*, p.part_code, p.device_model, p.part_category, b.batch_code, b.expiration_date
                   FROM stocktaking_details d
                   JOIN master_parts p ON p.id = d.part_id
                   JOIN inventory_batches b ON b.id = d.batch_id
                   WHERE d.stocktaking_id = ? ORDER BY p.part_code", array($id));

$page_title  = 'Count ' . $s['stocktaking_number'];
$breadcrumbs = array('Inventory'=>null,
                     'Stock Taking'=>base_url().'/modules/stocktaking/index.php',
                     $s['stocktaking_number']=>null, 'Count'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Session</div><div class="panel-b">
  <b>Number:</b> <?php echo e($s['stocktaking_number']); ?> &nbsp;|&nbsp;
  <b>Warehouse:</b> <?php echo e($s['warehouse_code']); ?> &nbsp;|&nbsp;
  <b>Date:</b> <?php echo dmy($s['stocktaking_date']); ?>
</div></div>

<form method="post" action="count.php">
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
<div class="panel"><div class="panel-h">Enter Physical Quantities</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part</th><th>Model</th><th>Batch</th><th>Expiry</th>
    <th class="num">System Qty</th><th style="width:130px">Physical Qty</th></tr></thead>
  <tbody>
  <?php foreach ($details as $d): ?>
    <tr><td><?php echo e($d['part_code']); ?></td><td><?php echo e($d['device_model']); ?></td>
        <td><?php echo e($d['batch_code']); ?></td><td><?php echo dmy($d['expiration_date']); ?></td>
        <td class="num"><?php echo num($d['system_qty']); ?></td>
        <td><input type="number" name="physical[<?php echo (int)$d['id']; ?>]"
                   value="<?php echo (int)$d['physical_qty']; ?>" min="0" style="width:100%"></td></tr>
  <?php endforeach; if (!$details) echo '<tr><td colspan="6" class="center">No batches to count</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<button class="btn btn-primary" type="submit">Save Count</button>
<a class="btn" href="view.php?id=<?php echo (int)$id; ?>">Cancel</a>
</form>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>