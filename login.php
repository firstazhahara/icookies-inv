<?php
require_once dirname(__FILE__) . '/includes/auth.php';
if (is_logged_in()) redirect(base_url() . '/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Session token invalid. Please try again.';
    } else {
        $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $p = isset($_POST['password']) ? $_POST['password'] : '';
        $user = db_one("SELECT * FROM users WHERE username = ? AND is_active = 1", array($u));
        if ($user && password_verify($p, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['roles']     = array();
            $rs = db_all("SELECT r.role_name FROM user_roles ur
                          JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?",
                         array((int)$user['id']));
            foreach ($rs as $r) { $_SESSION['roles'][] = $r['role_name']; }
            db_exec("UPDATE users SET last_login = NOW() WHERE id = ?", array((int)$user['id']));
            audit('LOGIN', 'auth', $user['username'], 'Successful login');
            redirect(base_url() . '/index.php');
        }
        $error = 'Invalid username or password.';
        audit('LOGIN_FAILED', 'auth', $u, 'Failed login attempt');
    }
}
$base = base_url();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Login &mdash; <?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css"></head>
<body>
<div class="loginbox">
  <div class="lh">iCookies INVENTORY</div>
  <div class="lb">
    <?php if ($error): ?><div class="flash flash-error"><?php echo e($error); ?></div><?php endif; ?>
    <form method="post" action="login.php">
      <?php echo csrf_field(); ?>
      <div class="formrow"><label>Username</label>
        <input type="text" name="username" autofocus></div>
      <div class="formrow"><label>Password</label>
        <input type="password" name="password"></div>
      <div class="formrow">
        <button class="btn btn-primary" type="submit">Sign In</button>
      </div>
    </form>
    <div style="margin-top:10px;font-size:10px;color:#7a8896;">
      Internal use only. Default admin created by <code>install.php</code>.
    </div>
  </div>
</div>
</body></html>