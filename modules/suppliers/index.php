<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $id = (int)$_POST['id'];
    $cur = (int) db_val("SELECT is_active FROM suppliers WHERE id = ?", array($id));
    db_exec("UPDATE suppliers SET is_active = ? WHERE id = ?", array($cur ? 0 : 1, $id));
    $_SESSION['flash'] = array('type'=>'success','msg'=>'Supplier status updated.');
    redirect('index.php');
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = array(); $where = '';
if ($q !== '') { $where = " WHERE supplier_name LIKE ? OR contact_person LIKE ?";
                 $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }
$rows = db_all("SELECT * FROM suppliers" . $where . " ORDER BY supplier_name", $params);

$page_title  = 'Suppliers';
$breadcrumbs = array('Master Data'=>null, 'Suppliers'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <form method="get" action="index.php" style="display:flex;gap:8px;align-items:flex-end">
    <div class="fld"><label>Search</label><input type="text" name="q" value="<?php echo e($q); ?>"></div>
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn" href="index.php">Reset</a>
  </form>
  <div style="margin-left:auto"><a class="btn btn-success" href="edit.php">+ New Supplier</a></div>
</div>
<div class="panel"><div class="panel-h">Supplier List</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Address</th>
    <th class="center">Status</th><th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?php echo e($r['supplier_name']); ?></td><td><?php echo e($r['contact_person']); ?></td>
        <td><?php echo e($r['phone']); ?></td><td><?php echo e($r['email']); ?></td>
        <td><?php echo e($r['address']); ?></td>
        <td class="center"><?php echo (int)$r['is_active']
            ? '<span class="badge b-normal">ACTIVE</span>'
            : '<span class="badge b-depleted">INACTIVE</span>'; ?></td>
        <td class="center actions">
          <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
          <form method="post" action="index.php" style="display:inline"
                onsubmit="return confirm('Toggle status?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
            <button class="btn btn-sm" type="submit">Toggle</button>
          </form>
        </td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="center">No suppliers</td></tr>'; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>