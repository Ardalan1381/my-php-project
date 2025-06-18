<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$room_type_id = filter_input(INPUT_GET, 'room_type_id', FILTER_VALIDATE_INT);

if (!$room_type_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه نوع اتاق نامعتبر است!']);
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

// بررسی اینکه اتاق متعلق به هتلی باشه که این هتل‌دار مالکش هست
$stmt = $db->prepare("SELECT rt.hotel_id, h.hotel_owner 
                      FROM Room_Types rt 
                      JOIN Hotels h ON rt.hotel_id = h.hotel_id 
                      WHERE rt.room_type_id = :room_type_id");
$stmt->execute(['room_type_id' => $room_type_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room || $room['hotel_owner'] !== $username) {
    echo json_encode(['success' => false, 'message' => 'شما اجازه دسترسی به این نوع اتاق را ندارید!']);
    exit;
}

// گرفتن تصاویر
$stmt = $db->prepare("SELECT image_id, image_path FROM Room_Images WHERE room_type_id = :room_type_id");
$stmt->execute(['room_type_id' => $room_type_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($images);
?>