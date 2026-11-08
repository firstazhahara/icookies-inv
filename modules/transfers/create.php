<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('transfer.create');

$warehouses = db_all("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY warehouse_code");
$parts      = db_all("SELECT * FROM master_parts WHERE is_active = 1 ORDER BY part_code");
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $date = $_POST['transfer_date'];
    $src  = (int)$_POST['source_warehouse_id'];
    $dst  = (int)$_POST['destination_warehouse_id'];
    $rem  = trim($_POST['remarks']);
    $items = isset($_POST['items']) ? $_POST['items'] : array();

    if ($src <= 0 || $dst <= 0) $errors[] = 'Source and destination warehouse are required.';
    if ($src === $dst)          $errors[] = 'Source and destination must be different.';
    if ($date === '')           $errors[] = 'Transfer date is required.';

    $clean = array();
    foreach ($items as $it) {
        $pid = (int)$it['part_id']; $qty = (int)$it['qty'];
        if ($pid > 0 && $qty > 0) $clean[] = array('part_id'=>$pid, 'qty'=>$qty);
    }
    if (!$clean) $errors[] = 'No valid line items.';

    if (!$errors) {
        $number = next_number('TR', 'inventory_transfers', 'transfer_number');
        $db = db();
        mysqli_begin_transaction($db);
        try {
            $tid = db_exec("INSERT INTO inventory_transfers
                (transfer_number, transfer_date, source_warehouse_id, destination_warehouse_id,
                 status, remarks, created_by) VALUES (?,?,?,?,'PENDING',?,?)",
                array($number, $date, $src, $dst, $rem,
                      isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null));

            foreach ($clean as $it) {
                $alloc = consume_stock_fefo($it['part_id'], $src, $it['qty'],
                                            'TRANSFER_OUT', $number, 'Transfer to WH #' . $dst);
                foreach ($alloc as $a) {
                    db_exec("INSERT INTO inventory_transfer_details
                        (transfer_id, part_id, source_batch_id, dest_batch_id, qty, purchase_cost,
                         expiration_date, batch_code)
                        VALUES (?,?,?,NULL,?,?,?,?)",
                        array($tid, $it['part_id'], $a['batch_id'], $a['qty'],
                              $a['purchase_cost'], $a['expiration_date'], $a['batch_code']));
                }
            }
            mysqli_commit($db);
            audit('CREATE', 'transfers', $number, 'Transfer created (pending approval)');
            $_SESSION['flash'] = array('type'=>'success',
                'msg'=>'Transfer ' . $number . ' created. Awaiting supervisor approval.');
            redirect('view.php?id=' . $tid);
        } catch (Exception $ex) {
            mysqli_rollback($db);
            $errors[] = $ex->getMessage();
        }
    }
}

$page_title  = 'New Stock Transfer';
$breadcrumbs = array('Inventory'=>null,
                     'Transfers'=>base_url().'/modules/transfers/index.php', 'New'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?php echo e($er); ?></div><?php endforeach; ?>
<form method="post" action="create.php">
<?php echo csrf_field(); ?>
<div class="panel"><div class="panel-h">Transfer Header</div><div class="panel-b">
<div class="formgrid3">
  <div class="formrow"><label>Transfer Date <span class="req">*</span></label>
    <input type="date" name="transfer_date" value="<?php echo e(date('Y-m-d')); ?>"></div>
  <div class="formrow"><label>Source Warehouse <span class="req">*</span></label>
    <select name="source_warehouse_id"><option value="">-- Select --</option>
    <?php foreach ($warehouses as $w): ?>
      <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['warehouse_code'].' - '.$w['warehouse_name']); ?></option>
    <?php endforeach; ?></select></div>
  <div class="formrow"><label>Destination Warehouse <span class="req">*</span></label>
    <select name="destination_warehouse_id"><option value="">-- Select --</option>
    <?php foreach ($warehouses as $w): ?>
      <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['warehouse_code'].' - '.$w['warehouse_name']); ?></option>
    <?php endforeach; ?></select></div>
</div>
<div class="formrow"><label>Remarks</label><input type="text" name="remarks"></div>
<div class="flash flash-info">Batch identity, expiration date and purchase cost are preserved.
  Stock moves only after approval.</div>
</div></div>

<div class="panel"><div class="panel-h">Line Items</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th style="width:60%">Part</th><th style="width:15%">Qty</th>
    <th style="width:15%">Del</th></tr></thead>
  <tbody id="itemRows">
    <tr>
      <td><select name="items[0][part_id]" class="part-select" style="width:100%">
        <option value="">-- Select Part --</option>
        <?php foreach ($parts as $pt): ?>
          <option value="<?php echo (int)$pt['id']; ?>">
            <?php echo e($pt['part_code'].' | '.$pt['device_model'].' | '.$pt['part_category']); ?></option>
        <?php endforeach; ?>
      </select></td>
      <td><input type="number" name="items[0][qty]" min="1" value="1" style="width:100%"></td>
      <td class="center"><button type="button" class="btn btn-sm btn-danger"
            onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
    </tr>
  </tbody>
</table>
<div style="padding:6px"><button class="btn" type="button" id="addItemRow">+ Add Line</button></div>
</div></div>
<button class="btn btn-primary" type="submit">Create Transfer</button>
<a class="btn" href="index.php">Cancel</a>
</form>

<template id="itemRowTemplate">
  <td><select name="items[__IDX__][part_id]" style="width:100%">
    <option value="">-- Select Part --</option>
    <?php foreach ($parts as $pt): ?>
      <option value="<?php echo (int)$pt['id']; ?>">
        <?php echo e($pt['part_code'].' | '.$pt['device_model'].' | '.$pt['part_category']); ?></option>
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