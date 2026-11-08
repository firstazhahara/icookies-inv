<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('*');

$report = array(); $errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a CSV file to upload.';
    } else {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) { $errors[] = 'Cannot read uploaded file.'; }
        else {
            $line = 0; $created = 0; $updated = 0;
            while (($data = fgetcsv($fh, 4096, ',')) !== false) {
                $line++;
                if ($line === 1 && strtolower(trim($data[0])) === 'part_id') continue; // header
                if (count($data) < 8) { $report[] = "Line $line: not enough columns, skipped."; continue; }
                $code = trim($data[0]);
                if ($code === '') { $report[] = "Line $line: empty Part ID, skipped."; continue; }
                $vals = array(
                    $code, trim($data[1]), trim($data[2]), trim($data[3]),
                    isset($data[4]) ? trim($data[4]) : '',
                    (int)(isset($data[5]) ? $data[5] : 1),
                    (float)(isset($data[6]) ? $data[6] : 0),
                    (float)(isset($data[7]) ? $data[7] : 0)
                );
                $exists = db_val("SELECT id FROM master_parts WHERE part_code = ?", array($code));
                if ($exists) {
                    db_exec("UPDATE master_parts SET device_type=?, device_model=?, part_category=?,
                             part_variant=?, warranty_month=?, purch_idr=?, sell_idr=?, updated_at=NOW()
                             WHERE part_code=?", array($vals[1],$vals[2],$vals[3],$vals[4],
                             $vals[5],$vals[6],$vals[7],$code));
                    $updated++;
                } else {
                    db_exec("INSERT INTO master_parts (part_code, device_type, device_model, part_category,
                             part_variant, warranty_month, purch_idr, sell_idr, is_active)
                             VALUES (?,?,?,?,?,?,?,?,1)", $vals);
                    $created++;
                }
            }
            fclose($fh);
            audit('IMPORT', 'master_parts', 'CSV', "Created $created, updated $updated");
            $_SESSION['flash'] = array('type'=>'success',
                'msg'=>"Import complete: $created created, $updated updated.");
            redirect('index.php');
        }
    }
}

$page_title  = 'Import Part Master';
$breadcrumbs = array('Master Data'=>null, 'Part Master'=>base_url().'/modules/master_parts/index.php',
                     'Import'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Import CSV</div><div class="panel-b">
  <?php foreach ($errors as $er): ?><div class="flash flash-error"><?php echo e($er); ?></div><?php endforeach; ?>
  <?php foreach ($report as $r): ?><div class="flash flash-info"><?php echo e($r); ?></div><?php endforeach; ?>
  <p>CSV columns (header optional):</p>
  <pre style="background:#f4f6f8;border:1px solid #c6d0da;padding:8px;font-size:11px">
Part_ID,Device_Type,Device_Model,Part_Category,Part_Variant,Warranty_month,Purch_IDR,Sell_IDR</pre>
  <form method="post" enctype="multipart/form-data" action="import.php">
    <?php echo csrf_field(); ?>
    <div class="formrow"><label>CSV File</label><input type="file" name="csv" accept=".csv"></div>
    <button class="btn btn-primary" type="submit">Upload &amp; Import</button>
    <a class="btn" href="index.php">Cancel</a>
  </form>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>