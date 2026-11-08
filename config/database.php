<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory');

function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$conn) {
            die('Database connection failed: ' . mysqli_connect_error()
              . '<br>Import database/inventory.sql in phpMyAdmin first.');
        }
        mysqli_set_charset($conn, 'utf8');
    }
    return $conn;
}