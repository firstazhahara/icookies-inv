<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'near';
$wh  = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$warehouses = db_all("SELECT * FROM warehouses ORDER BY warehouse_code");

$baseSql = "SELECT b.*, p.part_code, p.device_type, p.device_model, p.part_category,
                   w.warehouse_code
            FROM inventory_batches b
            JOIN master_parts p ON p.id = b.part_id
            JOIN warehouses w ON w.id = b.warehouse_id
            WHERE b.qty_remaining > 0 ";
$params = array();
if ($wh) { $baseSql .= " AND b.warehouse_id = ?"; $params[] = $wh; }

$near    = db_all($baseSql . " AND b.expiration_date >= CURDATE()
                              AND DATEDIFF(b.expiration_date, CURDATE()) <= ?
                              ORDER BY b.expiration_date", array_merge($params, array(NEAR_EXPIRY_DAYS)));
$expired = db_all($baseSql . " AND b.expiration_date < CURDATE() ORDER BY b.expiration_date", $params);

$nearQty = 0; $nearVal = 0;
foreach ($near as $r) { $nearQty += (int)$r['qty_remaining']; $nearVal += (int)$r['qty_remaining'] * (float)$r['purchase_cost']; }
$expQty = 0; $expVal = 0;
foreach ($expired as $r) { $expQty += (int)$r['qty_remaining']; $expVal += (int)$r['qty_remaining'] * (float)$r['purchase_cost']; }

$page_title  = 'Expiration Management';
$breadcrumbs = array('Monitoring'=>null, 'Expiration'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="kpis" style="grid-template-columns:repeat(6,1fr)">
  <div class="kpi"><div class="kl">Near Expiry Batches</div><div class="kv"><?php echo num(count($near)); ?></div></div>
  <div class="kpi"><div class="kl">Near Expiry Qty</div><div class="kv"><?php echo num($nearQty); ?></div></div>
  <div class="kpi"><div class="kl">Near Expiry Value</div><div class="kv small"><?php echo money($nearVal); ?></div></div>
  <div class="kpi"><div class="kl">Expired Batches</div><div class="kv"><?php echo num(count($expired)); ?></div></div>
  <div class="kpi"><div class="kl">Expired Qty</div><div class="kv"><?php echo num($expQty); ?></div></div>
  <div class="kpi"><div class="kl">Expired Value</div><div class="kv small"><?php echo money($expVal); ?></div></div>
</div>

<div class="toolbar">
  <form method="get" action="index.php" style="display:flex;gap:8px;align-items:flex-end">
    <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
    <div class="fld"><label>Warehouse</label>
      <select name="warehouse_id"><option value="0">All</option>
      <?php foreach ($warehouses as $w): ?>
        <option value="<?php echo (int)$w['id']; ?>" <?php if ($wh==$w['id']) echo 'selected'; ?>>
          <?php echo e($w['warehouse_code']); ?></option>
      <?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Apply</button>
  </form>
  <div style="margin-left:auto" class="actions">
    <a class="btn <?php if ($tab==='near') echo 'btn-primary'; ?>" href="?tab=near&warehouse_id=<?php echo $wh; ?>">Near Expiry</a>
    <a class="btn <?php if ($tab==='expired') echo 'btn-primary'; ?>" href="?tab=expired&warehouse_id=<?php echo $wh; ?>">Expired Stock</a>
  </div>
</div>

<?php $list = ($tab === 'expired') ? $expired : $near; ?>
<div class="panel"><div class="panel-h">
  <?php echo ($tab === 'expired') ? 'Expired Stock Report' : 'Near Expiry Report (next '.NEAR_EXPIRY_DAYS.' days)'; ?>
</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Part ID</th><th>Device Type</th><th>Model</th><th>Category</th><th>Warehouse</th>
    <th>Batch ID</th><th>Incoming</th><th>Expiration</th><th class="num">Qty</th>
    <th class="num">Purchase</th><th class="num">Stock Value</th><th class="num">Days</th>
    <th class="center">Status</th></tr></thead>
  <tbody>
  <?php foreach ($list as $r):
      $st = expiry_status($r['expiration_date'], $r['qty_remaining'], $r['status']);
      $cls = ($st === 'EXPIRED') ? 'b-expired' : (($st === 'NEAR EXPIRY') ? 'b-near' : 'b-normal'); ?>
    <tr><td><?php echo e($r['part_code']); ?></td><td><?php echo e($r['device_type']); ?></td>
        <td><?php echo e($r['device_model']); ?></td><td><?php echo e($r['part_category']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td><td><?php echo e($r['batch_code']); ?></td>
        <td><?php echo dmy($r['incoming_date']); ?></td><td><?php echo dmy($r['expiration_date']); ?></td>
        <td class="num"><?php echo num($r['qty_remaining']); ?></td>
        <td class="num"><?php echo money($r['purchase_cost']); ?></td>
        <td class="num"><?php echo money($r['qty_remaining'] * $r['purchase_cost']); ?></td>
        <td class="num"><?php echo days_remaining($r['expiration_date']); ?></td>
        <td class="center"><span class="badge <?php echo $cls; ?>"><?php echo e($st); ?></span></td></tr>
  <?php endforeach; if (!$list) echo '<tr><td colspan="13" class="center">No batches</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>