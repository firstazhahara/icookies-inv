<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('*');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    if (!csrf_validate()) die('Invalid CSRF token.');
    $id = (int)$_POST['id'];
    if ($id === current_user_id()) die('You cannot deactivate your own account.');
    $cur = (int) db_val("SELECT is_active FROM users WHERE id = ?", array($id));
    db_exec("UPDATE users SET is_active = ? WHERE id = ?", array($cur ? 0 : 1, $id));
    audit('TOGGLE', 'users', 'ID ' . $id, 'Active=' . ($cur ? 0 : 1));
    $_SESSION['flash'] = array('type'=>'success','msg'=>'User status updated.');
    redirect('index.php');
}

$rows = db_all("SELECT u.*, GROUP_CONCAT(r.role_name SEPARATOR ', ') roles
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id
                GROUP BY u.id ORDER BY u.username");

$page_title  = 'Users & Roles';
$breadcrumbs = array('Administration'=>null, 'Users'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <div><b>User accounts and role assignment</b></div>
  <div style="margin-left:auto"><a class="btn btn-success" href="edit.php">+ New User</a></div>
</div>
<div class="panel"><div class="panel-h">Users</div><div class="panel-b" style="padding:0">
<table class="grid">
  <thead><tr><th>Username</th><th>Full Name</th><th>Email</th><th>Roles</th>
    <th>Last Login</th><th class="center">Status</th><th class="center">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?php echo e($r['username']); ?></td><td><?php echo e($r['full_name']); ?></td>
        <td><?php echo e($r['email']); ?></td><td><?php echo e($r['roles']); ?></td>
        <td><?php echo $r['last_login'] ? date('d M Y H:i', strtotime($r['last_login'])) : '-'; ?></td>
        <td class="center"><?php echo (int)$r['is_active']
            ? '<span class="badge b-normal">ACTIVE</span>'
            : '<span class="badge b-depleted">INACTIVE</span>'; ?></td>
        <td class="center actions">
          <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
          <form method="post" action="index.php" style="display:inline"
                onsubmit="return confirm('Toggle this user status?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
            <button class="btn btn-sm" type="submit">Toggle</button>
          </form>
        </td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>