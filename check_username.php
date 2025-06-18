<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING);
file_put_contents('debug.log', "Checking username: $username\n", FILE_APPEND); // لاگ
$stmt = $db->prepare("SELECT user_id FROM Users WHERE username = :username");
$stmt->execute(['username' => $username]);
$exists = $stmt->fetch() ? true : false;

echo json_encode(['exists' => $exists]);
?>