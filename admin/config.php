<?php
session_start();
$db_host = "localhost";
$db_user = "devhubte_astour"; // یوزرنیم دیتابیست رو بذار
$db_pass = "3527@qsgM"; // پسورد دیتابیست رو بذار
$db_name = "devhubte_astour";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>