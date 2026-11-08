<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('*');

$id  = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$row = $id ? db_one("SELECT * FROM master_parts WHERE id = ?", array($id)) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) { die('Invalid CSRF token.'); }

    if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
        $cur = (int) db_val("SELECT is_active FROM master_parts WHERE id = ?", array($id));
        db_exec("UPDATE master_parts SET is_active = ?, updated_at = NOW() WHERE id = ?",
                array($cur ? 0 : 1, $id));
        audit('TOGGLE', 'master_parts', 'ID ' . $id, 'Active=' . ($cur ? 0 : 1));
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Part status updated.');
        redirect('index.php');
    }

    $code   = trim(isset($_POST['part_code']) ? $_POST['part_code'] : '');
    $dtype  = trim(isset($_POST['device_type']) ? $_POST['device_type'] : '');
    $dmodel = trim(isset($_POST['device_model']) ? $_POST['device_model'] : '');
    $pcat   = trim(isset($_POST['part_category']) ? $_POST['part_category'] : '');
    $pvar   = trim(isset($_POST['part_variant']) ? $_POST['part_variant'] : '');
    $warr   = (int)(isset($_POST['warranty_month']) ? $_POST['warranty_month'] : 1);
    $purch  = (float)(isset($_POST['purch_idr']) ? $_POST['purch_idr'] : 0);
    $sell   = (float)(isset($_POST['sell_idr']) ? $_POST['sell_idr'] : 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    $errors = array();
    if ($code === '')   $errors[] = 'Part ID is required.';
    if ($dtype === '')  $errors[] = 'Device Type is required.';
    if ($dmodel === '') $errors[] = 'Device Model is required.';
    if ($pcat === '')   $errors[] = 'Part Category is required.';
    if ($purch < 0 || $sell < 0) $errors[] = 'Prices cannot be negative.';

    $dup = db_val("SELECT id FROM master_parts WHERE part_code = ? AND id <> ?", array($code, $id));
    if ($dup) $errors[] = 'Part ID already exists.';

    if (!$errors) {
        if ($id) {
            db_exec("UPDATE master_parts SET part_code=?, device_type=?, device_model=?, part_category=?,
                     part_variant=?, warranty_month=?, purch_idr=?, sell_idr=?, is_active=?, updated_at=NOW()
                     WHERE id=?",
                    array($code, $dtype, $dmodel, $pcat, $pvar, $warr, $purch, $sell, $active, $id));
            audit('UPDATE', 'master_parts', $code, 'Part updated');
        } else {
            db_exec("INSERT INTO master_parts (part_code, device_type, device_model, part_category,
                     part_variant, warranty_month, purch_idr, sell_idr, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?)",
                    array($code, $dtype, $dmodel, $pcat, $pvar, $warr, $purch, $sell, $active));
            audit('CREATE', 'master_parts', $code, 'Part created');
        }
        $_SESSION['flash'] = array('type'=>'success','msg'=>'Part saved successfully.');
        redirect('index.php');
    }
    $row = array('part_code'=>$code,'device_type'=>$dtype,'device_model'=>$dmodel,
                 'part_category'=>$pcat,'part_variant'=>$pvar,'warranty_month'=>$warr,
                 'purch_idr'=>$purch,'sell_idr'=>$sell,'is_active'=>$active);
    $_SESSION['flash'] = array('type'=>'error','msg'=>implode(' ', $errors));
}

$v = function ($k, $d = '') use ($row) {
    return isset($row[$k]) ? $row[$k] : $d;
};

$page_title  = $id ? 'Edit Part' : 'New Part';
$breadcrumbs = array('Master Data' => null, 'Part Master' => base_url().'/modules/master_parts/index.php',
                     $page_title => null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel">
  <div class="panel-h"><?php echo e($page_title); ?></div>
  <div class="panel-b">
    <form method="post" action="edit.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
      <div class="formgrid3">
        <div class="formrow"><label>Part ID <span class="req">*</span></label>
          <input type="text" name="part_code" value="<?php echo e($v('part_code')); ?>"></div>
        <div class="formrow"><label>Device Type <span class="req">*</span></label>
          <input type="text" name="device_type" value="<?php echo e($v('device_type')); ?>"></div>
        <div class="formrow"><label>Device Model <span class="req">*</span></label>
          <input type="text" name="device_model" value="<?php echo e($v('device_model')); ?>"></div>
        <div class="formrow"><label>Part Category <span class="req">*</span></label>
          <input type="text" name="part_category" value="<?php echo e($v('part_category')); ?>"></div>
        <div class="formrow"><label>Part Variant</label>
          <input type="text" name="part_variant" value="<?php echo e($v('part_variant')); ?>"></div>
        <div class="formrow"><label>Warranty (months)</label>
          <input type="number" name="warranty_month" min="0" value="<?php echo (int)$v('warranty_month',1); ?>"></div>
        <div class="formrow"><label>Purchase Price (IDR) <span class="req">*</span></label>
          <input type="number" step="1" name="purch_idr" value="<?php echo (float)$v('purch_idr',0); ?>"></div>
        <div class="formrow"><label>Selling Price (IDR) <span class="req">*</span></label>
          <input type="number" step="1" name="sell_idr" value="<?php echo (float)$v('sell_idr',0); ?>"></div>
        <div class="formrow"><label>Status</label>
          <label style="font-weight:normal">
            <input type="checkbox" name="is_active" value="1" <?php if ((int)$v('is_active',1)) echo 'checked'; ?>>
            Active</label></div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn" href="index.php">Cancel</a>
    </form>
  </div>
</div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>