<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_login();
$reports = array(
  'current_stock'      => '1. Current Stock Report',
  'stock_by_warehouse' => '2. Stock by Warehouse',
  'stock_by_part'      => '3. Stock by Part',
  'batch_inventory'    => '4. Batch Inventory Report',
  'incoming_history'   => '5. Incoming History',
  'outgoing_history'   => '6. Outgoing History',
  'sales'              => '7. Sales Report',
  'issuance'           => '8. Internal Issuance Report',
  'transfers'          => '9. Stock Transfer Report',
  'stocktaking'        => '10. Stocktaking Variance Report',
  'disposal'           => '11. Disposal Report',
  'near_expiry'        => '12. Near Expiry Report',
  'expired'            => '13. Expired Stock Report',
  'valuation'          => '14. Inventory Valuation Report',
  'gross_profit'       => '15. Gross Profit Report',
  'stock_movement'     => '16. Stock Movement Report'
);
$page_title  = 'Report Center';
$breadcrumbs = array('Reports'=>null, 'Report Center'=>null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="panel"><div class="panel-h">Available Reports</div><div class="panel-b">
<table class="grid">
  <tbody>
  <?php $i = 0; foreach ($reports as $k => $label): $i++;
      if ($i % 2 == 1) echo '<tr>'; ?>
    <td style="width:50%">
      <a href="view.php?type=<?php echo e($k); ?>"><?php echo e($label); ?></a>
    </td>
  <?php if ($i % 2 == 0) echo '</tr>'; endforeach; if ($i % 2 == 1) echo '</tr>'; ?>
  </tbody>
</table>
<div class="flash flash-info" style="margin-top:10px">
  All reports support date range, warehouse and part filters, CSV export and print layout.
</div>
</div></div>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>