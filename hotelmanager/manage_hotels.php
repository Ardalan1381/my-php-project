<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

// فعال کردن لاگ‌ها برای دیباگ
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

function debug_log($message) {
    $log_dir = __DIR__ . '/../../logs';
    $log_file = $log_dir . '/php_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    if (is_writable($log_dir)) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $log_file);
    }
}

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    debug_log("User not logged in");
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> لطفاً وارد شوید!</p>';
    header('Location: /site/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// بررسی نقش کاربر و گرفتن نام کاربری
try {
    $stmt = $db->prepare("SELECT username, role FROM Users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'hotelier') {
        debug_log("Access denied: User ID $user_id is not a hotelier");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> دسترسی غیرمجاز! فقط هتل‌داران می‌توانند به این صفحه دسترسی داشته باشند.</p>';
        header('Location: /site/dashboard.php');
        exit;
    }
    $username = $user['username'];
} catch (Exception $e) {
    debug_log("Error checking user role: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بررسی نقش کاربر: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// پردازش درخواست ویرایش هتل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_hotel'])) {
    try {
        $hotel_id = filter_input(INPUT_POST, 'hotel_id', FILTER_VALIDATE_INT);
        $hotel_name = filter_input(INPUT_POST, 'hotel_name', FILTER_SANITIZE_STRING);
        $hotel_phone = filter_input(INPUT_POST, 'hotel_phone', FILTER_SANITIZE_STRING);
        $hotel_star = filter_input(INPUT_POST, 'hotel_star', FILTER_VALIDATE_INT);
        $hotel_province = filter_input(INPUT_POST, 'hotel_province', FILTER_SANITIZE_STRING);
        $hotel_country = filter_input(INPUT_POST, 'hotel_country', FILTER_SANITIZE_STRING);
        $hotel_city = filter_input(INPUT_POST, 'hotel_city', FILTER_SANITIZE_STRING);
        $hotel_publicrelations = filter_input(INPUT_POST, 'hotel_publicrelations', FILTER_SANITIZE_STRING);
        $hotel_address = filter_input(INPUT_POST, 'hotel_address', FILTER_SANITIZE_STRING);
        $price_per_night = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT);
        $hotel_capacity = filter_input(INPUT_POST, 'hotel_capacity', FILTER_VALIDATE_INT);
        $hotel_proposed = filter_input(INPUT_POST, 'hotel_proposed', FILTER_SANITIZE_STRING);

        if (!$hotel_id || !$hotel_name || !$hotel_province || !$hotel_country || !$hotel_city || !$hotel_publicrelations || !$hotel_address || $price_per_night === false || !$hotel_proposed) {
            throw new Exception("لطفاً تمام فیلدها را به درستی پر کنید!");
        }
        if (!in_array($hotel_proposed, ['Recommended', 'Not recommended'])) {
            throw new Exception("وضعیت پیشنهادی نامعتبر است!");
        }

        // بررسی مالکیت هتل
        $stmt = $db->prepare("SELECT hotel_owner, hotel_star, hotel_image FROM Hotels WHERE hotel_id = :hotel_id");
        $stmt->execute(['hotel_id' => $hotel_id]);
        $hotel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hotel || $hotel['hotel_owner'] !== $username) {
            throw new Exception("شما اجازه ویرایش این هتل را ندارید!");
        }

        // استفاده از تعداد ستاره‌های فعلی هتل (کاربر نمی‌تواند آن را تغییر دهد)
        $hotel_star = $hotel['hotel_star'];

        // آپلود تصویر جدید (در صورت وجود)
        $hotel_image = $hotel['hotel_image'];
        if (isset($_FILES['hotel_image']) && $_FILES['hotel_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../assets/image/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $image_name = 'Hotel_' . time() . '.' . pathinfo($_FILES['hotel_image']['name'], PATHINFO_EXTENSION);
            $image_path = $upload_dir . $image_name;
            if (!move_uploaded_file($_FILES['hotel_image']['tmp_name'], $image_path)) {
                throw new Exception("خطا در آپلود تصویر!");
            }
            $hotel_image = '/site/assets/image/' . $image_name;
        }

        // ثبت درخواست ویرایش در جدول Hotel_Edit_Requests
        $stmt = $db->prepare("INSERT INTO Hotel_Edit_Requests (
            hotel_id, hotel_name, hotel_phone, hotel_star, hotel_province, hotel_country, hotel_city, 
            hotel_publicrelations, hotel_address, price_per_night, hotel_image, hotel_capacity, hotel_proposed, requested_by
        ) VALUES (
            :hotel_id, :hotel_name, :hotel_phone, :hotel_star, :hotel_province, :hotel_country, :hotel_city, 
            :hotel_publicrelations, :hotel_address, :price_per_night, :hotel_image, :hotel_capacity, :hotel_proposed, :requested_by
        )");
        $stmt->execute([
            'hotel_id' => $hotel_id,
            'hotel_name' => $hotel_name,
            'hotel_phone' => $hotel_phone,
            'hotel_star' => $hotel_star,
            'hotel_province' => $hotel_province,
            'hotel_country' => $hotel_country,
            'hotel_city' => $hotel_city,
            'hotel_publicrelations' => $hotel_publicrelations,
            'hotel_address' => $hotel_address,
            'price_per_night' => $price_per_night,
            'hotel_image' => $hotel_image,
            'hotel_capacity' => $hotel_capacity !== false ? $hotel_capacity : null,
            'hotel_proposed' => $hotel_proposed,
            'requested_by' => $username
        ]);

        $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> درخواست ویرایش هتل با موفقیت ارسال شد و در انتظار تأیید ادمین است!</div>';
        header('Location: /site/hotelmanager/manage_hotels.php');
        exit;
    } catch (Exception $e) {
        debug_log("Error submitting hotel edit request: " . $e->getMessage());
        $_SESSION['error_message'] = '<div class="bg-red-900 bg-opacity-50 text-red-300 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در ارسال درخواست ویرایش: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// پردازش حذف هتل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_hotel'])) {
    try {
        $hotel_id = filter_input(INPUT_POST, 'hotel_id', FILTER_VALIDATE_INT);

        if (!$hotel_id) {
            throw new Exception("شناسه هتل نامعتبر است!");
        }

        $stmt = $db->prepare("SELECT hotel_owner FROM Hotels WHERE hotel_id = :hotel_id");
        $stmt->execute(['hotel_id' => $hotel_id]);
        $hotel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hotel || $hotel['hotel_owner'] !== $username) {
            throw new Exception("شما اجازه حذف این هتل را ندارید!");
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM Reservations WHERE hotel_id = :hotel_id");
        $stmt->execute(['hotel_id' => $hotel_id]);
        $reservation_count = $stmt->fetchColumn();

        if ($reservation_count > 0) {
            throw new Exception("نمی‌توانید این هتل را حذف کنید، زیرا رزروهایی برای آن وجود دارد!");
        }

        $stmt = $db->prepare("DELETE FROM Room_Types WHERE hotel_id = :hotel_id");
        $stmt->execute(['hotel_id' => $hotel_id]);

        $stmt = $db->prepare("DELETE ri FROM Room_Images ri 
                              JOIN Room_Types rt ON ri.room_type_id = rt.room_type_id 
                              WHERE rt.hotel_id = :hotel_id");
        $stmt->execute(['hotel_id' => $hotel_id]);

        $stmt = $db->prepare("DELETE FROM Hotels WHERE hotel_id = :hotel_id AND hotel_owner = :hotel_owner");
        $stmt->execute(['hotel_id' => $hotel_id, 'hotel_owner' => $username]);

        $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> هتل با موفقیت حذف شد!</div>';
        header('Location: /site/hotelmanager/manage_hotels.php');
        exit;
    } catch (Exception $e) {
        debug_log("Error deleting hotel: " . $e->getMessage());
        $_SESSION['error_message'] = '<div class="bg-red-900 bg-opacity-50 text-red-300 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در حذف هتل: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// پردازش اضافه کردن نوع اتاق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_room_type'])) {
    try {
        $hotel_id = filter_input(INPUT_POST, 'hotel_id', FILTER_VALIDATE_INT);
        $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_STRING);
        $room_capacity = filter_input(INPUT_POST, 'room_capacity', FILTER_VALIDATE_INT);
        $available_count = filter_input(INPUT_POST, 'available_count', FILTER_VALIDATE_INT);
        $room_price = filter_input(INPUT_POST, 'room_price', FILTER_VALIDATE_FLOAT);
        $extra_guest_price = filter_input(INPUT_POST, 'extra_guest_price', FILTER_VALIDATE_FLOAT);

        if (!$hotel_id || !$room_type || $room_capacity === false || $available_count === false || $room_price === false || $extra_guest_price === false) {
            throw new Exception("لطفاً تمام فیلدها را به درستی پر کنید!");
        }

        $stmt = $db->prepare("SELECT hotel_owner FROM Hotels WHERE hotel_id = :hotel_id");
        $stmt->execute(['hotel_id' => $hotel_id]);
        $hotel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hotel || $hotel['hotel_owner'] !== $username) {
            throw new Exception("شما اجازه اضافه کردن اتاق به این هتل را ندارید!");
        }

        $stmt = $db->prepare("INSERT INTO Room_Types (hotel_id, room_type, room_capacity, available_count, room_price, extra_guest_price) 
                              VALUES (:hotel_id, :room_type, :room_capacity, :available_count, :room_price, :extra_guest_price)");
        $stmt->execute([
            'hotel_id' => $hotel_id,
            'room_type' => $room_type,
            'room_capacity' => $room_capacity,
            'available_count' => $available_count,
            'room_price' => $room_price,
            'extra_guest_price' => $extra_guest_price
        ]);

        $room_type_id = $db->lastInsertId();
        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/rooms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $image_name = 'Room_' . time() . '.' . pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION);
            $image_path = $upload_dir . $image_name;
            if (!move_uploaded_file($_FILES['room_image']['tmp_name'], $image_path)) {
                throw new Exception("خطا در آپلود تصویر اتاق!");
            }
            $room_image_path = '/site/uploads/rooms/' . $image_name;
            $stmt = $db->prepare("INSERT INTO Room_Images (room_type_id, image_path) VALUES (:room_type_id, :image_path)");
            $stmt->execute(['room_type_id' => $room_type_id, 'image_path' => $room_image_path]);
        }

        $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> نوع اتاق با موفقیت اضافه شد!</div>';
        header('Location: /site/hotelmanager/manage_hotels.php');
        exit;
    } catch (Exception $e) {
        debug_log("Error adding room type: " . $e->getMessage());
        $_SESSION['error_message'] = '<div class="bg-red-900 bg-opacity-50 text-red-300 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در اضافه کردن نوع اتاق: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// پردازش ویرایش نوع اتاق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_room_type'])) {
    try {
        $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
        $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_STRING);
        $room_capacity = filter_input(INPUT_POST, 'room_capacity', FILTER_VALIDATE_INT);
        $available_count = filter_input(INPUT_POST, 'available_count', FILTER_VALIDATE_INT);
        $room_price = filter_input(INPUT_POST, 'room_price', FILTER_VALIDATE_FLOAT);
        $extra_guest_price = filter_input(INPUT_POST, 'extra_guest_price', FILTER_VALIDATE_FLOAT);

        if (!$room_type_id || !$room_type || $room_capacity === false || $available_count === false || $room_price === false || $extra_guest_price === false) {
            throw new Exception("لطفاً تمام فیلدها را به درستی پر کنید!");
        }

        $stmt = $db->prepare("SELECT rt.hotel_id, h.hotel_owner 
                              FROM Room_Types rt 
                              JOIN Hotels h ON rt.hotel_id = h.hotel_id 
                              WHERE rt.room_type_id = :room_type_id");
        $stmt->execute(['room_type_id' => $room_type_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room || $room['hotel_owner'] !== $username) {
            throw new Exception("شما اجازه ویرایش این نوع اتاق را ندارید!");
        }

        $stmt = $db->prepare("UPDATE Room_Types 
                              SET room_type = :room_type, room_capacity = :room_capacity, available_count = :available_count, 
                                  room_price = :room_price, extra_guest_price = :extra_guest_price 
                              WHERE room_type_id = :room_type_id");
        $stmt->execute([
            'room_type_id' => $room_type_id,
            'room_type' => $room_type,
            'room_capacity' => $room_capacity,
            'available_count' => $available_count,
            'room_price' => $room_price,
            'extra_guest_price' => $extra_guest_price
        ]);

        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/rooms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $image_name = 'Room_' . time() . '.' . pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION);
            $image_path = $upload_dir . $image_name;
            if (!move_uploaded_file($_FILES['room_image']['tmp_name'], $image_path)) {
                throw new Exception("خطا در آپلود تصویر اتاق!");
            }
            $room_image_path = '/site/uploads/rooms/' . $image_name;

            $stmt = $db->prepare("DELETE FROM Room_Images WHERE room_type_id = :room_type_id");
            $stmt->execute(['room_type_id' => $room_type_id]);

            $stmt = $db->prepare("INSERT INTO Room_Images (room_type_id, image_path) VALUES (:room_type_id, :image_path)");
            $stmt->execute(['room_type_id' => $room_type_id, 'image_path' => $room_image_path]);
        }

        $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> نوع اتاق با موفقیت ویرایش شد!</div>';
        header('Location: /site/hotelmanager/manage_hotels.php');
        exit;
    } catch (Exception $e) {
        debug_log("Error editing room type: " . $e->getMessage());
        $_SESSION['error_message'] = '<div class="bg-red-900 bg-opacity-50 text-red-300 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در ویرایش نوع اتاق: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// پردازش حذف نوع اتاق
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_room_type'])) {
    try {
        $room_type_id = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);

        if (!$room_type_id) {
            throw new Exception("شناسه نوع اتاق نامعتبر است!");
        }

        $stmt = $db->prepare("SELECT rt.hotel_id, h.hotel_owner 
                              FROM Room_Types rt 
                              JOIN Hotels h ON rt.hotel_id = h.hotel_id 
                              WHERE rt.room_type_id = :room_type_id");
        $stmt->execute(['room_type_id' => $room_type_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room || $room['hotel_owner'] !== $username) {
            throw new Exception("شما اجازه حذف این نوع اتاق را ندارید!");
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM Reservations WHERE room_type_id = :room_type_id");
        $stmt->execute(['room_type_id' => $room_type_id]);
        $reservation_count = $stmt->fetchColumn();

        if ($reservation_count > 0) {
            throw new Exception("نمی‌توانید این نوع اتاق را حذف کنید، زیرا رزروهایی برای آن وجود دارد!");
        }

        $stmt = $db->prepare("DELETE FROM Room_Images WHERE room_type_id = :room_type_id");
        $stmt->execute(['room_type_id' => $room_type_id]);

        $stmt = $db->prepare("DELETE FROM Room_Types WHERE room_type_id = :room_type_id");
        $stmt->execute(['room_type_id' => $room_type_id]);

        $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> نوع اتاق با موفقیت حذف شد!</div>';
        header('Location: /site/hotelmanager/manage_hotels.php');
        exit;
    } catch (Exception $e) {
        debug_log("Error deleting room type: " . $e->getMessage());
        $_SESSION['error_message'] = '<div class="bg-red-900 bg-opacity-50 text-red-300 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در حذف نوع اتاق: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// بارگذاری هتل متعلق به هتل‌دار
try {
    $stmt = $db->prepare("SELECT hotel_id, hotel_name, hotel_phone, hotel_star, hotel_province, hotel_country, hotel_city, hotel_publicrelations, hotel_address, price_per_night, hotel_image, hotel_capacity, hotel_proposed 
                          FROM Hotels 
                          WHERE hotel_owner = :hotel_owner");
    $stmt->execute(['hotel_owner' => $username]);
    $hotel = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    debug_log("Error fetching hotel: " . $e->getMessage());
    $hotel = null;
}

// هدر صفحه
$page_title = "مدیریت هتل - آستور";
include __DIR__ . '/../header.php';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Vazir', sans-serif;
            background: linear-gradient(to bottom, #1a202c, #2d3748);
            min-height: 100vh;
            color: #e2e8f0;
        }

        /* انیمیشن‌ها */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        /* استایل برای مدال‌ها */
        #editHotelModal, #editRoomModal {
            opacity: 0;
            z-index: 50;
        }
        #editHotelModal.opacity-100, #editRoomModal.opacity-100 {
            opacity: 1;
        }
        .modal-content {
            max-height: 80vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #4a5568 #2d3748;
        }
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }
        .modal-content::-webkit-scrollbar-track {
            background: #2d3748;
        }
        .modal-content::-webkit-scrollbar-thumb {
            background: #4a5568;
            border-radius: 4px;
        }

        /* استایل برای جدول */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: center;
            padding: 0.75rem;
        }

        /* استایل برای دکمه‌ها */
        button, input[type="submit"] {
            transition: all 0.3s ease;
        }

        /* استایل برای کارت‌ها */
        .card {
            background: #2d3748;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }

        /* ریسپانسیو کردن */
        @media (max-width: 640px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            h3 {
                font-size: 1.25rem;
            }
            h4 {
                font-size: 1.125rem;
            }
            .modal-content {
                width: 90%;
                max-width: 100%;
                margin: 0 auto;
            }
            .card {
                padding: 1rem;
            }
            .text-sm {
                font-size: 0.875rem;
            }
            .p-6 {
                padding: 1.5rem;
            }
            .px-4 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .py-2 {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
        <div class="bg-gray-900 bg-opacity-80 backdrop-filter backdrop-blur-lg p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-700">
            <h2 class="text-xl sm:text-2xl font-bold text-white mb-4 sm:mb-6 flex items-center">
                <i class="fas fa-hotel mr-2 sm:mr-3 text-blue-400"></i> مدیریت هتل
            </h2>

            <!-- نمایش پیام‌های موفقیت یا خطا -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="animate-fade-in">
                    <?php echo $_SESSION['success_message']; ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="animate-fade-in">
                    <?php echo $_SESSION['error_message']; ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- نمایش اطلاعات هتل -->
            <div>
                <h3 class="text-lg sm:text-xl font-semibold text-white mb-3 sm:mb-4">هتل شما</h3>
                <?php if (!$hotel): ?>
                    <p class="text-gray-400 text-sm sm:text-base">شما هنوز هتلی ثبت نکرده‌اید. لطفاً با پشتیبانی تماس بگیرید.</p>
                <?php else: ?>
                    <div class="card p-4 sm:p-6 mb-4 sm:mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-4">
                                <?php if ($hotel['hotel_image']): ?>
                                    <img src="<?php echo htmlspecialchars($hotel['hotel_image']); ?>" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>" class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-lg">
                                <?php else: ?>
                                    <div class="w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center bg-gray-600 rounded-lg text-gray-400 text-xs sm:text-sm">بدون تصویر</div>
                                <?php endif; ?>
                                <div class="text-center sm:text-right">
                                    <p class="text-white font-bold text-lg sm:text-xl"><?php echo htmlspecialchars($hotel['hotel_name']); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base mt-1"><i class="fas fa-map-marker-alt mr-2"></i> آدرس: <?php echo htmlspecialchars($hotel['hotel_address']); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-globe mr-2"></i> موقعیت: <?php echo htmlspecialchars($hotel['hotel_city'] . '، ' . $hotel['hotel_province'] . '، ' . $hotel['hotel_country']); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-phone mr-2"></i> شماره تماس: <?php echo htmlspecialchars($hotel['hotel_phone'] ?? 'نامشخص'); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-star mr-2"></i> ستاره‌ها: <?php echo htmlspecialchars($hotel['hotel_star'] ?? 'نامشخص'); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-user-tie mr-2"></i> روابط عمومی: <?php echo htmlspecialchars($hotel['hotel_publicrelations']); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-money-bill-wave mr-2"></i> قیمت هر شب: <?php echo number_format($hotel['price_per_night']); ?> تومان</p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-users mr-2"></i> ظرفیت: <?php echo htmlspecialchars($hotel['hotel_capacity'] ?? 'نامشخص'); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><i class="fas fa-thumbs-up mr-2"></i> وضعیت: <?php echo $hotel['hotel_proposed'] === 'Recommended' ? 'توصیه‌شده' : 'توصیه‌نشده'; ?></p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                <?php
                                debug_log("Hotel data for onclick: " . json_encode([
                                    'hotel_id' => $hotel['hotel_id'],
                                    'hotel_name' => $hotel['hotel_name'],
                                    'hotel_phone' => $hotel['hotel_phone'],
                                    'hotel_star' => $hotel['hotel_star'],
                                    'hotel_province' => $hotel['hotel_province'],
                                    'hotel_country' => $hotel['hotel_country'],
                                    'hotel_city' => $hotel['hotel_city'],
                                    'hotel_publicrelations' => $hotel['hotel_publicrelations'],
                                    'hotel_address' => $hotel['hotel_address'],
                                    'price_per_night' => $hotel['price_per_night'],
                                    'hotel_capacity' => $hotel['hotel_capacity'],
                                    'hotel_proposed' => $hotel['hotel_proposed']
                                ]));
                                ?>
                                <button onclick="openEditModal(<?php echo $hotel['hotel_id']; ?>, '<?php echo htmlspecialchars($hotel['hotel_name']); ?>', '<?php echo htmlspecialchars($hotel['hotel_phone']); ?>', <?php echo $hotel['hotel_star'] ?? 'null'; ?>, '<?php echo htmlspecialchars($hotel['hotel_province']); ?>', '<?php echo htmlspecialchars($hotel['hotel_country']); ?>', '<?php echo htmlspecialchars($hotel['hotel_city']); ?>', '<?php echo htmlspecialchars($hotel['hotel_publicrelations']); ?>', '<?php echo htmlspecialchars($hotel['hotel_address']); ?>', <?php echo $hotel['price_per_night']; ?>, '<?php echo htmlspecialchars($hotel['hotel_image'] ?? ''); ?>', <?php echo $hotel['hotel_capacity'] ?? 'null'; ?>, '<?php echo $hotel['hotel_proposed']; ?>')" class="bg-yellow-600 text-white px-3 py-2 rounded-lg hover:bg-yellow-700 transition-all duration-300 flex items-center justify-center"><i class="fas fa-edit mr-2"></i> ویرایش</button>
                                <form method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این هتل را حذف کنید؟')">
                                    <input type="hidden" name="hotel_id" value="<?php echo $hotel['hotel_id']; ?>">
                                    <button type="submit" name="delete_hotel" class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-all duration-300 flex items-center justify-center"><i class="fas fa-trash mr-2"></i> حذف</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- فرم اضافه کردن نوع اتاق -->
                    <div class="card p-4 sm:p-6 mb-4 sm:mb-6">
                        <h4 class="text-base sm:text-lg font-semibold text-white mb-3 sm:mb-4 flex items-center"><i class="fas fa-plus-circle mr-2 text-blue-400"></i> اضافه کردن نوع اتاق</h4>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="hotel_id" value="<?php echo $hotel['hotel_id']; ?>">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-300 text-sm font-medium mb-2">نوع اتاق</label>
                                    <input type="text" name="room_type" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-sm font-medium mb-2">ظرفیت اتاق</label>
                                    <input type="number" name="room_capacity" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-sm font-medium mb-2">تعداد اتاق‌های موجود</label>
                                    <input type="number" name="available_count" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-sm font-medium mb-2">قیمت (تومان)</label>
                                    <input type="number" name="room_price" step="0.01" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                <div>
                                    <label class="block text-gray-300 text-sm font-medium mb-2">قیمت مهمان اضافی (تومان)</label>
                                    <input type="number" name="extra_guest_price" step="0.01" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                <div class="col-span-1 sm:col-span-2 lg:col-span-1">
                                    <label class="block text-gray-300 text-sm font-medium mb-2">تصویر اتاق</label>
                                    <input type="file" name="room_image" accept="image/*" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="add_room_type" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-all duration-300 flex items-center"><i class="fas fa-plus mr-2"></i> اضافه کردن</button>
                            </div>
                        </form>
                    </div>

                    <!-- لیست نوع اتاق‌ها -->
                    <div class="card p-4 sm:p-6">
                        <h4 class="text-base sm:text-lg font-semibold text-white mb-3 sm:mb-4 flex items-center"><i class="fas fa-list mr-2 text-blue-400"></i> نوع اتاق‌ها</h4>
                        <?php
                        $stmt = $db->prepare("SELECT rt.room_type_id, rt.room_type, rt.room_capacity, rt.available_count, rt.room_price, rt.extra_guest_price, ri.image_path 
                                              FROM Room_Types rt 
                                              LEFT JOIN Room_Images ri ON rt.room_type_id = ri.room_type_id 
                                              WHERE rt.hotel_id = :hotel_id");
                        $stmt->execute(['hotel_id' => $hotel['hotel_id']]);
                        $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if (empty($room_types)): ?>
                            <p class="text-gray-400 text-sm sm:text-base">هنوز نوع اتاقی اضافه نشده است.</p>
                        <?php else: ?>
                            <!-- جدول برای دسکتاپ -->
                            <div class="hidden md:block overflow-x-auto">
                                <table class="w-full text-gray-300 text-sm border-collapse">
                                    <thead>
                                        <tr class="bg-gray-700">
                                            <th class="border border-gray-600 p-2 sm:p-3">تصویر</th>
                                            <th class="border border-gray-600 p-2 sm:p-3">نوع اتاق</th>
                                            <th class="border border-gray-600 p-2 sm:p-3">ظرفیت</th>
                                            <th class="border border-gray-600 p-2 sm:p-3">تعداد موجود</th>
                                            <th class="border border-gray-600 p-2 sm:p-3">قیمت (تومان)</th>
                                            <th class="border border-gray-600 p-2 sm:p-3">قیمت مهمان اضافی (تومان)</th>
                                            <th class="border border-gray-600 p-2 sm:p-3">عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($room_types as $room): ?>
                                            <?php
                                            debug_log("Room data for onclick: " . json_encode([
                                                'room_type_id' => $room['room_type_id'],
                                                'room_type' => $room['room_type'],
                                                'room_capacity' => $room['room_capacity'],
                                                'available_count' => $room['available_count'],
                                                'room_price' => $room['room_price'],
                                                'extra_guest_price' => $room['extra_guest_price'],
                                                'images' => $room['image_path'] ? [['image_id' => 1, 'image_path' => $room['image_path']]] : []
                                            ]));
                                            ?>
                                            <tr class="hover:bg-gray-600 transition-colors">
                                                <td class="border border-gray-600 p-2 sm:p-3">
                                                    <?php if ($room['image_path']): ?>
                                                        <img src="<?php echo htmlspecialchars($room['image_path']); ?>" alt="<?php echo htmlspecialchars($room['room_type']); ?>" class="w-10 h-10 sm:w-12 sm:h-12 object-cover rounded-lg mx-auto">
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs sm:text-sm">بدون تصویر</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="border border-gray-600 p-2 sm:p-3"><?php echo htmlspecialchars($room['room_type']); ?></td>
                                                <td class="border border-gray-600 p-2 sm:p-3"><?php echo $room['room_capacity']; ?></td>
                                                <td class="border border-gray-600 p-2 sm:p-3"><?php echo $room['available_count']; ?></td>
                                                <td class="border border-gray-600 p-2 sm:p-3"><?php echo number_format($room['room_price']); ?></td>
                                                <td class="border border-gray-600 p-2 sm:p-3"><?php echo number_format($room['extra_guest_price']); ?></td>
                                                <td class="border border-gray-600 p-2 sm:p-3">
                                                    <div class="flex space-x-2 justify-center">
                                                        <button onclick="openEditRoomModal(<?php echo $room['room_type_id']; ?>, '<?php echo htmlspecialchars($room['room_type']); ?>', <?php echo $room['room_capacity']; ?>, <?php echo $room['available_count']; ?>, <?php echo $room['room_price']; ?>, <?php echo $room['extra_guest_price']; ?>, '<?php echo htmlspecialchars($room['image_path'] ?? ''); ?>')" class="bg-yellow-600 text-white px-2 sm:px-3 py-1 rounded-lg hover:bg-yellow-700 transition-all duration-300"><i class="fas fa-edit"></i></button>
                                                        <form method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این نوع اتاق را حذف کنید؟')">
                                                            <input type="hidden" name="room_type_id" value="<?php echo $room['room_type_id']; ?>">
                                                            <button type="submit" name="delete_room_type" class="bg-red-600 text-white px-2 sm:px-3 py-1 rounded-lg hover:bg-red-700 transition-all duration-300"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- کارت‌ها برای موبایل -->
                            <div class="md:hidden space-y-3">
                                <?php foreach ($room_types as $room): ?>
                                    <div class="card p-3 sm:p-4">
                                        <div class="flex items-center space-x-3 sm:space-x-4">
                                            <?php if ($room['image_path']): ?>
                                                <img src="<?php echo htmlspecialchars($room['image_path']); ?>" alt="<?php echo htmlspecialchars($room['room_type']); ?>" class="w-12 h-12 sm:w-16 sm:h-16 object-cover rounded-lg">
                                            <?php else: ?>
                                                <div class="w-12 h-12 sm:w-16 sm:h-16 flex items-center justify-center bg-gray-600 rounded-lg text-gray-400 text-xs sm:text-sm">بدون تصویر</div>
                                            <?php endif; ?>
                                            <div class="flex-1">
                                                <p class="text-white font-semibold text-sm sm:text-base"><?php echo htmlspecialchars($room['room_type']); ?></p>
                                                <p class="text-gray-300 text-xs sm:text-sm">ظرفیت: <?php echo $room['room_capacity']; ?></p>
                                                <p class="text-gray-300 text-xs sm:text-sm">تعداد موجود: <?php echo $room['available_count']; ?></p>
                                                <p class="text-gray-300 text-xs sm:text-sm">قیمت: <?php echo number_format($room['room_price']); ?> تومان</p>
                                                <p class="text-gray-300 text-xs sm:text-sm">مهمان اضافی: <?php echo number_format($room['extra_guest_price']); ?> تومان</p>
                                            </div>
                                        </div>
                                        <div class="flex space-x-2 mt-3">
                                            <button onclick="openEditRoomModal(<?php echo $room['room_type_id']; ?>, '<?php echo htmlspecialchars($room['room_type']); ?>', <?php echo $room['room_capacity']; ?>, <?php echo $room['available_count']; ?>, <?php echo $room['room_price']; ?>, <?php echo $room['extra_guest_price']; ?>, '<?php echo htmlspecialchars($room['image_path'] ?? ''); ?>')" class="bg-yellow-600 text-white px-3 py-1 sm:px-4 sm:py-2 rounded-lg hover:bg-yellow-700 transition-all duration-300 flex-1 flex items-center justify-center text-xs sm:text-sm"><i class="fas fa-edit mr-1 sm:mr-2"></i> ویرایش</button>
                                            <form method="POST" class="flex-1" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این نوع اتاق را حذف کنید؟')">
                                                <input type="hidden" name="room_type_id" value="<?php echo $room['room_type_id']; ?>">
                                                <button type="submit" name="delete_room_type" class="w-full bg-red-600 text-white px-3 py-1 sm:px-4 sm:py-2 rounded-lg hover:bg-red-700 transition-all duration-300 flex items-center justify-center text-xs sm:text-sm"><i class="fas fa-trash mr-1 sm:mr-2"></i> حذف</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- مدال ویرایش هتل -->
    <div id="editHotelModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden transition-opacity duration-300">
        <div class="modal-content bg-gray-800 p-4 sm:p-6 rounded-2xl w-full max-w-md sm:max-w-2xl transform transition-all duration-300 scale-95">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg sm:text-xl font-semibold text-white flex items-center"><i class="fas fa-hotel mr-2 text-blue-400"></i> ویرایش هتل</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white transition-colors duration-200">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="hotel_id" id="edit_hotel_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">نام هتل</label>
                        <input type="text" name="hotel_name" id="edit_hotel_name" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">شماره تماس</label>
                        <input type="text" name="hotel_phone" id="edit_hotel_phone" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">تعداد ستاره‌ها (غیرقابل تغییر)</label>
                        <input type="number" name="hotel_star" id="edit_hotel_star" class="w-full p-2 sm:p-3 bg-gray-600 text-gray-400 border border-gray-600 rounded-lg text-sm sm:text-base" disabled>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">استان</label>
                        <input type="text" name="hotel_province" id="edit_hotel_province" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">کشور</label>
                        <input type="text" name="hotel_country" id="edit_hotel_country" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">شهر</label>
                        <input type="text" name="hotel_city" id="edit_hotel_city" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">روابط عمومی</label>
                        <input type="text" name="hotel_publicrelations" id="edit_hotel_publicrelations" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">ظرفیت هتل</label>
                        <input type="number" name="hotel_capacity" id="edit_hotel_capacity" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">آدرس</label>
                        <input type="text" name="hotel_address" id="edit_hotel_address" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">قیمت هر شب (تومان)</label>
                        <input type="number" name="price_per_night" id="edit_price_per_night" step="0.01" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">وضعیت پیشنهادی</label>
                        <select name="hotel_proposed" id="edit_hotel_proposed" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                            <option value="Recommended">توصیه‌شده</option>
                            <option value="Not recommended">توصیه‌نشده</option>
                        </select>
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">تصویر فعلی</label>
                        <img id="edit_hotel_image_preview" src="" alt="تصویر هتل" class="w-12 h-12 sm:w-16 sm:h-16 object-cover rounded-lg hidden">
                        <input type="hidden" id="edit_hotel_image" name="current_hotel_image">
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">تصویر جدید (اختیاری)</label>
                        <input type="file" name="hotel_image" accept="image/*" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-4 sm:mt-6">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 text-white px-3 sm:px-5 py-1 sm:py-2 rounded-lg hover:bg-gray-700 transition-all duration-300 flex items-center text-xs sm:text-sm"><i class="fas fa-times mr-1 sm:mr-2"></i> لغو</button>
                    <button type="submit" name="edit_hotel" class="bg-blue-600 text-white px-3 sm:px-5 py-1 sm:py-2 rounded-lg hover:bg-blue-700 transition-all duration-300 flex items-center text-xs sm:text-sm"><i class="fas fa-save mr-1 sm:mr-2"></i> ارسال درخواست</button>
                </div>
            </form>
        </div>
    </div>

    <!-- مدال ویرایش نوع اتاق -->
    <div id="editRoomModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden transition-opacity duration-300">
        <div class="modal-content bg-gray-800 p-4 sm:p-6 rounded-2xl w-full max-w-md sm:max-w-2xl transform transition-all duration-300 scale-95">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg sm:text-xl font-semibold text-white flex items-center"><i class="fas fa-bed mr-2 text-blue-400"></i> ویرایش نوع اتاق</h3>
                <button onclick="closeEditRoomModal()" class="text-gray-400 hover:text-white transition-colors duration-200">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="room_type_id" id="edit_room_type_id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">نوع اتاق</label>
                        <input type="text" name="room_type" id="edit_room_type" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">ظرفیت اتاق</label>
                        <input type="number" name="room_capacity" id="edit_room_capacity" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">تعداد اتاق‌های موجود</label>
                        <input type="number" name="available_count" id="edit_available_count" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div>
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">قیمت (تومان)</label>
                        <input type="number" name="room_price" id="edit_room_price" step="0.01" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">قیمت مهمان اضافی (تومان)</label>
                        <input type="number" name="extra_guest_price" id="edit_extra_guest_price" step="0.01" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" required>
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">تصویر فعلی</label>
                        <img id="edit_room_image_preview" src="" alt="تصویر اتاق" class="w-12 h-12 sm:w-16 sm:h-16 object-cover rounded-lg hidden">
                        <input type="hidden" id="edit_room_image" name="current_room_image">
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label class="block text-gray-300 text-xs sm:text-sm font-medium mb-2">تصویر جدید (اختیاری)</label>
                        <input type="file" name="room_image" accept="image/*" class="w-full p-2 sm:p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                    </div>
                </div>
                <div class="flex justify-end space-x-2 mt-4 sm:mt-6">
                    <button type="button" onclick="closeEditRoomModal()" class="bg-gray-600 text-white px-3 sm:px-5 py-1 sm:py-2 rounded-lg hover:bg-gray-700 transition-all duration-300 flex items-center text-xs sm:text-sm"><i class="fas fa-times mr-1 sm:mr-2"></i> لغو</button>
                    <button type="submit" name="edit_room_type" class="bg-blue-600 text-white px-3 sm:px-5 py-1 sm:py-2 rounded-lg hover:bg-blue-700 transition-all duration-300 flex items-center text-xs sm:text-sm"><i class="fas fa-save mr-1 sm:mr-2"></i> ذخیره</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(hotel_id, hotel_name, hotel_phone, hotel_star, hotel_province, hotel_country, hotel_city, hotel_publicrelations, hotel_address, price_per_night, hotel_image, hotel_capacity, hotel_proposed) {
        console.log('openEditModal called with:', { hotel_id, hotel_name, hotel_phone, hotel_star, hotel_province, hotel_country, hotel_city, hotel_publicrelations, hotel_address, price_per_night, hotel_image, hotel_capacity, hotel_proposed });

        const modal = document.getElementById('editHotelModal');
        if (!modal) {
            console.error('editHotelModal not found');
            return;
        }
        const modalContent = modal.querySelector('.modal-content');
        if (!modalContent) {
            console.error('Modal content (.modal-content) not found in editHotelModal');
            return;
        }

        document.getElementById('edit_hotel_id').value = hotel_id;
        document.getElementById('edit_hotel_name').value = hotel_name;
        document.getElementById('edit_hotel_phone').value = hotel_phone || '';
        document.getElementById('edit_hotel_star').value = hotel_star || '';
        document.getElementById('edit_hotel_province').value = hotel_province;
        document.getElementById('edit_hotel_country').value = hotel_country;
        document.getElementById('edit_hotel_city').value = hotel_city;
        document.getElementById('edit_hotel_publicrelations').value = hotel_publicrelations;
        document.getElementById('edit_hotel_address').value = hotel_address;
        document.getElementById('edit_price_per_night').value = price_per_night;
        if (hotel_image) {
            document.getElementById('edit_hotel_image_preview').src = hotel_image;
            document.getElementById('edit_hotel_image_preview').classList.remove('hidden');
        } else {
            document.getElementById('edit_hotel_image_preview').classList.add('hidden');
        }
        document.getElementById('edit_hotel_image').value = hotel_image || '';
        document.getElementById('edit_hotel_capacity').value = hotel_capacity || '';
        document.getElementById('edit_hotel_proposed').value = hotel_proposed;

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }, 10);
    }

    function closeEditModal() {
        console.log('closeEditModal called');
        const modal = document.getElementById('editHotelModal');
        if (!modal) {
            console.error('editHotelModal not found');
            return;
        }
        const modalContent = modal.querySelector('.modal-content');
        if (!modalContent) {
            console.error('Modal content (.modal-content) not found in editHotelModal');
            modal.classList.add('hidden');
            return;
        }

        modal.classList.remove('opacity-100');
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function openEditRoomModal(room_type_id, room_type, room_capacity, available_count, room_price, extra_guest_price, image_path) {
        console.log('openEditRoomModal called with:', { room_type_id, room_type, room_capacity, available_count, room_price, extra_guest_price, image_path });

        const modal = document.getElementById('editRoomModal');
        if (!modal) {
            console.error('editRoomModal not found');
            return;
        }
        const modalContent = modal.querySelector('.modal-content');
        if (!modalContent) {
            console.error('Modal content (.modal-content) not found in editRoomModal');
            return;
        }

        document.getElementById('edit_room_type_id').value = room_type_id;
        document.getElementById('edit_room_type').value = room_type;
        document.getElementById('edit_room_capacity').value = room_capacity;
        document.getElementById('edit_available_count').value = available_count;
        document.getElementById('edit_room_price').value = room_price;
        document.getElementById('edit_extra_guest_price').value = extra_guest_price;
        if (image_path) {
            document.getElementById('edit_room_image_preview').src = image_path;
            document.getElementById('edit_room_image_preview').classList.remove('hidden');
        } else {
            document.getElementById('edit_room_image_preview').classList.add('hidden');
        }
        document.getElementById('edit_room_image').value = image_path || '';

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }, 10);
    }

    function closeEditRoomModal() {
        console.log('closeEditRoomModal called');
        const modal = document.getElementById('editRoomModal');
        if (!modal) {
            console.error('editRoomModal not found');
            return;
        }
        const modalContent = modal.querySelector('.modal-content');
        if (!modalContent) {
            console.error('Modal content (.modal-content) not found in editRoomModal');
            modal.classList.add('hidden');
            return;
        }

        modal.classList.remove('opacity-100');
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // بستن مدال با کلیک روی پس‌زمینه
    document.getElementById('editHotelModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    document.getElementById('editRoomModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditRoomModal();
        }
    });

    // بستن مدال با کلید Esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
            closeEditRoomModal();
        }
    });
    </script>

    <?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>