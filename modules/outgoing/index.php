<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$df = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dt = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$wh = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$ty = isset($_GET['type']) ? $_GET['type'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$where = " WHERE 1=1"; $params = array();
if ($df !== '') { $where .= " AND h.outgoing_date >= ?"; $params[] = $df; }
if ($dt !== '') { $where .= " AND h.outgoing_date <= ?"; $params[] = $dt; }
if ($wh) { $where .= " AND h.warehouse_id = ?"; $params[] = $wh; }
if ($ty !== '') { $where .= " AND h.outgoing_type = ?"; $params[] = $ty; }

$total = (int) db_val("SELECT COUNT(*) FROM inventory_outgoing_headers h" . $where, $params);
$p = paginate($total, $page, ROWS_PER_PAGE);

$rows = db_all("SELECT h.*, w.warehouse_code,
                       (SELECT COALESCE(SUM(d.qty),0) FROM inventory_outgoing_details d
                        WHERE d.outgoing_id = h.id) qty
                FROM inventory_outgoing_headers h
                LEFT JOIN warehouses w ON w.id = h.warehouse_id"
                . $where . " ORDER BY h.id DESC LIMIT " . (int)$p['per_page'] . " OFFSET " . (int)$p['offset'],
                $params);
$warehouses = db_all("SELECT * FROM warehouses ORDER BY warehouse_code");

$page_title  = 'Outgoing Transactions';
$breadcrumbs = array('Inventory'=>null, 'Outgoing & Sales'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <form method="get" action="index.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <div class="fld"><label>Date From</label><input type="date" name="date_from" value="<?php echo e($df); ?>"></div>
    <div class="fld"><label>Date To</label><input type="date" name="date_to" value="<?php echo e($dt); ?>"></div>
    <div class="fld"><label>Warehouse</label>
      <select name="warehouse_id"><option value="0">All</option>
      <?php foreach ($warehouses as $w): ?>
        <option value="<?php echo (int)$w['id']; ?>" <?php if ($wh==$w['id']) echo 'selected'; ?>>
          <?php echo e($w['warehouse_code']); ?></option>
      <?php endforeach; ?></select></div>
    <div class="fld"><label>Type</label>
      <select name="type"><option value="">All</option>
        <option value="SALE" <?php if ($ty==='SALE') echo 'selected'; ?>>SALE</option>
        <option value="ISSUE" <?php if ($ty==='ISSUE') echo 'selected'; ?>>ISSUE</option>
      </select></div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn" href="index.php">Reset</a>
  </form>
  <div style="margin-left:auto">
    <?php if (can('outgoing.create') || can('sales.create')): ?>
    <a class="btn btn-success" href="create.php">+ New Transaction</a>
    <?php endif; ?>
  </div>
</div>

<div class="panel"><div class="panel-h">Outgoing Transactions (<?php echo num($total); ?>)</div>
<div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Number</th><th>Date</th><th>Warehouse</th><th>Type</th>
    <th>Reference</th><th class="num">Qty</th><th class="num">Purchase</th>
    <th class="num">Selling</th><th class="num">Gross Profit</th><th class="center">Status</th>
    <th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="view.php?id=<?php echo (int)$r['id']; ?>"><?php echo e($r['outgoing_number']); ?></a></td>
        <td><?php echo dmy($r['outgoing_date']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td>
        <td><?php echo e($r['outgoing_type']); ?></td>
        <td><?php echo e($r['customer_reference']); ?></td>
        <td class="num"><?php echo num($r['qty']); ?></td>
        <td class="num"><?php echo money($r['total_purchase_cost']); ?></td>
        <td class="num"><?php echo money($r['total_selling_price']); ?></td>
        <td class="num"><?php echo money($r['gross_profit']); ?></td>
        <td class="center"><?php echo $r['status']==='POSTED'
            ? '<span class="badge b-normal">POSTED</span>'
            : '<span class="badge b-disposed">REVERSED</span>'; ?></td>
        <td class="center"><a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a></td>
    </tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="11" class="center">No records</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php render_pager($p, 'date_from='.urlencode($df).'&date_to='.urlencode($dt)
                       .'&warehouse_id='.$wh.'&type='.urlencode($ty)); ?>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>