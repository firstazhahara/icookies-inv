<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('outgoing.create');

$warehouses = db_all("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY warehouse_code");
$parts      = db_all("SELECT * FROM master_parts WHERE is_active = 1 ORDER BY part_code");

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');

    $date = $_POST['outgoing_date'];
    $whId = (int)$_POST['warehouse_id'];
    $type = $_POST['outgoing_type'] === 'ISSUE' ? 'ISSUE' : 'SALE';
    $cust = trim($_POST['customer_reference']);
    $rem  = trim($_POST['remarks']);
    $items = isset($_POST['items']) ? $_POST['items'] : array();

    if ($date === '') $errors[] = 'Outgoing date is required.';
    if ($whId <= 0)   $errors[] = 'Warehouse is required.';

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
            $prefix = ($type === 'SALE') ? 'SO' : 'IS';
            $number = next_number($prefix, 'inventory_outgoing_headers', 'outgoing_number');
            $hid = db_exec("INSERT INTO inventory_outgoing_headers
                (outgoing_number, outgoing_date, warehouse_id, outgoing_type, customer_reference,
                 remarks, status, created_by)
                VALUES (?,?,?,?,?,?,'POSTED',?)",
                array($number, $date, $whId, $type, $cust, $rem,
                      isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null));

            $totalCost = 0; $totalSell = 0; $totalGP = 0;

            foreach ($clean as $it) {
                $part = db_one("SELECT * FROM master_parts WHERE id = ?", array($it['part_id']));
                if (!$part) throw new Exception('Part not found.');

                $sellPrice = ($type === 'SALE') ? $it['price'] : 0;
                if ($type === 'SALE' && $sellPrice <= 0) $sellPrice = (float)$part['sell_idr'];

                $warrantyEnd = null;
                if ($type === 'SALE' && (int)$part['warranty_month'] > 0) {
                    $warrantyEnd = add_months($date, (int)$part['warranty_month']);
                }

                $trxType = ($type === 'SALE') ? 'OUTGOING_SALE' : 'OUTGOING_ISSUE';
                $allocs = consume_stock_fefo($it['part_id'], $whId, $it['qty'], $trxType,
                                             $number, 'Outgoing ' . $number);

                foreach ($allocs as $a) {
                    $tCost = $a['qty'] * $a['purchase_cost'];
                    $tSell = $a['qty'] * $sellPrice;
                    $gp    = $tSell - $tCost;
                    $totalCost += $tCost; $totalSell += $tSell; $totalGP += $gp;

                    db_exec("INSERT INTO inventory_outgoing_details
                        (outgoing_id, part_id, batch_id, qty, purchase_cost, selling_price,
                         total_purchase_cost, total_selling_price, gross_profit, warranty_end_date)
                        VALUES (?,?,?,?,?,?,?,?,?,?)",
                        array($hid, $it['part_id'], $a['batch_id'], $a['qty'],
                              $a['purchase_cost'], $sellPrice, $tCost, $tSell, $gp, $warrantyEnd));
                }
            }

            db_exec("UPDATE inventory_outgoing_headers SET total_purchase_cost=?, total_selling_price=?,
                     gross_profit=? WHERE id=?", array($totalCost, $totalSell, $totalGP, $hid));

            mysqli_commit($db);
            audit('CREATE', 'outgoing', $number, 'Total ' . money($totalSell));
            $_SESSION['flash'] = array('type'=>'success','msg'=>'Outgoing ' . $number . ' posted.');
            redirect('view.php?id=' . $hid);
        } catch (Exception $ex) {
            mysqli_rollback($db);
            $errors[] = $ex->getMessage();
        }
    }
}

$page_title  = 'Issue / Sell Stock';
$breadcrumbs = array('Inventory'=>null,
                     'Outgoing'=>base_url().'/modules/outgoing/index.php', 'New Transaction'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?php echo e($er); ?></div><?php endforeach; ?>
<form method="post" action="create.php" id="outForm">
<?php echo csrf_field(); ?>
<div class="panel"><div class="panel-h">Transaction Header</div><div class="panel-b">
<div class="formgrid3">
  <div class="formrow"><label>Date <span class="req">*</span></label>
    <input type="date" name="outgoing_date" value="<?php echo e(date('Y-m-d')); ?>"></div>
  <div class="formrow"><label>Warehouse <span class="req">*</span></label>
    <select name="warehouse_id" id="whSel">
      <option value="">-- Select --</option>
      <?php foreach ($warehouses as $w): ?>
        <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['warehouse_code'].' - '.$w['warehouse_name']); ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="formrow"><label>Type</label>
    <select name="outgoing_type" id="typeSel">
      <option value="SALE">SALE</option>
      <option value="ISSUE">INTERNAL ISSUE</option>
    </select></div>
</div>
<div class="formgrid">
  <div class="formrow"><label>Customer / Reference</label>
    <input type="text" name="customer_reference"></div>
  <div class="formrow"><label>Remarks</label>
    <input type="text" name="remarks"></div>
</div>
<div class="flash flash-info">Batches are allocated automatically using <b>FEFO</b>
  (earliest expiration first). Expired batches are never issued.</div>
</div></div>

<div class="panel"><div class="panel-h">Line Items</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th style="width:42%">Part</th><th style="width:9%">Qty</th>
    <th style="width:12%">On Hand</th><th style="width:14%">Selling Price</th>
    <th style="width:10%">Total</th><th style="width:8%">Del</th></tr></thead>
  <tbody id="itemRows">
    <tr>
      <td><select name="items[0][part_id]" class="part-select" style="width:100%">
        <option value="">-- Select Part --</option>
        <?php foreach ($parts as $pt): ?>
          <option value="<?php echo (int)$pt['id']; ?>"
                  data-sell="<?php echo (float)$pt['sell_idr']; ?>">
            <?php echo e($pt['part_code'].' | '.$pt['device_model'].' | '.$pt['part_category']); ?>
          </option>
        <?php endforeach; ?>
      </select></td>
      <td><input type="number" name="items[0][qty]" class="qty-input" min="1" value="1" style="width:100%"></td>
      <td class="num stock-out">-</td>
      <td><input type="number" name="items[0][price]" class="price-input" step="1" value="0" style="width:100%"></td>
      <td class="num total-out">0</td>
      <td class="center"><button type="button" class="btn btn-sm btn-danger"
            onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
    </tr>
  </tbody>
</table>
<div style="padding:6px"><button class="btn" type="button" id="addItemRow">+ Add Line</button></div>
</div></div>

<button class="btn btn-primary" type="submit">Post Transaction</button>
<a class="btn" href="index.php">Cancel</a>
</form>

<template id="itemRowTemplate">
  <td><select name="items[__IDX__][part_id]" class="part-select" style="width:100%">
    <option value="">-- Select Part --</option>
    <?php foreach ($parts as $pt): ?>
      <option value="<?php echo (int)$pt['id']; ?>" data-sell="<?php echo (float)$pt['sell_idr']; ?>">
        <?php echo e($pt['part_code'].' | '.$pt['device_model'].' | '.$pt['part_category']); ?></option>
    <?php endforeach; ?>
  </select></td>
  <td><input type="number" name="items[__IDX__][qty]" class="qty-input" min="1" value="1" style="width:100%"></td>
  <td class="num stock-out">-</td>
  <td><input type="number" name="items[__IDX__][price]" class="price-input" step="1" value="0" style="width:100%"></td>
  <td class="num total-out">0</td>
  <td class="center"><button type="button" class="btn btn-sm btn-danger"
        onclick="this.closest('tr').parentNode.removeChild(this.closest('tr'))">X</button></td>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var tbody = document.getElementById('itemRows');
  var tpl   = document.getElementById('itemRowTemplate');
  var idx   = 1;
  document.getElementById('addItemRow').addEventListener('click', function () {
    var tr = document.createElement('tr');
    tr.innerHTML = tpl.innerHTML.replace(/__IDX__/g, idx++);
    tbody.appendChild(tr); bind(tr);
  });
  function bind(tr) {
    var sel = tr.querySelector('.part-select');
    var qty = tr.querySelector('.qty-input');
    var prc = tr.querySelector('.price-input');
    var tot = tr.querySelector('.total-out');
    var stk = tr.querySelector('.stock-out');
    function recalc() { tot.textContent = parseFloat(prc.value||0) * parseInt(qty.value||0,10); }
    sel.addEventListener('change', function () {
      var o = sel.options[sel.selectedIndex];
      if (o && o.getAttribute('data-sell')) prc.value = o.getAttribute('data-sell');
      var wh = document.getElementById('whSel').value;
      if (!wh || !sel.value) { stk.textContent = '-'; return; }
      var xhr = new XMLHttpRequest();
      xhr.open('GET', 'stock_check.php?warehouse_id=' + wh + '&part_id=' + sel.value, true);
      xhr.onload = function () {
        try { stk.textContent = JSON.parse(xhr.responseText).qty; } catch (e) { stk.textContent = '?'; }
      };
      xhr.send();
      recalc();
    });
    qty.addEventListener('keyup', recalc);
    prc.addEventListener('keyup', recalc);
  }
  bind(tbody.rows[0]);
});
</script>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>