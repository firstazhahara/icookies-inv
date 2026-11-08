<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    require_perm('transfer.approve');
    if (!csrf_validate()) die('Invalid CSRF token.');
    $t = db_one("SELECT * FROM inventory_transfers WHERE id = ?", array($id));
    if (!$t || $t['status'] !== 'PENDING') die('Transfer cannot be approved.');

    $db = db();
    mysqli_begin_transaction($db);
    try {
        $details = db_all("SELECT * FROM inventory_transfer_details WHERE transfer_id = ?", array($id));
        foreach ($details as $d) {
            // Destination batch keeps the SAME batch_code, cost and expiration (row per warehouse)
            $destBatch = add_stock($d['part_id'], (int)$t['destination_warehouse_id'],
                                   $d['batch_code'], (int)$d['qty'], (float)$d['purchase_cost'],
                                   $d['expiration_date'], $d['expiration_date'], null, null,
                                   'TRANSFER_IN', $t['transfer_number'],
                                   'Received from transfer ' . $t['transfer_number']);
            db_exec("UPDATE inventory_transfer_details SET dest_batch_id = ? WHERE id = ?",
                    array($destBatch, (int)$d['id']));
        }
        db_exec("UPDATE inventory_transfers SET status='COMPLETED', approved_by=?, completed_at=NOW()
                 WHERE id=?", array(
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $id));
        mysqli_commit($db);
        audit('APPROVE', 'transfers', $t['transfer_number'], 'Completed');
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Transfer approved and completed.');
        redirect('view.php?id=' . $id);
    } catch (Exception $ex) {
        mysqli_rollback($db);
        die('Approval failed: ' . e($ex->getMessage()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    require_perm('transfer.approve');
    if (!csrf_validate()) die('Invalid CSRF token.');
    $t = db_one("SELECT * FROM inventory_transfers WHERE id = ?", array($id));
    if ($t && $t['status'] === 'PENDING') {
        $db = db();
        mysqli_begin_transaction($db);
        try {
            // Return stock already deducted at creation time
            $details = db_all("SELECT * FROM inventory_transfer_details WHERE transfer_id = ?", array($id));
            foreach ($details as $d) {
                add_stock($d['part_id'], (int)$t['source_warehouse_id'], $d['batch_code'],
                          (int)$d['qty'], (float)$d['purchase_cost'], $d['expiration_date'],
                          $d['expiration_date'], null, null, 'REVERSAL',
                          $t['transfer_number'], 'Cancelled transfer - stock returned');
            }
            db_exec("UPDATE inventory_transfers SET status='CANCELLED' WHERE id=?", array($id));
            mysqli_commit($db);
            $_SESSION['flash'] = array('type'=>'success','msg'=>'Transfer cancelled, stock restored.');
            redirect('view.php?id=' . $id);
        } catch (Exception $ex) {
            mysqli_rollback($db);
            die('Cancel failed: ' . e($ex->getMessage()));
        }
    }
}

$t = db_one("SELECT t.*, ws.warehouse_code src_code, ws.warehouse_name src_name,
                    wd.warehouse_code dst_code, wd.warehouse_name dst_name,
                    u1.full_name created_name, u2.full_name approved_name
             FROM inventory_transfers t
             LEFT JOIN warehouses ws ON ws.id = t.source_warehouse_id
             LEFT JOIN warehouses wd ON wd.id = t.destination_warehouse_id
             LEFT JOIN users u1 ON u1.id = t.created_by
             LEFT JOIN users u2 ON u2.id = t.approved_by
             WHERE t.id = ?", array($id));
if (!$t) die('Transfer not found.');

$details = db_all("SELECT d.*, p.part_code, p.device_model, p.part_category
                   FROM inventory_transfer_details d
                   JOIN master_parts p ON p.id = d.part_id
                   WHERE d.transfer_id = ? ORDER BY d.id", array($id));

$page_title  = 'Transfer ' . $t['transfer_number'];
$breadcrumbs = array('Inventory'=>null,
                     'Transfers'=>base_url().'/modules/transfers/index.php', $t['transfer_number']=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Transfer Details</div><div class="panel-b">
<table class="grid">
  <tr><th style="width:160px">Number</th><td><?php echo e($t['transfer_number']); ?></td>
      <th style="width:160px">Date</th><td><?php echo dmy($t['transfer_date']); ?></td></tr>
  <tr><th>Source</th><td><?php echo e($t['src_code'].' - '.$t['src_name']); ?></td>
      <th>Destination</th><td><?php echo e($t['dst_code'].' - '.$t['dst_name']); ?></td></tr>
  <tr><th>Status</th><td><?php echo e($t['status']); ?></td>
      <th>Created By</th><td><?php echo e($t['created_name']); ?></td></tr>
  <tr><th>Approved By</th><td><?php echo e($t['approved_name']); ?></td>
      <th>Remarks</th><td><?php echo e($t['remarks']); ?></td></tr>
</table>
</div></div>

<div class="panel"><div class="panel-h">Line Items</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part</th><th>Model</th><th>Category</th><th>Batch</th>
    <th class="num">Qty</th><th class="num">Unit Cost</th><th>Expiration</th></tr></thead>
  <tbody>
  <?php foreach ($details as $d): ?>
    <tr><td><?php echo e($d['part_code']); ?></td><td><?php echo e($d['device_model']); ?></td>
        <td><?php echo e($d['part_category']); ?></td><td><?php echo e($d['batch_code']); ?></td>
        <td class="num"><?php echo num($d['qty']); ?></td>
        <td class="num"><?php echo money($d['purchase_cost']); ?></td>
        <td><?php echo dmy($d['expiration_date']); ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>

<?php if ($t['status'] === 'PENDING' && can('transfer.approve')): ?>
<form method="post" action="view.php" style="display:inline">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <input type="hidden" name="action" value="approve">
  <button class="btn btn-success" type="submit"
          data-confirm="Approve and complete this transfer?">Approve &amp; Complete</button>
</form>
<form method="post" action="view.php" style="display:inline">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <input type="hidden" name="action" value="cancel">
  <button class="btn btn-danger" type="submit"
          data-confirm="Cancel this transfer and restore source stock?">Cancel Transfer</button>
</form>
<?php endif; ?>
<a class="btn" href="index.php">&laquo; Back</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>