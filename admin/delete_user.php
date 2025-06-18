<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'Moderator'])) {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT username, role FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$target_user_id = $_GET['id'] ?? null;
if (!$target_user_id) {
    header("Location: /site/admin/users.php");
    exit;
}

// جلوگیری از حذف خود کاربر
if ($target_user_id == $user_id) {
    header("Location: /site/admin/users.php?error=" . urlencode("نمی‌توانید خودتان را حذف کنید!"));
    exit;
}

// چک کردن وجود کاربر
$stmt = $db->prepare("SELECT username FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $target_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: /site/admin/users.php");
    exit;
}

// حذف کاربر
$stmt = $db->prepare("DELETE FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $target_user_id]);

// ریدایرکت به صفحه کاربران با پیام موفقیت
header("Location: /site/admin/users.php?message=" . urlencode("کاربر '{$user['username']}' با موفقیت حذف شد."));
exit;
?>