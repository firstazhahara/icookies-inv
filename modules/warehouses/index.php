<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $id = (int)$_POST['id'];
    $cur = (int) db_val("SELECT is_active FROM warehouses WHERE id = ?", array($id));
    db_exec("UPDATE warehouses SET is_active = ? WHERE id = ?", array($cur ? 0 : 1, $id));
    audit('TOGGLE', 'warehouses', 'ID ' . $id, 'Active=' . ($cur ? 0 : 1));
    $_SESSION['flash'] = array('type'=>'success','msg'=>'Warehouse status updated.');
    redirect('index.php');
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = array(); $where = '';
if ($q !== '') { $where = " WHERE warehouse_code LIKE ? OR warehouse_name LIKE ?";
                 $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }

$rows = db_all("SELECT w.*,
                  (SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_batches b WHERE b.warehouse_id=w.id) qty,
                  (SELECT COALESCE(SUM(qty_remaining*purchase_cost),0) FROM inventory_batches b
                    WHERE b.warehouse_id=w.id) val
                FROM warehouses w" . $where . " ORDER BY warehouse_code", $params);

$page_title  = 'Warehouses';
$breadcrumbs = array('Master Data'=>null, 'Warehouses'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <form method="get" action="index.php" style="display:flex;gap:8px;align-items:flex-end">
    <div class="fld"><label>Search</label>
      <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="code or name"></div>
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn" href="index.php">Reset</a>
  </form>
  <div style="margin-left:auto"><a class="btn btn-success" href="edit.php">+ New Warehouse</a></div>
</div>

<div class="panel"><div class="panel-h">Warehouse List</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Code</th><th>Name</th><th>Address</th><th>Description</th>
    <th class="num">Stock Qty</th><th class="num">Stock Value</th><th class="center">Status</th>
    <th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo e($r['warehouse_code']); ?></td>
      <td><?php echo e($r['warehouse_name']); ?></td>
      <td><?php echo e($r['warehouse_address']); ?></td>
      <td><?php echo e($r['description']); ?></td>
      <td class="num"><?php echo num($r['qty']); ?></td>
      <td class="num"><?php echo money($r['val']); ?></td>
      <td class="center"><?php echo (int)$r['is_active']
          ? '<span class="badge b-normal">ACTIVE</span>'
          : '<span class="badge b-depleted">INACTIVE</span>'; ?></td>
      <td class="center actions">
        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a>
        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
        <form method="post" action="index.php" style="display:inline"
              onsubmit="return confirm('Toggle active status?');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
          <button class="btn btn-sm" type="submit">Toggle</button>
        </form>
      </td>
    </tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="center">No warehouses</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>