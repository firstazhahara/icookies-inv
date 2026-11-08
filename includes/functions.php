<?php
require_once dirname(dirname(__FILE__)) . '/config/settings.php';

function e($s) {
    return htmlspecialchars($s === null ? '' : $s, ENT_QUOTES, 'UTF-8');
}

function redirect($url) { header('Location: ' . $url); exit; }

function base_url() {
    $s = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $pos = strpos($s, '/modules/');
    if ($pos !== false) { return substr($s, 0, $pos); }
    return rtrim($s, '/');
}

function money($v) { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
function num($v)   { return number_format((int)$v, 0, ',', '.'); }
function dmy($d)   { return $d ? date('d M Y', strtotime($d)) : '-'; }
function ymd($d)   { return $d ? date('Y-m-d', strtotime($d)) : ''; }

/* ---------- DB helpers (prepared statements) ---------- */
function db_stmt($sql, $params) {
    $db = db();
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) { die('SQL prepare error: ' . mysqli_error($db) . '<br><code>' . e($sql) . '</code>'); }
    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else $types .= 's';
        }
        $args = array();
        $args[] = $stmt;
        $args[] = $types;
        foreach ($params as $k => $v) { $args[] = &$params[$k]; }
        call_user_func_array('mysqli_stmt_bind_param', $args);
    }
    if (!mysqli_stmt_execute($stmt)) {
        die('SQL execute error: ' . mysqli_stmt_error($stmt) . '<br><code>' . e($sql) . '</code>');
    }
    return $stmt;
}

function db_all($sql, $params = array()) {
    $stmt = db_stmt($sql, $params);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = array();
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_one($sql, $params = array()) {
    $rows = db_all($sql, $params);
    return $rows ? $rows[0] : null;
}

function db_val($sql, $params = array()) {
    $r = db_one($sql, $params);
    if (!$r) return null;
    $v = array_values($r);
    return $v[0];
}

function db_exec($sql, $params = array()) {
    $stmt = db_stmt($sql, $params);
    $id   = mysqli_insert_id(db());
    mysqli_stmt_close($stmt);
    return $id;
}

/* ---------- Date / Expiration ---------- */
function expiration_from($date) {
    $d = new DateTime($date);
    $d->add(new DateInterval('P' . EXPIRY_MONTHS . 'M'));
    return $d->format('Y-m-d');
}

function add_months($date, $months) {
    $d = new DateTime($date);
    $d->add(new DateInterval('P' . (int)$months . 'M'));
    return $d->format('Y-m-d');
}

function expiry_status($expiration, $qtyRemaining, $batchStatus) {
    if ($batchStatus === 'DISPOSED') return 'DISPOSED';
    if ((int)$qtyRemaining <= 0) return 'DEPLETED';
    $today = date('Y-m-d');
    if ($expiration <= $today) return 'EXPIRED';
    $days = (int)floor((strtotime($expiration) - strtotime($today)) / 86400);
    return ($days <= NEAR_EXPIRY_DAYS) ? 'NEAR EXPIRY' : 'NORMAL';
}

function days_remaining($expiration) {
    return (int)floor((strtotime($expiration) - strtotime(date('Y-m-d'))) / 86400);
}

/* ---------- Numbering ---------- */
function next_number($prefix, $table, $column) {
    for ($i = 0; $i < 50; $i++) {
        $n = (int) db_val("SELECT COUNT(*) FROM $table") + 1 + $i;
        $cand = $prefix . '-' . date('Y') . '-' . str_pad($n, 5, '0', STR_PAD_LEFT);
        $ex = (int) db_val("SELECT COUNT(*) FROM $table WHERE $column = ?", array($cand));
        if ($ex === 0) return $cand;
    }
    return $prefix . '-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/* ---------- CSRF ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(pack('N', mt_rand()) . pack('N', mt_rand()));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
function csrf_validate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $t = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    return $t !== '' && isset($_SESSION['csrf_token']) && $t === $_SESSION['csrf_token'];
}

/* ---------- Audit ---------- */
function audit($action, $module, $reference = '', $details = '') {
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    db_exec("INSERT INTO audit_logs (user_id, action, module, reference, details, ip_address)
             VALUES (?,?,?,?,?,?)", array($uid, $action, $module, $reference, $details, $ip));
}

/* ---------- Stock primitives ---------------------------------- */
/* IMPORTANT: add_stock / consume_stock must be called inside a DB transaction. */

function add_stock($part_id, $warehouse_id, $batch_code, $qty, $purchase_cost,
                   $expiration_date, $incoming_date, $supplier_id, $incoming_id,
                   $trx_type, $ref_number, $remarks, $trx_date = null) {
    $db  = db();
    $qty = (int)$qty;
    if ($qty <= 0) throw new Exception('Quantity must be greater than zero.');
    if ($trx_date === null) $trx_date = date('Y-m-d H:i:s');

    $existing = db_one("SELECT id, qty_remaining FROM inventory_batches
                        WHERE batch_code = ? AND warehouse_id = ? FOR UPDATE",
                        array($batch_code, $warehouse_id));
    if ($existing) {
        $newQty  = (int)$existing['qty_remaining'] + $qty;
        $batchId = (int)$existing['id'];
        db_exec("UPDATE inventory_batches SET qty_remaining = ?, status = 'ACTIVE' WHERE id = ?",
                array($newQty, $batchId));
    } else {
        $batchId = db_exec("INSERT INTO inventory_batches
            (batch_code, part_id, warehouse_id, supplier_id, incoming_id, incoming_date,
             expiration_date, qty_received, qty_remaining, purchase_cost, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,'ACTIVE')",
            array($batch_code, (int)$part_id, (int)$warehouse_id, $supplier_id, $incoming_id,
                  $incoming_date, $expiration_date, $qty, $qty, (float)$purchase_cost));
        $newQty = $qty;
    }

    $trxNo = next_number('TRX', 'inventory_transactions', 'trx_number');
    db_exec("INSERT INTO inventory_transactions
        (trx_number, trx_date, trx_type, part_id, warehouse_id, batch_id, qty_in, qty_out,
         balance_after, reference_number, user_id, remarks)
        VALUES (?,?,?,?,?,?,?,0,?,?,?,?)",
        array($trxNo, $trx_date, $trx_type, (int)$part_id, (int)$warehouse_id, $batchId,
              $qty, $newQty, $ref_number,
              isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $remarks));

    return $batchId;
}

function consume_from_batch($batch_id, $qty, $trx_type, $ref_number, $remarks, $trx_date = null) {
    $qty = (int)$qty;
    if ($qty <= 0) throw new Exception('Quantity must be greater than zero.');
    $b = db_one("SELECT * FROM inventory_batches WHERE id = ? FOR UPDATE", array((int)$batch_id));
    if (!$b) throw new Exception('Batch not found.');
    if ((int)$b['qty_remaining'] < $qty) throw new Exception('Insufficient quantity in batch ' . $b['batch_code'] . '.');
    $newQty = (int)$b['qty_remaining'] - $qty;
    db_exec("UPDATE inventory_batches SET qty_remaining = ? WHERE id = ?", array($newQty, (int)$batch_id));

    if ($trx_date === null) $trx_date = date('Y-m-d H:i:s');
    $trxNo = next_number('TRX', 'inventory_transactions', 'trx_number');
    db_exec("INSERT INTO inventory_transactions
        (trx_number, trx_date, trx_type, part_id, warehouse_id, batch_id, qty_in, qty_out,
         balance_after, reference_number, user_id, remarks)
        VALUES (?,?,?,?,?,?,0,?,?,?,?,?)",
        array($trxNo, $trx_date, $trx_type, (int)$b['part_id'], (int)$b['warehouse_id'],
              (int)$batch_id, $qty, $newQty, $ref_number,
              isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $remarks));

    return array(
        'batch_id'        => (int)$b['id'],
        'batch_code'      => $b['batch_code'],
        'qty'             => $qty,
        'purchase_cost'   => (float)$b['purchase_cost'],
        'expiration_date' => $b['expiration_date'],
        'balance_after'   => $newQty
    );
}

/* FEFO allocation: reserves & decrements across earliest-expiring batches */
function consume_stock_fefo($part_id, $warehouse_id, $qty, $trx_type, $ref_number,
                            $remarks, $allow_expired = false, $trx_date = null) {
    $qty = (int)$qty;
    if ($qty <= 0) throw new Exception('Quantity must be greater than zero.');
    $today = date('Y-m-d');

    $sql = "SELECT id, batch_code, qty_remaining, purchase_cost, expiration_date
            FROM inventory_batches
            WHERE part_id = ? AND warehouse_id = ? AND qty_remaining > 0 AND status = 'ACTIVE'";
    $params = array((int)$part_id, (int)$warehouse_id);
    if (!$allow_expired) { $sql .= " AND expiration_date > ?"; $params[] = $today; }
    $sql .= " ORDER BY expiration_date ASC, id ASC FOR UPDATE";

    $batches = db_all($sql, $params);
    $available = 0;
    foreach ($batches as $b) { $available += (int)$b['qty_remaining']; }
    if ($available < $qty) {
        throw new Exception('Insufficient available stock. Requested ' . $qty . ', available ' . $available . '.');
    }

    $allocations = array();
    $remaining = $qty;
    foreach ($batches as $b) {
        if ($remaining <= 0) break;
        $take = min($remaining, (int)$b['qty_remaining']);
        $newQty = (int)$b['qty_remaining'] - $take;
        db_exec("UPDATE inventory_batches SET qty_remaining = ? WHERE id = ?", array($newQty, (int)$b['id']));

        if ($trx_date === null) $trx_date = date('Y-m-d H:i:s');
        $trxNo = next_number('TRX', 'inventory_transactions', 'trx_number');
        db_exec("INSERT INTO inventory_transactions
            (trx_number, trx_date, trx_type, part_id, warehouse_id, batch_id, qty_in, qty_out,
             balance_after, reference_number, user_id, remarks)
            VALUES (?,?,?,?,?,?,0,?,?,?,?,?)",
            array($trxNo, $trx_date, $trx_type, (int)$part_id, (int)$warehouse_id,
                  (int)$b['id'], $take, $newQty, $ref_number,
                  isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, $remarks));

        $allocations[] = array(
            'batch_id'        => (int)$b['id'],
            'batch_code'      => $b['batch_code'],
            'qty'             => $take,
            'purchase_cost'   => (float)$b['purchase_cost'],
            'expiration_date' => $b['expiration_date'],
            'balance_after'   => $newQty
        );
        $remaining -= $take;
    }
    return $allocations;
}

function stock_on_hand($part_id, $warehouse_id = null) {
    if ($warehouse_id === null) {
        return (int) db_val("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_batches WHERE part_id = ?",
                            array((int)$part_id));
    }
    return (int) db_val("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_batches
                         WHERE part_id = ? AND warehouse_id = ?",
                        array((int)$part_id, (int)$warehouse_id));
}

/* ---------- CSV ---------- */
function csv_download($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    if ($headers) fputcsv($out, $headers);
    foreach ($rows as $r) { fputcsv($out, $r); }
    fclose($out);
    exit;
}

/* ---------- Pagination ---------- */
function paginate($total, $page, $perPage) {
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page < 1) $page = 1;
    if ($page > $pages) $page = $pages;
    return array('page' => $page, 'pages' => $pages,
                 'offset' => ($page - 1) * $perPage, 'per_page' => $perPage, 'total' => $total);
}

function render_pager($p, $baseQuery) {
    if ($p['pages'] <= 1) return;
    echo '<div class="pager">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 15 && abs($i - $p['page']) > 3 && $i != 1 && $i != $p['pages']) {
            if (abs($i - $p['page']) == 4) echo '<span>…</span>';
            continue;
        }
        $q = $baseQuery . '&page=' . $i;
        $cls = ($i == $p['page']) ? 'pg pg-on' : 'pg';
        echo '<a class="' . $cls . '" href="?' . e($q) . '">' . $i . '</a>';
    }
    echo '</div>';
}