<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Moderator') {
    header("Location: /site/login.php");
    exit;
}

$hospital_id = $_GET['id'] ?? null;
if ($hospital_id) {
    $stmt = $db->prepare("DELETE FROM Hospitals WHERE hospital_id = :hospital_id");
    $stmt->execute(['hospital_id' => $hospital_id]);
}

header("Location: /site/admin/hospitals.php");
exit;
?>