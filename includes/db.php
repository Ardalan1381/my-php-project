<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO("mysql:host=localhost;dbname=devhubte_astour;charset=utf8", "devhubte_astour", "3527@qsgM");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("خطای اتصال به دیتابیس: " . $e->getMessage());
}
?>
