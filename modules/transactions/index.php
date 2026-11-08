<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$df   = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dt   = isset($_GET['date_to'])   ? $_GET['date_to']   : '';
$wh   = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$pt   = isset($_GET['part_id']) ? (int)$_GET['part_id'] : 0;
$ty   = isset($_GET['type']) ? $_GET['type'] : '';
$user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$where = " WHERE 1=1"; $params = array();
if ($df !== '') { $where .= " AND t.trx_date >= ?"; $params[] = $df . ' 00:00:00'; }
if ($dt !== '') { $where .= " AND t.trx_date <= ?"; $params[] = $dt . ' 23:59:59'; }
if ($wh) { $where .= " AND t.warehouse_id = ?"; $params[] = $wh; }
if ($pt) { $where .= " AND t.part_id = ?"; $params[] = $pt; }
if ($ty !== '') { $where .= " AND t.trx_type = ?"; $params[] = $ty; }
if ($user) { $where .= " AND t.user_id = ?"; $params[] = $user; }

$total = (int) db_val("SELECT COUNT(*) FROM inventory_transactions t" . $where, $params);
$p = paginate($total, $page, 50);

$rows = db_all("SELECT t.*, p.part_code, w.warehouse_code, b.batch_code, u.full_name
                FROM inventory_transactions t
                LEFT JOIN master_parts p ON p.id = t.part_id
                LEFT JOIN warehouses w ON w.id = t.warehouse_id
                LEFT JOIN inventory_batches b ON b.id = t.batch_id
                LEFT JOIN users u ON u.id = t.user_id"
                . $where . " ORDER BY t.id DESC LIMIT " . (int)$p['per_page'] . " OFFSET " . (int)$p['offset'],
                $params);

$warehouses = db_all("SELECT * FROM warehouses ORDER BY warehouse_code");
$parts      = db_all("SELECT * FROM master_parts ORDER BY part_code");
$users      = db_all("SELECT id, full_name FROM users ORDER BY full_name");
$types = array('INCOMING','OUTGOING_SALE','OUTGOING_ISSUE','TRANSFER_OUT','TRANSFER_IN',
               'STOCK_ADJUSTMENT_IN','STOCK_ADJUSTMENT_OUT','DISPOSAL','REVERSAL');

$page_title  = 'Stock Movement History';
$breadcrumbs = array('Monitoring'=>null, 'Stock Movement'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
<form method="get" action="index.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
  <div class="fld"><label>From</label><input type="date" name="date_from" value="<?php echo e($df); ?>"></div>
  <div class="fld"><label>To</label><input type="date" name="date_to" value="<?php echo e($dt); ?>"></div>
  <div class="fld"><label>Warehouse</label><select name="warehouse_id"><option value="0">All</option>
    <?php foreach ($warehouses as $w): ?><option value="<?php echo (int)$w['id']; ?>"
      <?php if ($wh==$w['id']) echo 'selected'; ?>><?php echo e($w['warehouse_code']); ?></option><?php endforeach; ?>
  </select></div>
  <div class="fld"><label>Part</label><select name="part_id"><option value="0">All</option>
    <?php foreach ($parts as $x): ?><option value="<?php echo (int)$x['id']; ?>"
      <?php if ($pt==$x['id']) echo 'selected'; ?>><?php echo e($x['part_code']); ?></option><?php endforeach; ?>
  </select></div>
  <div class="fld"><label>Type</label><select name="type"><option value="">All</option>
    <?php foreach ($types as $t): ?><option value="<?php echo e($t); ?>"
      <?php if ($ty===$t) echo 'selected'; ?>><?php echo e($t); ?></option><?php endforeach; ?>
  </select></div>
  <div class="fld"><label>User</label><select name="user_id"><option value="0">All</option>
    <?php foreach ($users as $x): ?><option value="<?php echo (int)$x['id']; ?>"
      <?php if ($user==$x['id']) echo 'selected'; ?>><?php echo e($x['full_name']); ?></option><?php endforeach; ?>
  </select></div>
  <button class="btn btn-primary" type="submit">Filter</button>
  <a class="btn" href="index.php">Reset</a>
</form>
</div>

<div class="panel"><div class="panel-h">Transactions (<?php echo num($total); ?>)</div>
<div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Trx No</th><th>Date</th><th>Type</th><th>Part</th><th>Warehouse</th>
    <th>Batch</th><th class="num">In</th><th class="num">Out</th><th class="num">Balance</th>
    <th>Reference</th><th>User</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?php echo e($r['trx_number']); ?></td>
        <td><?php echo date('d M Y H:i', strtotime($r['trx_date'])); ?></td>
        <td><?php echo e($r['trx_type']); ?></td>
        <td><?php echo e($r['part_code']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td>
        <td><?php echo e($r['batch_code']); ?></td>
        <td class="num"><?php echo $r['qty_in'] ? num($r['qty_in']) : ''; ?></td>
        <td class="num"><?php echo $r['qty_out'] ? num($r['qty_out']) : ''; ?></td>
        <td class="num"><?php echo num($r['balance_after']); ?></td>
        <td><?php echo e($r['reference_number']); ?></td>
        <td><?php echo e($r['full_name']); ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="11" class="center">No transactions</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php render_pager($p, 'date_from='.urlencode($df).'&date_to='.urlencode($dt)
                       .'&warehouse_id='.$wh.'&part_id='.$pt.'&type='.urlencode($ty).'&user_id='.$user); ?>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>