<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();

$id  = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$row = $id ? db_one("SELECT * FROM suppliers WHERE id = ?", array($id)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $name = trim($_POST['supplier_name']);
    if ($name === '') {
        $_SESSION['flash'] = array('type'=>'error','msg'=>'Supplier name is required.');
    } else {
        $vals = array($name, trim($_POST['contact_person']), trim($_POST['phone']),
                      trim($_POST['email']), trim($_POST['address']),
                      isset($_POST['is_active']) ? 1 : 0);
        if ($id) {
            db_exec("UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?, email=?,
                     address=?, is_active=? WHERE id=?", array_merge($vals, array($id)));
            audit('UPDATE','suppliers',$name,'Updated');
        } else {
            db_exec("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, is_active)
                     VALUES (?,?,?,?,?,?)", $vals);
            audit('CREATE','suppliers',$name,'Created');
        }
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Supplier saved.');
        redirect('index.php');
    }
    $row = $_POST;
}
$v = function ($k, $d = '') use ($row) { return isset($row[$k]) ? $row[$k] : $d; };

$page_title  = $id ? 'Edit Supplier' : 'New Supplier';
$breadcrumbs = array('Master Data'=>null,
                     'Suppliers'=>base_url().'/modules/suppliers/index.php', $page_title=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h"><?php echo e($page_title); ?></div><div class="panel-b">
<form method="post" action="edit.php">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <div class="formgrid">
    <div class="formrow"><label>Supplier Name <span class="req">*</span></label>
      <input type="text" name="supplier_name" value="<?php echo e($v('supplier_name')); ?>"></div>
    <div class="formrow"><label>Contact Person</label>
      <input type="text" name="contact_person" value="<?php echo e($v('contact_person')); ?>"></div>
    <div class="formrow"><label>Phone</label>
      <input type="text" name="phone" value="<?php echo e($v('phone')); ?>"></div>
    <div class="formrow"><label>Email</label>
      <input type="text" name="email" value="<?php echo e($v('email')); ?>"></div>
    <div class="formrow"><label>Address</label>
      <input type="text" name="address" value="<?php echo e($v('address')); ?>"></div>
  </div>
  <div class="formrow"><label style="font-weight:normal">
    <input type="checkbox" name="is_active" value="1" <?php if ((int)$v('is_active',1)) echo 'checked'; ?>>
    Active</label></div>
  <button class="btn btn-primary" type="submit">Save</button>
  <a class="btn" href="index.php">Cancel</a>
</form>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>