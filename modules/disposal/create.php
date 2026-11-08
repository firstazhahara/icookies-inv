<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('disposal.request');

$warehouses = db_all("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY warehouse_code");
$parts      = db_all("SELECT * FROM master_parts WHERE is_active = 1 ORDER BY part_code");
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $wh = (int)$_POST['warehouse_id'];
    $date = $_POST['disposal_date'];
    $reason = $_POST['reason'];
    if ($wh <= 0) $errors[] = 'Warehouse is required.';
    if ($date === '') $errors[] = 'Date is required.';

    $clean = array();
    foreach ((isset($_POST['items']) ? $_POST['items'] : array()) as $it) {
        $bid = (int)$it['batch_id']; $qty = (int)$it['qty'];
        if ($bid > 0 && $qty > 0) $clean[] = array('batch_id'=>$bid, 'qty'=>$qty);
    }
    if (!$clean) $errors[] = 'No valid line items.';

    if (!$errors) {
        $number = next_number('DP', 'disposal_headers', 'disposal_number');
        $db = db();
        mysqli_begin_transaction($db);
        try {
            $did = db_exec("INSERT INTO disposal_headers
                (disposal_number, disposal_date, warehouse_id, reason, remarks, status, requested_by)
                VALUES (?,?,?,?,?,'PENDING',?)",
                array($number, $date, $wh, $reason, trim($_POST['remarks']),
                      isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null));

            $total = 0;
            foreach ($clean as $it) {
                $b = db_one("SELECT * FROM inventory_batches WHERE id = ? AND warehouse_id = ?",
                            array($it['batch_id'], $wh));
                if (!$b) throw new Exception('Batch not found in this warehouse.');
                if ((int)$b['qty_remaining'] < $it['qty']) {
                    throw new Exception('Disposal qty exceeds remaining for batch ' . $b['batch_code']);
                }
                $val = $it['qty'] * (float)$b['purchase_cost'];
                $total += $val;
                db_exec("INSERT INTO disposal_details
                    (disposal_id, part_id, batch_id, qty, purchase_cost, total_value)
                    VALUES (?,?,?,?,?,?)",
                    array($did, (int)$b['part_id'], $it['batch_id'], $it['qty'],
                          (float)$b['purchase_cost'], $val));
            }
            db_exec("UPDATE disposal_headers SET total_value = ? WHERE id = ?", array($total, $did));
            mysqli_commit($db);
            audit('CREATE', 'disposal', $number, 'Disposal requested, value ' . money($total));
            $_SESSION['flash'] = array('type'=>'success','msg'=>'Disposal request submitted for approval.');
            redirect('view.php?id=' . $did);
        } catch (Exception $ex) {
            mysqli_rollback($db);
            $errors[] = $ex->getMessage();
        }
    }
}
$page_title  = 'New Disposal Request';
$breadcrumbs = array('Inventory'=>null,
                     'Disposal'=>base_url().'/modules/disposal/index.php', 'New'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?php echo e($er); ?></div><?php endforeach; ?>
<form method="post" action="create.php">
<?php echo csrf_field(); ?>
<div class="panel"><div class="panel-h">Disposal Header</div><div class="panel-b">
<div class="formgrid3">
  <div class="formrow"><label>Date <span class="req">*</span></label>
    <input type="date" name="disposal_date" value="<?php echo e(date('Y-m-d')); ?>"></div>
  <div class="formrow"><label>Warehouse <span class="req">*</span></label>
    <select name="warehouse_id"><option value="">-- Select --</option>
    <?php foreach ($warehouses as $w): ?>
      <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['warehouse_code'].' - '.$w['warehouse_name']); ?></option>
    <?php endforeach; ?></select></div>
  <div class="formrow"><label>Reason</label>
    <select name="reason">
      <option value="EXPIRED">EXPIRED</option><option value="DAMAGED">DAMAGED</option>
      <option value="DEFECTIVE">DEFECTIVE</option><option value="OBSOLETE">OBSOLETE</option>
      <option value="LOST">LOST</option><option value="OTHER">OTHER</option>
    </select></div>
</div>
<div class="formrow"><label>Remarks</label><input type="text" name="remarks"></div>
<div class="flash flash-info">Approval is required before stock is reduced.</div>
</div></div>

<div class="panel"><div class="panel-h">Batch Lines</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th style="width:62%">Batch (Part / Warehouse / Expiry)</th>
    <th style="width:15%">Qty</th><th style="width:15%">Del</th></tr></thead>
  <tbody id="itemRows">
    <tr>
      <td>
        <?php
        // Batches are filtered by warehouse in JS; listing all active batches with remaining stock
        $batches = db_all("SELECT b.id, b.batch_code, b.expiration_date, b.qty_remaining,
                          b.warehouse_id, p.part_code, p.device_model,
                          w.warehouse_code
                   FROM inventory_batches b
                   JOIN master_parts p ON p.id = b.part_id
                   JOIN warehouses w ON w.id = b.warehouse_id
                   WHERE b.qty_remaining > 0 AND b.status = 'ACTIVE'
                   ORDER BY b.expiration_date"); ?>
        <select name="items[0][batch_id]" class="batch-select" data-wh="" style="width:100%">
          <option value="">-- Select Batch --</option>
          <?php foreach ($batches as $b): ?>
            <option value="<?php echo (int)$b['id']; ?>"
                    data-wh="<?php echo (int)$b['warehouse_id']; ?>">
              <?php echo e($b['part_code'].' | '.$b['batch_code'].' | '.$b['warehouse_code']
                          .' | exp '.dmy($b['expiration_date']).' | rem '.$b['qty_remaining']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input type="number" name="items[0][qty]" min="1" value="1" style="width:100%"></td>
      <td class="center"><button type="button" class="btn btn-sm btn-danger"
            onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
    </tr>
  </tbody>
</table>
<div style="padding:6px"><button class="btn" type="button" id="addItemRow">+ Add Line</button></div>
</div></div>
<button class="btn btn-primary" type="submit">Submit Request</button>
<a class="btn" href="index.php">Cancel</a>
</form>

<template id="itemRowTemplate">
  <td><select name="items[__IDX__][batch_id]" style="width:100%">
    <option value="">-- Select Batch --</option>
    <?php foreach ($batches as $b): ?>
      <option value="<?php echo (int)$b['id']; ?>">
        <?php echo e($b['part_code'].' | '.$b['batch_code'].' | '.$b['warehouse_code']
                    .' | exp '.dmy($b['expiration_date']).' | rem '.$b['qty_remaining']); ?></option>
    <?php endforeach; ?>
  </select></td>
  <td><input type="number" name="items[__IDX__][qty]" min="1" value="1" style="width:100%"></td>
  <td class="center"><button type="button" class="btn btn-sm btn-danger"
        onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var i = 1, tbody = document.getElementById('itemRows'), tpl = document.getElementById('itemRowTemplate');
  document.getElementById('addItemRow').addEventListener('click', function () {
    var tr = document.createElement('tr');
    tr.innerHTML = tpl.innerHTML.replace(/__IDX__/g, i++);
    tbody.appendChild(tr);
  });
});
</script>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>