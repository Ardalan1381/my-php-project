<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$image_id = filter_input(INPUT_POST, 'image_id', FILTER_VALIDATE_INT);
$room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);

if (!$image_id || !$room_type_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه تصویر یا نوع اتاق نامعتبر است!']);
    exit;
}

// بررسی نقش کاربر و گرفتن نام کاربری
$stmt = $db->prepare("SELECT username, role FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'hotelier') {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز!']);
    exit;
}
$username = $user['username'];

// بررسی اینکه تصویر متعلق به هتلی باشه که این هتل‌دار مالکش هست
$stmt = $db->prepare("SELECT rt.hotel_id, h.hotel_owner 
                      FROM Room_Images ri 
                      JOIN Room_Types rt ON ri.room_type_id = rt.room_type_id 
                      JOIN Hotels h ON rt.hotel_id = h.hotel_id 
                      WHERE ri.image_id = :image_id AND ri.room_type_id = :room_type_id");
$stmt->execute(['image_id' => $image_id, 'room_type_id' => $room_type_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || $result['hotel_owner'] !== $username) {
    echo json_encode(['success' => false, 'message' => 'شما اجازه حذف این تصویر را ندارید!']);
    exit;
}

// گرفتن مسیر تصویر برای حذف فایل
$stmt = $db->prepare("SELECT image_path FROM Room_Images WHERE image_id = :image_id");
$stmt->execute(['image_id' => $image_id]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if ($image) {
    $file_path = __DIR__ . '/../../' . $image['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// حذف تصویر از دیتابیس
$stmt = $db->prepare("DELETE FROM Room_Images WHERE image_id = :image_id");
$stmt->execute(['image_id' => $image_id]);

echo json_encode(['success' => true]);
?>