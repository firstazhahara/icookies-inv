<?php
if (!isset($page_title)) $page_title = 'Inventory';
if (!isset($breadcrumbs)) $breadcrumbs = array();
$base  = base_url();
$uname = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
$urole = implode(' / ', current_roles());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo e($page_title); ?> &mdash; <?php echo e(APP_NAME); ?></title>
<link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
</head>
<body>
<div class="topbar">
  <div class="brand">&#9632; iCookies INVENTORY</div>
  <div class="topright">
    <span class="userinfo"><?php echo e($uname); ?> (<?php echo e($urole); ?>)</span>
    <a class="btn-top" href="<?php echo $base; ?>/logout.php"
       onclick="return confirm('Log out now?');">Logout</a>
  </div>
</div>
<?php require dirname(__FILE__) . '/sidebar.php'; ?>
<div class="content">
  <div class="crumbs">
    <a href="<?php echo $base; ?>/index.php">Home</a>
    <?php foreach ($breadcrumbs as $label => $url): ?>
      &raquo;
      <?php if ($url): ?><a href="<?php echo e($url); ?>"><?php echo e($label); ?></a>
      <?php else: ?><span><?php echo e($label); ?></span><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="pagehead">
    <h1><?php echo e($page_title); ?></h1>
  </div>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="flash flash-<?php echo e($_SESSION['flash']['type']); ?>">
    <?php echo e($_SESSION['flash']['msg']); ?>
  </div>
<?php unset($_SESSION['flash']); endif; ?>