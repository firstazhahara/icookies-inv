<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('stocktaking.create');

$warehouses = db_all("SELECT * FROM warehouses WHERE is_active = 1 ORDER BY warehouse_code");
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $wh = (int)$_POST['warehouse_id'];
    $date = $_POST['stocktaking_date'];
    if ($wh <= 0) $errors[] = 'Warehouse is required.';
    if ($date === '') $errors[] = 'Date is required.';

    if (!$errors) {
        $number = next_number('ST', 'stocktaking_headers', 'stocktaking_number');
        $db = db();
        mysqli_begin_transaction($db);
        try {
            $sid = db_exec("INSERT INTO stocktaking_headers
                (stocktaking_number, warehouse_id, stocktaking_date, status, remarks, counted_by)
                VALUES (?,?,?,'OPEN',?,?)",
                array($number, $wh, $date, trim($_POST['remarks']),
                      isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null));

            $batches = db_all("SELECT id, part_id, qty_remaining FROM inventory_batches
                               WHERE warehouse_id = ? AND qty_remaining > 0", array($wh));
            foreach ($batches as $b) {
                db_exec("INSERT INTO stocktaking_details
                    (stocktaking_id, part_id, batch_id, system_qty, physical_qty, variance)
                    VALUES (?,?,?,?,?,0)",
                    array($sid, (int)$b['part_id'], (int)$b['id'], (int)$b['qty_remaining'],
                          (int)$b['qty_remaining']));
            }
            mysqli_commit($db);
            audit('CREATE', 'stocktaking', $number, 'Session created');
            $_SESSION['flash'] = array('type'=>'success','msg'=>'Stocktaking session created.');
            redirect('count.php?id=' . $sid);
        } catch (Exception $ex) {
            mysqli_rollback($db);
            $errors[] = $ex->getMessage();
        }
    }
}
$page_title  = 'New Stocktaking Session';
$breadcrumbs = array('Inventory'=>null,
                     'Stock Taking'=>base_url().'/modules/stocktaking/index.php', 'New'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?><div class="flash flash-error"><?php echo e($er); ?></div><?php endforeach; ?>
<div class="panel"><div class="panel-h">Session</div><div class="panel-b">
<form method="post" action="create.php">
  <?php echo csrf_field(); ?>
  <div class="formgrid">
    <div class="formrow"><label>Warehouse <span class="req">*</span></label>
      <select name="warehouse_id"><option value="">-- Select --</option>
      <?php foreach ($warehouses as $w): ?>
        <option value="<?php echo (int)$w['id']; ?>"><?php echo e($w['warehouse_code'].' - '.$w['warehouse_name']); ?></option>
      <?php endforeach; ?></select></div>
    <div class="formrow"><label>Date <span class="req">*</span></label>
      <input type="date" name="stocktaking_date" value="<?php echo e(date('Y-m-d')); ?>"></div>
  </div>
  <div class="formrow"><label>Remarks</label><input type="text" name="remarks"></div>
  <div class="flash flash-info">A snapshot of all batches with stock in the selected warehouse
    will be generated for counting.</div>
  <button class="btn btn-primary" type="submit">Create Session</button>
  <a class="btn" href="index.php">Cancel</a>
</form>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>