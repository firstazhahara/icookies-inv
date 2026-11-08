<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('*');

$id  = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$row = $id ? db_one("SELECT * FROM users WHERE id = ?", array($id)) : null;
$allRoles = db_all("SELECT * FROM roles ORDER BY id");
$userRoles = array();
if ($id) {
    foreach (db_all("SELECT role_id FROM user_roles WHERE user_id = ?", array($id)) as $r) {
        $userRoles[] = (int)$r['role_id'];
    }
}
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $pwd      = isset($_POST['password']) ? $_POST['password'] : '';
    $active   = isset($_POST['is_active']) ? 1 : 0;
    $roles    = isset($_POST['roles']) ? $_POST['roles'] : array();

    if ($username === '') $errors[] = 'Username is required.';
    if ($fullname === '') $errors[] = 'Full name is required.';
    if (!$id && $pwd === '') $errors[] = 'Password is required for a new user.';
    if (db_val("SELECT id FROM users WHERE username = ? AND id <> ?", array($username, $id))) {
        $errors[] = 'Username already exists.';
    }
    if (!$roles) $errors[] = 'Select at least one role.';

    if (!$errors) {
        if ($id) {
            if ($pwd !== '') {
                db_exec("UPDATE users SET username=?, full_name=?, email=?, is_active=?, password_hash=?
                         WHERE id=?", array($username, $fullname, $email, $active,
                                            password_hash($pwd, PASSWORD_DEFAULT), $id));
            } else {
                db_exec("UPDATE users SET username=?, full_name=?, email=?, is_active=? WHERE id=?",
                        array($username, $fullname, $email, $active, $id));
            }
            db_exec("DELETE FROM user_roles WHERE user_id = ?", array($id));
            audit('UPDATE', 'users', $username, 'User updated');
        } else {
            $id = db_exec("INSERT INTO users (username, password_hash, full_name, email, is_active)
                           VALUES (?,?,?,?,?)",
                          array($username, password_hash($pwd, PASSWORD_DEFAULT),
                                $fullname, $email, $active));
            audit('CREATE', 'users', $username, 'User created');
        }
        foreach ($roles as $rid) {
            db_exec("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)",
                    array($id, (int)$rid));
        }
        $_SESSION['flash'] = array('type'=>'success','msg'=>'User saved.');
        redirect('index.php');
    }
    $row = $_POST; $userRoles = array_map('intval', $roles);
    $_SESSION['flash'] = array('type'=>'error','msg'=>implode(' ', $errors));
}
$v = function ($k, $d = '') use ($row) { return isset($row[$k]) ? $row[$k] : $d; };

$page_title  = $id ? 'Edit User' : 'New User';
$breadcrumbs = array('Administration'=>null,
                     'Users'=>base_url().'/modules/users/index.php', $page_title=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h"><?php echo e($page_title); ?></div><div class="panel-b">
<form method="post" action="edit.php">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <div class="formgrid">
    <div class="formrow"><label>Username <span class="req">*</span></label>
      <input type="text" name="username" value="<?php echo e($v('username')); ?>"></div>
    <div class="formrow"><label>Full Name <span class="req">*</span></label>
      <input type="text" name="full_name" value="<?php echo e($v('full_name')); ?>"></div>
    <div class="formrow"><label>Email</label>
      <input type="text" name="email" value="<?php echo e($v('email')); ?>"></div>
    <div class="formrow"><label>Password <?php if (!$id): ?><span class="req">*</span><?php endif; ?></label>
      <input type="password" name="password"
             placeholder="<?php echo $id ? 'Leave blank to keep current password' : ''; ?>"></div>
  </div>
  <div class="formrow"><label>Roles <span class="req">*</span></label>
    <?php foreach ($allRoles as $r): ?>
      <label style="font-weight:normal;display:inline-block;margin-right:14px">
        <input type="checkbox" name="roles[]" value="<?php echo (int)$r['id']; ?>"
               <?php if (in_array((int)$r['id'], $userRoles)) echo 'checked'; ?>>
        <?php echo e($r['role_name']); ?>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="formrow"><label style="font-weight:normal">
    <input type="checkbox" name="is_active" value="1" <?php if ((int)$v('is_active',1)) echo 'checked'; ?>>
    Active</label></div>
  <button class="btn btn-primary" type="submit">Save</button>
  <a class="btn" href="index.php">Cancel</a>
</form>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>