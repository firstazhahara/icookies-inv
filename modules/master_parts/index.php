<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_perm('inventory.view');

$q       = isset($_GET['q']) ? trim($_GET['q']) : '';
$fType   = isset($_GET['device_type']) ? $_GET['device_type'] : '';
$fModel  = isset($_GET['device_model']) ? $_GET['device_model'] : '';
$fCat    = isset($_GET['part_category']) ? $_GET['part_category'] : '';
$page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$where = " WHERE 1=1"; $params = array();
if ($q !== '')      { $where .= " AND part_code LIKE ?"; $params[] = '%' . $q . '%'; }
if ($fType !== '')  { $where .= " AND device_type = ?";  $params[] = $fType; }
if ($fModel !== '') { $where .= " AND device_model = ?"; $params[] = $fModel; }
if ($fCat !== '')   { $where .= " AND part_category = ?";$params[] = $fCat; }

$total = (int) db_val("SELECT COUNT(*) FROM master_parts" . $where, $params);
$p = paginate($total, $page, ROWS_PER_PAGE);

$rows = db_all("SELECT * FROM master_parts" . $where .
               " ORDER BY part_code LIMIT " . (int)$p['per_page'] . " OFFSET " . (int)$p['offset'],
               $params);

$types  = db_all("SELECT DISTINCT device_type FROM master_parts ORDER BY device_type");
$models = db_all("SELECT DISTINCT device_model FROM master_parts ORDER BY device_model");
$cats   = db_all("SELECT DISTINCT part_category FROM master_parts ORDER BY part_category");

$page_title  = 'Part Master';
$breadcrumbs = array('Master Data' => null, 'Part Master' => null);
require dirname(dirname(__DIR__)) . '/includes/header.php';
?>
<div class="toolbar">
  <form method="get" action="index.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <div class="fld"><label>Part ID</label><input type="text" name="q" value="<?php echo e($q); ?>"></div>
    <div class="fld"><label>Device Type</label>
      <select name="device_type"><option value="">All</option>
      <?php foreach ($types as $t): ?>
        <option value="<?php echo e($t['device_type']); ?>" <?php if ($fType==$t['device_type']) echo 'selected'; ?>>
          <?php echo e($t['device_type']); ?></option>
      <?php endforeach; ?></select></div>
    <div class="fld"><label>Model</label>
      <select name="device_model"><option value="">All</option>
      <?php foreach ($models as $t): ?>
        <option value="<?php echo e($t['device_model']); ?>" <?php if ($fModel==$t['device_model']) echo 'selected'; ?>>
          <?php echo e($t['device_model']); ?></option>
      <?php endforeach; ?></select></div>
    <div class="fld"><label>Category</label>
      <select name="part_category"><option value="">All</option>
      <?php foreach ($cats as $t): ?>
        <option value="<?php echo e($t['part_category']); ?>" <?php if ($fCat==$t['part_category']) echo 'selected'; ?>>
          <?php echo e($t['part_category']); ?></option>
      <?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn" href="index.php">Reset</a>
  </form>
  <div style="margin-left:auto" class="actions">
    <?php if (can('*')): ?>
    <a class="btn btn-success" href="edit.php">+ New Part</a>
    <a class="btn" href="import.php">Import CSV</a>
    <?php endif; ?>
    <a class="btn" href="export.php">Export CSV</a>
  </div>
</div>

<div class="panel">
  <div class="panel-h">Parts (<?php echo num($total); ?>)</div>
  <div class="panel-b" style="padding:0">
  <table class="grid">
    <thead><tr>
      <th>Part ID</th><th>Device Type</th><th>Model</th><th>Category</th><th>Variant</th>
      <th class="num">Warranty(mo)</th><th class="num">Purchase</th><th class="num">Selling</th>
      <th class="num">GP</th><th class="num">Margin %</th><th class="num">Markup %</th>
      <th class="center">Status</th><th class="center">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $purch = (float)$r['purch_idr']; $sell = (float)$r['sell_idr'];
        $gp = $sell - $purch;
        $margin = $sell > 0 ? ($gp / $sell) * 100 : 0;
        $markup = $purch > 0 ? ($gp / $purch) * 100 : 0; ?>
      <tr>
        <td><a href="view.php?id=<?php echo (int)$r['id']; ?>"><?php echo e($r['part_code']); ?></a></td>
        <td><?php echo e($r['device_type']); ?></td>
        <td><?php echo e($r['device_model']); ?></td>
        <td><?php echo e($r['part_category']); ?></td>
        <td><?php echo e($r['part_variant']); ?></td>
        <td class="num"><?php echo (int)$r['warranty_month']; ?></td>
        <td class="num"><?php echo money($purch); ?></td>
        <td class="num"><?php echo money($sell); ?></td>
        <td class="num"><?php echo money($gp); ?></td>
        <td class="num"><?php echo number_format($margin, 1); ?></td>
        <td class="num"><?php echo number_format($markup, 1); ?></td>
        <td class="center">
          <?php if ((int)$r['is_active']): ?><span class="badge b-normal">ACTIVE</span>
          <?php else: ?><span class="badge b-depleted">INACTIVE</span><?php endif; ?>
        </td>
        <td class="center actions">
          <a class="btn btn-sm" href="view.php?id=<?php echo (int)$r['id']; ?>">View</a>
          <?php if (can('*')): ?>
          <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
          <form method="post" action="edit.php" style="display:inline"
                onsubmit="return confirm('Toggle active status for this part?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
            <button class="btn btn-sm" type="submit"><?php echo (int)$r['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="13" class="center">No records</td></tr>'; ?>
    </tbody>
  </table>
  </div>
</div>
<?php render_pager($p, 'q=' . urlencode($q) . '&device_type=' . urlencode($fType)
                       . '&device_model=' . urlencode($fModel) . '&part_category=' . urlencode($fCat)); ?>
<?php require dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>