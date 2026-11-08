<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$df   = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dt   = isset($_GET['date_to'])   ? $_GET['date_to']   : '';
$wh   = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$sup  = isset($_GET['supplier_id'])  ? (int)$_GET['supplier_id']  : 0;
$part = isset($_GET['part_id'])      ? (int)$_GET['part_id']      : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$where = " WHERE 1=1"; $params = array();
if ($df !== '') { $where .= " AND h.incoming_date >= ?"; $params[] = $df; }
if ($dt !== '') { $where .= " AND h.incoming_date <= ?"; $params[] = $dt; }
if ($wh) { $where .= " AND h.warehouse_id = ?"; $params[] = $wh; }
if ($sup){ $where .= " AND h.supplier_id = ?";  $params[] = $sup; }
if ($part){ $where .= " AND EXISTS (SELECT 1 FROM inventory_incoming_details d
                                     WHERE d.incoming_id = h.id AND d.part_id = ?)"; $params[] = $part; }

$total = (int) db_val("SELECT COUNT(*) FROM inventory_incoming_headers h" . $where, $params);
$p = paginate($total, $page, ROWS_PER_PAGE);

$rows = db_all("SELECT h.*, w.warehouse_code, s.supplier_name,
                       (SELECT COUNT(*) FROM inventory_incoming_details d
                        WHERE d.incoming_id = h.id) AS line_count
                FROM inventory_incoming_headers h
                LEFT JOIN warehouses w ON w.id = h.warehouse_id
                LEFT JOIN suppliers s ON s.id = h.supplier_id"
                . $where . " ORDER BY h.id DESC LIMIT " . (int)$p['per_page'] . " OFFSET " . (int)$p['offset'],
                $params);

$warehouses = db_all("SELECT * FROM warehouses ORDER BY warehouse_code");
$suppliers  = db_all("SELECT * FROM suppliers ORDER BY supplier_name");
$parts      = db_all("SELECT * FROM master_parts ORDER BY part_code");

$page_title  = 'Incoming Transactions';
$breadcrumbs = array('Inventory'=>null, 'Incoming'=>null);
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
    <div class="fld"><label>Supplier</label>
      <select name="supplier_id"><option value="0">All</option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?php echo (int)$s['id']; ?>" <?php if ($sup==$s['id']) echo 'selected'; ?>>
          <?php echo e($s['supplier_name']); ?></option>
      <?php endforeach; ?></select></div>
    <div class="fld"><label>Part</label>
      <select name="part_id"><option value="0">All</option>
      <?php foreach ($parts as $pt): ?>
        <option value="<?php echo (int)$pt['id']; ?>" <?php if ($part==$pt['id']) echo 'selected'; ?>>
          <?php echo e($pt['part_code']); ?></option>
      <?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn" href="index.php">Reset</a>
  </form>
  <div style="margin-left:auto" class="actions">
    <?php if (can('incoming.create')): ?>
    <a class="btn btn-success" href="create.php">+ New Receipt</a>
    <?php endif; ?>
    <a class="btn" href="export.php?date_from=<?php echo e($df); ?>&date_to=<?php echo e($dt); ?>">Export CSV</a>
  </div>
</div>

<div class="panel"><div class="panel-h">Receipts (<?php echo num($total); ?>)</div>
<div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Number</th><th>Date</th><th>Warehouse</th><th>Supplier</th>
    <th class="num">Lines</th><th class="num">Total Cost</th><th class="center">Status</th>
    <th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="view.php?id=<?php echo (int)$r['id']; ?>"><?php echo e($r['incoming_number']); ?></a></td>
        <td><?php echo dmy($r['incoming_date']); ?></td>
        <td><?php echo e($r['warehouse_code']); ?></td>
        <td><?php echo e($r['supplier_name']); ?></td>
        <td class="num"><?php echo num($r['line_count']); ?></td>
        <td class="num"><?php echo money($r['total_cost']); ?></td>
        <td class="center"><?php echo $r['status'] === 'POSTED'
            ? '<span class="badge b-normal">POSTED</span>'
            : '<span class="badge b-disposed">REVERSED</span>'; ?></td>
        <td class="center actions">
          <a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a>
          <?php if (can('*') && $r['status'] === 'POSTED'): ?>
          <form method="post" action="view.php" style="display:inline"
                onsubmit="return confirm('Reverse this receipt? Stock will be reduced.');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="reverse">
            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
            <button class="btn btn-sm btn-danger" type="submit">Reverse</button>
          </form>
          <?php endif; ?>
        </td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="center">No receipts</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php render_pager($p, 'date_from='.urlencode($df).'&date_to='.urlencode($dt)
                       .'&warehouse_id='.$wh.'&supplier_id='.$sup.'&part_id='.$part); ?>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>