<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('incoming.create');

$warehouses = db_all("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY warehouse_code");
$suppliers  = db_all("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY supplier_name");
$parts      = db_all("SELECT * FROM master_parts WHERE is_active = 1 ORDER BY part_code");

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');

    $date  = $_POST['incoming_date'];
    $whId  = (int)$_POST['warehouse_id'];
    $supId = (int)$_POST['supplier_id'];
    $rem   = trim($_POST['remarks']);
    $items = isset($_POST['items']) ? $_POST['items'] : array();

    if ($date === '') $errors[] = 'Incoming date is required.';
    if ($whId <= 0)   $errors[] = 'Warehouse is required.';
    if (!$items)      $errors[] = 'Add at least one line item.';

    $clean = array();
    foreach ($items as $it) {
        $pid = (int)$it['part_id'];
        $qty = (int)$it['qty'];
        $prc = (float)$it['price'];
        if ($pid <= 0 || $qty <= 0) continue;
        $clean[] = array('part_id'=>$pid, 'qty'=>$qty, 'price'=>$prc);
    }
    if (!$clean) $errors[] = 'No valid line items.';

    if (!$errors) {
        $db = db();
        mysqli_begin_transaction($db);
        try {
            $number = next_number('IC', 'inventory_incoming_headers', 'incoming_number');
            $hid = db_exec("INSERT INTO inventory_incoming_headers
                (incoming_number, incoming_date, warehouse_id, supplier_id, remarks, total_cost,
                 status, created_by) VALUES (?,?,?,?,?,0,'POSTED',?)",
                array($number, $date, $whId, $supId ? $supId : null, $rem,
                      isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null));

            $grandTotal = 0;
            $seq = 0;
            foreach ($clean as $it) {
                $seq++;
                $expiration = expiration_from($date);
                $total = $it['qty'] * $it['price'];
                $grandTotal += $total;

                $part = db_one("SELECT part_code FROM master_parts WHERE id = ?", array($it['part_id']));
                $batchCode = 'B-' . date('Ymd', strtotime($date)) . '-'
                           . $part['part_code'] . '-' . $number . '-' . $seq;

                $batchId = add_stock($it['part_id'], $whId, $batchCode, $it['qty'], $it['price'],
                                     $expiration, $date, $supId ? $supId : null, $hid,
                                     'INCOMING', $number, 'Incoming receipt');

                db_exec("INSERT INTO inventory_incoming_details
                    (incoming_id, part_id, batch_id, qty_received, purchase_price, total_cost, expiration_date)
                    VALUES (?,?,?,?,?,?,?)",
                    array($hid, $it['part_id'], $batchId, $it['qty'], $it['price'], $total, $expiration));
            }
            db_exec("UPDATE inventory_incoming_headers SET total_cost = ? WHERE id = ?",
                    array($grandTotal, $hid));
            mysqli_commit($db);
            audit('CREATE', 'incoming', $number, 'Total ' . money($grandTotal));
            $_SESSION['flash'] = array('type'=>'success',
                'msg'=>'Incoming ' . $number . ' posted successfully.');
            redirect('view.php?id=' . $hid);
        } catch (Exception $ex) {
            mysqli_rollback($db);
            $errors[] = $ex->getMessage();
        }
    }
}

$page_title  = 'Receive Incoming Stock';
$breadcrumbs = array('Inventory'=>null,
                     'Incoming'=>base_url().'/modules/incoming/index.php', 'New Receipt'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?php echo e($er); ?></div><?php endforeach; ?>
<form method="post" action="create.php">
<?php echo csrf_field(); ?>
<div class="panel"><div class="panel-h">Receipt Header</div><div class="panel-b">
  <div class="formgrid3">
    <div class="formrow"><label>Incoming Date <span class="req">*</span></label>
      <input type="date" name="incoming_date" value="<?php echo e(date('Y-m-d')); ?>"></div>
    <div class="formrow"><label>Warehouse <span class="req">*</span></label>
      <select name="warehouse_id">
        <option value="">-- Select --</option>
        <?php foreach ($warehouses as $w): ?>
          <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['warehouse_code'].' - '.$w['warehouse_name']); ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="formrow"><label>Supplier</label>
      <select name="supplier_id">
        <option value="0">-- None --</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['supplier_name']); ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="formrow"><label>Remarks</label>
    <input type="text" name="remarks"></div>
  <div class="flash flash-info">Expiration Date = Incoming Date + 6 calendar months (computed per line).</div>
</div></div>

<div class="panel"><div class="panel-h">Line Items</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th style="width:44%">Part</th><th style="width:10%">Qty</th>
    <th style="width:14%">Purchase Price</th><th style="width:14%">Expiration</th>
    <th style="width:10%">Total</th><th style="width:8%">Del</th></tr></thead>
  <tbody id="itemRows">
    <tr>
      <td><select name="items[0][part_id]" class="part-select" style="width:100%">
        <option value="">-- Select Part --</option>
        <?php foreach ($parts as $pt): ?>
          <option value="<?php echo (int)$pt['id']; ?>"
                  data-purch="<?php echo (float)$pt['purch_idr']; ?>">
            <?php echo e($pt['part_code'].' | '.$pt['device_type'].' '.$pt['device_model'].' | '.$pt['part_category']); ?>
          </option>
        <?php endforeach; ?>
      </select></td>
      <td><input type="number" name="items[0][qty]" class="qty-input" min="1" value="1" style="width:100%"></td>
      <td><input type="number" name="items[0][price]" class="price-input" step="1" value="0" style="width:100%"></td>
      <td class="exp-out"><?php echo dmy(expiration_from(date('Y-m-d'))); ?></td>
      <td class="num total-out">0</td>
      <td class="center"><button type="button" class="btn btn-sm btn-danger"
            onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
    </tr>
  </tbody>
</table>
<div style="padding:6px"><button class="btn" type="button" id="addItemRow">+ Add Line</button></div>
</div></div>

<button class="btn btn-primary" type="submit">Post Receipt</button>
<a class="btn" href="index.php">Cancel</a>
</form>

<template id="itemRowTemplate">
  <td><select name="items[__IDX__][part_id]" class="part-select" style="width:100%">
    <option value="">-- Select Part --</option>
    <?php foreach ($parts as $pt): ?>
      <option value="<?php echo (int)$pt['id']; ?>"
              data-purch="<?php echo (float)$pt['purch_idr']; ?>">
        <?php echo e($pt['part_code'].' | '.$pt['device_type'].' '.$pt['device_model'].' | '.$pt['part_category']); ?>
      </option>
    <?php endforeach; ?>
  </select></td>
  <td><input type="number" name="items[__IDX__][qty]" class="qty-input" min="1" value="1" style="width:100%"></td>
  <td><input type="number" name="items[__IDX__][price]" class="price-input" step="1" value="0" style="width:100%"></td>
  <td class="exp-out">-</td>
  <td class="num total-out">0</td>
  <td class="center"><button type="button" class="btn btn-sm btn-danger"
        onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var addBtn = document.getElementById('addItemRow');
  var tpl = document.getElementById('itemRowTemplate');
  var tbody = document.getElementById('itemRows');
  var idx = 1;
  addBtn.addEventListener('click', function () {
    var tr = document.createElement('tr');
    tr.innerHTML = tpl.innerHTML.replace(/__IDX__/g, idx++);
    tbody.appendChild(tr);
    bind(tr);
  });
  function bind(tr) {
    var sel = tr.querySelector('.part-select');
    var qty = tr.querySelector('.qty-input');
    var prc = tr.querySelector('.price-input');
    var tot = tr.querySelector('.total-out');
    var exp = tr.querySelector('.exp-out');
    function recalc() { tot.textContent = (parseFloat(prc.value||0) * parseInt(qty.value||0,10)); }
    sel.addEventListener('change', function () {
      var o = sel.options[sel.selectedIndex];
      if (o && o.getAttribute('data-purch')) prc.value = o.getAttribute('data-purch');
      recalc();
    });
    qty.addEventListener('keyup', recalc);
    prc.addEventListener('keyup', recalc);
    var d = document.querySelector('input[name="incoming_date"]');
    function setExp() {
      if (!d.value) return;
      var p = d.value.split('-');
      var dt = new Date(+p[0], +p[1]-1, +p[2]);
      dt.setMonth(dt.getMonth() + 6);
      exp.textContent = ('0'+dt.getDate()).slice(-2) + ' '
        + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][dt.getMonth()]
        + ' ' + dt.getFullYear();
    }
    if (d) { d.addEventListener('change', setExp); setExp(); }
  }
  bind(tbody.rows[0]);
});
</script>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>