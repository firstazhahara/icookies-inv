<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('*');

$id  = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$row = $id ? db_one("SELECT * FROM warehouses WHERE id = ?", array($id)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $code = trim($_POST['warehouse_code']);
    $name = trim($_POST['warehouse_name']);
    $addr = trim($_POST['warehouse_address']);
    $desc = trim($_POST['description']);
    $act  = isset($_POST['is_active']) ? 1 : 0;

    $errors = array();
    if ($code === '') $errors[] = 'Warehouse code is required.';
    if ($name === '') $errors[] = 'Warehouse name is required.';
    if (db_val("SELECT id FROM warehouses WHERE warehouse_code = ? AND id <> ?", array($code, $id))) {
        $errors[] = 'Warehouse code already exists.';
    }
    if (!$errors) {
        if ($id) {
            db_exec("UPDATE warehouses SET warehouse_code=?, warehouse_name=?, warehouse_address=?,
                     description=?, is_active=? WHERE id=?",
                    array($code,$name,$addr,$desc,$act,$id));
            audit('UPDATE', 'warehouses', $code, 'Updated');
        } else {
            db_exec("INSERT INTO warehouses (warehouse_code, warehouse_name, warehouse_address,
                     description, is_active) VALUES (?,?,?,?,?)",
                    array($code,$name,$addr,$desc,$act));
            audit('CREATE', 'warehouses', $code, 'Created');
        }
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Warehouse saved.');
        redirect('index.php');
    }
    $_SESSION['flash'] = array('type'=>'error','msg'=>implode(' ', $errors));
    $row = array('warehouse_code'=>$code,'warehouse_name'=>$name,'warehouse_address'=>$addr,
                 'description'=>$desc,'is_active'=>$act);
}

$v = function ($k, $d = '') use ($row) { return isset($row[$k]) ? $row[$k] : $d; };

$page_title  = $id ? 'Edit Warehouse' : 'New Warehouse';
$breadcrumbs = array('Master Data'=>null,
                     'Warehouses'=>base_url().'/modules/warehouses/index.php', $page_title=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h"><?php echo e($page_title); ?></div><div class="panel-b">
<form method="post" action="edit.php">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <div class="formgrid">
    <div class="formrow"><label>Warehouse Code <span class="req">*</span></label>
      <input type="text" name="warehouse_code" value="<?php echo e($v('warehouse_code')); ?>"></div>
    <div class="formrow"><label>Warehouse Name <span class="req">*</span></label>
      <input type="text" name="warehouse_name" value="<?php echo e($v('warehouse_name')); ?>"></div>
    <div class="formrow"><label>Address</label>
      <input type="text" name="warehouse_address" value="<?php echo e($v('warehouse_address')); ?>"></div>
    <div class="formrow"><label>Description</label>
      <input type="text" name="description" value="<?php echo e($v('description')); ?>"></div>
  </div>
  <div class="formrow"><label style="font-weight:normal">
    <input type="checkbox" name="is_active" value="1" <?php if ((int)$v('is_active',1)) echo 'checked'; ?>>
    Active</label></div>
  <button class="btn btn-primary" type="submit">Save</button>
  <a class="btn" href="index.php">Cancel</a>
</form>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>