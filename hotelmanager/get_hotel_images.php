<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید!']);
    exit;
}

$user_id = $_SESSION['user_id'];
$hotel_id = filter_input(INPUT_GET, 'hotel_id', FILTER_VALIDATE_INT);

if (!$hotel_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه هتل نامعتبر است!']);
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

// بررسی اینکه هتل متعلق به این هتل‌دار باشه
$stmt = $db->prepare("SELECT hotel_owner FROM Hotels WHERE hotel_id = :hotel_id");
$stmt->execute(['hotel_id' => $hotel_id]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hotel || $hotel['hotel_owner'] !== $username) {
    echo json_encode(['success' => false, 'message' => 'شما اجازه دسترسی به این هتل را ندارید!']);
    exit;
}

// گرفتن تصاویر
$stmt = $db->prepare("SELECT image_id, image_path FROM Hotel_Images WHERE hotel_id = :hotel_id");
$stmt->execute(['hotel_id' => $hotel_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($images);
?>