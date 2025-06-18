<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Moderator') {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT username, role FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$hotel_id = $_GET['id'] ?? null;
if (!$hotel_id) {
    header("Location: /site/admin/hotels.php");
    exit;
}

// چک کردن وجود هتل
$stmt = $db->prepare("SELECT hotel_name FROM Hotels WHERE hotel_id = :hotel_id");
$stmt->execute(['hotel_id' => $hotel_id]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hotel) {
    header("Location: /site/admin/hotels.php");
    exit;
}

// حذف هتل
$stmt = $db->prepare("DELETE FROM Hotels WHERE hotel_id = :hotel_id");
$stmt->execute(['hotel_id' => $hotel_id]);

// ریدایرکت به صفحه هتل‌ها با پیام موفقیت
header("Location: /site/admin/hotels.php?message=" . urlencode("هتل '{$hotel['hotel_name']}' با موفقیت حذف شد."));
exit;
?>