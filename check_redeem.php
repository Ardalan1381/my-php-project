<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$code = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
$stmt = $db->prepare("SELECT amount FROM Redeem_Codes WHERE code = :code AND used_by IS NULL");
$stmt->execute(['code' => $code]);
$redeem = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['valid' => !!$redeem, 'amount' => $redeem['amount'] ?? 0]);
?>