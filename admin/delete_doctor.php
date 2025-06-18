<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Moderator') {
    header("Location: /site/login.php");
    exit;
}

$doctor_id = $_GET['id'] ?? null;
if ($doctor_id) {
    $stmt = $db->prepare("DELETE FROM Doctors WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctor_id]);
}

header("Location: /site/admin/doctors.php");
exit;
?>