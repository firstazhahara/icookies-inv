<?php
/* Run once: http://localhost/inventory/install.php
   Creates/repairs the default administrator account. */
require_once dirname(__FILE__) . '/includes/auth.php';

$msg = array();
$existing = db_one("SELECT id FROM users WHERE username = 'admin'");
if (!$existing) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $uid = db_exec("INSERT INTO users (username, password_hash, full_name, email, is_active)
                    VALUES ('admin', ?, 'System Administrator', 'admin@local', 1)", array($hash));
    $rid = (int) db_val("SELECT id FROM roles WHERE role_name = 'ADMIN'");
    db_exec("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)", array($uid, $rid));
    $msg[] = 'Administrator created: <b>admin / admin123</b>';
} else {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    db_exec("UPDATE users SET password_hash = ? WHERE username = 'admin'", array($hash));
    $msg[] = 'Administrator password reset to <b>admin123</b>.';
}

$checks = array();
$checks['warehouses']   = (int) db_val("SELECT COUNT(*) FROM warehouses");
$checks['suppliers']    = (int) db_val("SELECT COUNT(*) FROM suppliers");
$checks['master_parts'] = (int) db_val("SELECT COUNT(*) FROM master_parts");
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Install</title>
<link rel="stylesheet" href="<?php echo base_url(); ?>/assets/css/style.css"></head><body>
<div class="content" style="margin-left:0">
<div class="panel"><div class="panel-h">Installation Status</div><div class="panel-b">
<?php foreach ($msg as $m): ?><div class="flash flash-success"><?php echo $m; ?></div><?php endforeach; ?>
<table class="grid" style="width:400px">
<tr><th>Seed Table</th><th class="num">Rows</th></tr>
<?php foreach ($checks as $k => $v): ?>
<tr><td><?php echo e($k); ?></td><td class="num"><?php echo (int)$v; ?></td></tr>
<?php endforeach; ?>
</table>
<p style="margin-top:12px">
  <a class="btn btn-primary" href="<?php echo base_url(); ?>/login.php">Go to Login</a>
</p>
<p style="font-size:11px;color:#7a8896">
  For security, delete <code>install.php</code> after first run.
</p>
</div></div>
</div></body></html>