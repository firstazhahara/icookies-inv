<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    require_perm('disposal.approve');
    if (!csrf_validate()) die('Invalid CSRF token.');
    $h = db_one("SELECT * FROM disposal_headers WHERE id = ?", array($id));
    if (!$h || $h['status'] !== 'PENDING') die('Disposal cannot be approved.');

    $db = db();
    mysqli_begin_transaction($db);
    try {
        $details = db_all("SELECT * FROM disposal_details WHERE disposal_id = ?", array($id));
        foreach ($details as $d) {
            consume_from_batch($d['batch_id'], (int)$d['qty'], 'DISPOSAL',
                               $h['disposal_number'], 'Disposal - ' . $h['reason']);
            $rem = (int) db_val("SELECT qty_remaining FROM inventory_batches WHERE id = ?",
                                array((int)$d['batch_id']));
            if ($rem === 0) {
                db_exec("UPDATE inventory_batches SET status='DISPOSED' WHERE id = ?",
                        array((int)$d['batch_id']));
            }
        }
        db_exec("UPDATE disposal_headers SET status='APPROVED', approved_by=?, approved_at=NOW()
                 WHERE id=?", array(
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $id));
        mysqli_commit($db);
        audit('APPROVE', 'disposal', $h['disposal_number'], 'Disposal approved');
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Disposal approved; stock reduced.');
        redirect('view.php?id=' . $id);
    } catch (Exception $ex) {
        mysqli_rollback($db);
        die('Approval failed: ' . e($ex->getMessage()));
    }
}

$h = db_one("SELECT h.*, w.warehouse_code, u1.full_name req_name, u2.full_name app_name
             FROM disposal_headers h
             LEFT JOIN warehouses w ON w.id = h.warehouse_id
             LEFT JOIN users u1 ON u1.id = h.requested_by
             LEFT JOIN users u2 ON u2.id = h.approved_by
             WHERE h.id = ?", array($id));
if (!$h) die('Disposal not found.');

$details = db_all("SELECT d.*, p.part_code, p.device_model, b.batch_code, b.expiration_date
                   FROM disposal_details d
                   JOIN master_parts p ON p.id = d.part_id
                   JOIN inventory_batches b ON b.id = d.batch_id
                   WHERE d.disposal_id = ?", array($id));

$page_title  = 'Disposal ' . $h['disposal_number'];
$breadcrumbs = array('Inventory'=>null,
                     'Disposal'=>base_url().'/modules/disposal/index.php', $h['disposal_number']=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Disposal Details</div><div class="panel-b">
<table class="grid">
  <tr><th style="width:150px">Number</th><td><?php echo e($h['disposal_number']); ?></td>
      <th style="width:150px">Date</th><td><?php echo dmy($h['disposal_date']); ?></td></tr>
  <tr><th>Warehouse</th><td><?php echo e($h['warehouse_code']); ?></td>
      <th>Reason</th><td><?php echo e($h['reason']); ?></td></tr>
  <tr><th>Status</th><td><?php echo e($h['status']); ?></td>
      <th>Total Value</th><td><?php echo money($h['total_value']); ?></td></tr>
  <tr><th>Requested By</th><td><?php echo e($h['req_name']); ?></td>
      <th>Approved By</th><td><?php echo e($h['app_name']); ?></td></tr>
  <tr><th>Remarks</th><td colspan="3"><?php echo e($h['remarks']); ?></td></tr>
</table>
</div></div>

<div class="panel"><div class="panel-h">Batch Lines</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part</th><th>Model</th><th>Batch</th><th>Expiration</th>
    <th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Total Value</th></tr></thead>
  <tbody>
  <?php foreach ($details as $d): ?>
    <tr><td><?php echo e($d['part_code']); ?></td><td><?php echo e($d['device_model']); ?></td>
        <td><?php echo e($d['batch_code']); ?></td><td><?php echo dmy($d['expiration_date']); ?></td>
        <td class="num"><?php echo num($d['qty']); ?></td>
        <td class="num"><?php echo money($d['purchase_cost']); ?></td>
        <td class="num"><?php echo money($d['total_value']); ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>

<?php if ($h['status'] === 'PENDING' && can('disposal.approve')): ?>
<form method="post" action="view.php" style="display:inline">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <input type="hidden" name="action" value="approve">
  <button class="btn btn-success" type="submit"
          data-confirm="Approve and reduce stock?">Approve Disposal</button>
</form>
<?php endif; ?>
<a class="btn" href="index.php">&laquo; Back</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>