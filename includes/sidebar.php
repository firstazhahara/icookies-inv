<?php $b = base_url(); ?>
<div class="sidebar">
  <div class="navgroup">Dashboard</div>
  <a href="<?php echo $b; ?>/index.php">Overview</a>

  <div class="navgroup">Master Data</div>
  <a href="<?php echo $b; ?>/modules/master_parts/index.php">Part Master</a>
  <a href="<?php echo $b; ?>/modules/warehouses/index.php">Warehouses</a>
  <a href="<?php echo $b; ?>/modules/suppliers/index.php">Suppliers</a>

  <div class="navgroup">Inventory</div>
  <a href="<?php echo $b; ?>/modules/incoming/index.php">Incoming</a>
  <a href="<?php echo $b; ?>/modules/outgoing/index.php">Outgoing &amp; Sales</a>
  <a href="<?php echo $b; ?>/modules/transfers/index.php">Stock Transfer</a>
  <a href="<?php echo $b; ?>/modules/stocktaking/index.php">Stock Taking</a>
  <a href="<?php echo $b; ?>/modules/disposal/index.php">Disposal</a>

  <div class="navgroup">Monitoring</div>
  <a href="<?php echo $b; ?>/modules/expiration/index.php">Expiration</a>
  <a href="<?php echo $b; ?>/modules/valuation/index.php">Inventory Valuation</a>
  <a href="<?php echo $b; ?>/modules/transactions/index.php">Stock Movement</a>

  <div class="navgroup">Reports</div>
  <a href="<?php echo $b; ?>/modules/reports/index.php">Report Center</a>

  <div class="navgroup">Administration</div>
  <a href="<?php echo $b; ?>/modules/users/index.php">Users &amp; Roles</a>
</div>