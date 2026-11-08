<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reverse') {
    require_perm('*');
    if (!csrf_validate()) die('Invalid CSRF token.');
    $h = db_one("SELECT * FROM inventory_incoming_headers WHERE id = ?", array($id));
    if (!$h || $h['status'] !== 'POSTED') { die('Receipt cannot be reversed.'); }
    $db = db();
    mysqli_begin_transaction($db);
    try {
        $details = db_all("SELECT * FROM inventory_incoming_details WHERE incoming_id = ?", array($id));
        foreach ($details as $d) {
            consume_from_batch($d['batch_id'], (int)$d['qty_received'], 'REVERSAL',
                               $h['incoming_number'], 'Reversal of incoming receipt');
        }
        db_exec("UPDATE inventory_incoming_headers SET status='REVERSED', reversed_at=NOW() WHERE id=?",
                array($id));
        mysqli_commit($db);
        audit('REVERSE', 'incoming', $h['incoming_number'], 'Reversed');
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Receipt reversed.');
        redirect('view.php?id=' . $id);
    } catch (Exception $ex) {
        mysqli_rollback($db);
        die('Reversal failed: ' . e($ex->getMessage()));
    }
}

$h = db_one("SELECT h.*, w.warehouse_code, w.warehouse_name, s.supplier_name, u.full_name
             FROM inventory_incoming_headers h
             LEFT JOIN warehouses w ON w.id = h.warehouse_id
             LEFT JOIN suppliers s ON s.id = h.supplier_id
             LEFT JOIN users u ON u.id = h.created_by
             WHERE h.id = ?", array($id));
if (!$h) die('Receipt not found.');

$details = db_all("SELECT d.*, p.part_code, p.device_type, p.device_model, p.part_category,
                          b.batch_code, b.qty_remaining
                   FROM inventory_incoming_details d
                   JOIN master_parts p ON p.id = d.part_id
                   LEFT JOIN inventory_batches b ON b.id = d.batch_id
                   WHERE d.incoming_id = ? ORDER BY d.id", array($id));

$page_title  = 'Receipt ' . $h['incoming_number'];
$breadcrumbs = array('Inventory'=>null,
                     'Incoming'=>base_url().'/modules/incoming/index.php', $h['incoming_number']=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Receipt Header</div><div class="panel-b">
<table class="grid" style="width:100%">
  <tr><th style="width:150px">Number</th><td><?php echo e($h['incoming_number']); ?></td>
      <th style="width:150px">Date</th><td><?php echo dmy($h['incoming_date']); ?></td></tr>
  <tr><th>Warehouse</th><td><?php echo e($h['warehouse_code'].' - '.$h['warehouse_name']); ?></td>
      <th>Supplier</th><td><?php echo e($h['supplier_name']); ?></td></tr>
  <tr><th>Created By</th><td><?php echo e($h['full_name']); ?></td>
      <th>Status</th><td><?php echo e($h['status']); ?></td></tr>
  <tr><th>Remarks</th><td colspan="3"><?php echo e($h['remarks']); ?></td></tr>
</table>
</div></div>

<div class="panel"><div class="panel-h">Line Items / Batches</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part</th><th>Model</th><th>Category</th><th>Batch ID</th>
    <th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Total</th>
    <th>Expiration</th><th class="num">Remaining</th></tr></thead>
  <tbody>
  <?php foreach ($details as $d): ?>
    <tr><td><?php echo e($d['part_code']); ?></td><td><?php echo e($d['device_model']); ?></td>
        <td><?php echo e($d['part_category']); ?></td>
        <td><?php echo e($d['batch_code']); ?></td>
        <td class="num"><?php echo num($d['qty_received']); ?></td>
        <td class="num"><?php echo money($d['purchase_price']); ?></td>
        <td class="num"><?php echo money($d['total_cost']); ?></td>
        <td><?php echo dmy($d['expiration_date']); ?></td>
        <td class="num"><?php echo num($d['qty_remaining']); ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="6">TOTAL</th>
    <th class="num"><?php echo money($h['total_cost']); ?></th><th colspan="2"></th></tr></tfoot>
</table>
</div></div>
<a class="btn" href="index.php">&laquo; Back</a>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>