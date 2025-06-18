<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

define('ALLOWED_ROLES', ['admin', 'Moderator']);
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ALLOWED_ROLES)) {
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

$username = $user['username'];
$role = $user['role'];

// پردازش تأیید یا رد درخواست ویرایش هتل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    try {
        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'];

        if (!$request_id || !in_array($action, ['approve', 'reject'])) {
            throw new Exception("درخواست یا عملیات نامعتبر است!");
        }

        // گرفتن اطلاعات درخواست
        $stmt = $db->prepare("SELECT * FROM Hotel_Edit_Requests WHERE request_id = :request_id AND request_status = 'Pending'");
        $stmt->execute(['request_id' => $request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            throw new Exception("درخواست یافت نشد یا قبلاً پردازش شده است!");
        }

        if ($action === 'approve') {
            // به‌روزرسانی اطلاعات هتل
            $stmt = $db->prepare("UPDATE Hotels 
                                  SET hotel_name = :hotel_name, hotel_phone = :hotel_phone, hotel_star = :hotel_star, 
                                      hotel_province = :hotel_province, hotel_country = :hotel_country, hotel_city = :hotel_city, 
                                      hotel_publicrelations = :hotel_publicrelations, hotel_address = :hotel_address, 
                                      price_per_night = :price_per_night, hotel_image = :hotel_image, hotel_capacity = :hotel_capacity, 
                                      hotel_proposed = :hotel_proposed 
                                  WHERE hotel_id = :hotel_id");
            $stmt->execute([
                'hotel_id' => $request['hotel_id'],
                'hotel_name' => $request['hotel_name'],
                'hotel_phone' => $request['hotel_phone'],
                'hotel_star' => $request['hotel_star'],
                'hotel_province' => $request['hotel_province'],
                'hotel_country' => $request['hotel_country'],
                'hotel_city' => $request['hotel_city'],
                'hotel_publicrelations' => $request['hotel_publicrelations'],
                'hotel_address' => $request['hotel_address'],
                'price_per_night' => $request['price_per_night'],
                'hotel_image' => $request['hotel_image'],
                'hotel_capacity' => $request['hotel_capacity'],
                'hotel_proposed' => $request['hotel_proposed']
            ]);

            // به‌روزرسانی وضعیت درخواست
            $stmt = $db->prepare("UPDATE Hotel_Edit_Requests SET request_status = 'Approved' WHERE request_id = :request_id");
            $stmt->execute(['request_id' => $request_id]);

            $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> درخواست با موفقیت تأیید شد!</div>';
        } else {
            // رد درخواست
            $stmt = $db->prepare("UPDATE Hotel_Edit_Requests SET request_status = 'Rejected' WHERE request_id = :request_id");
            $stmt->execute(['request_id' => $request_id]);

            $_SESSION['success_message'] = '<div class="bg-green-900 bg-opacity-50 text-green-300 p-4 rounded-lg mb-6 flex items-center border border-green-700"><i class="fas fa-check-circle mr-2"></i> درخواست با موفقیت رد شد!</div>';
        }

        header('Location: /site/admin/index.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = '<div class="bg-red-900 bg-opacity-50 text-red-300 p-4 rounded-lg mb-6 flex items-center border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

try {
    // آمار کلی
    $total_users = $db->query("SELECT COUNT(*) FROM Users")->fetchColumn();
    $total_hotels = $db->query("SELECT COUNT(*) FROM Hotels")->fetchColumn();
    $total_doctors = $db->query("SELECT COUNT(*) FROM Doctors")->fetchColumn();
    $total_hospitals = $db->query("SELECT COUNT(*) FROM Hospitals")->fetchColumn();
    $total_reservations = $db->query("SELECT COUNT(*) FROM Reservations")->fetchColumn() +
                         $db->query("SELECT COUNT(*) FROM Medical_Reservations")->fetchColumn() +
                         $db->query("SELECT COUNT(*) FROM Hospital_Reservations")->fetchColumn();
    $total_balance = $db->query("SELECT SUM(balance) FROM Users")->fetchColumn() ?: 0;

    // رزروها
    $hotel_total = $db->query("SELECT SUM(total_price) FROM Reservations")->fetchColumn() ?: 0;
    $medical_pending = $db->query("SELECT SUM(total_price) FROM Medical_Reservations WHERE status = 'Pending'")->fetchColumn() ?: 0;
    $medical_confirmed = $db->query("SELECT SUM(total_price) FROM Medical_Reservations WHERE status = 'Confirmed'")->fetchColumn() ?: 0;
    $medical_cancelled = $db->query("SELECT SUM(total_price) FROM Medical_Reservations WHERE status = 'Cancelled'")->fetchColumn() ?: 0;
    $hospital_total = $db->query("SELECT SUM(total_price) FROM Hospital_Reservations")->fetchColumn() ?: 0;

    // ردیم‌کدها
    $redeem_used = $db->query("SELECT COUNT(*) FROM Redeem_Codes WHERE used_by IS NOT NULL")->fetchColumn();
    $redeem_unused = $db->query("SELECT COUNT(*) FROM Redeem_Codes WHERE used_by IS NULL")->fetchColumn();

    // درخواست‌های ویرایش هتل
    $stmt = $db->prepare("SELECT her.*, h.hotel_name AS current_hotel_name 
                          FROM Hotel_Edit_Requests her 
                          JOIN Hotels h ON her.hotel_id = h.hotel_id 
                          WHERE her.request_status = 'Pending'");
    $stmt->execute();
    $edit_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "خطا در اتصال به دیتابیس: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - آستور</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('/site/assets/fonts/Vazir-Regular.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        body {
            font-family: 'Vazir', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            animation: fadeIn 0.5s ease-in;
        }
        .sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            transition: all 0.3s ease;
            transform: translateX(100%);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.4);
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: rgba(75, 85, 99, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
        }
        .stat-card {
            background: linear-gradient(45deg, #3b82f6, #60a5fa);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .stat-card:nth-child(2) { background: linear-gradient(45deg, #10b981, #34d399); }
        .stat-card:nth-child(3) { background: linear-gradient(45deg, #f59e0b, #fbbf24); }
        .stat-card:nth-child(4) { background: linear-gradient(45deg, #8b5cf6, #a78bfa); }
        .stat-card:nth-child(5) { background: linear-gradient(45deg, #6366f1, #818cf8); }
        .stat-card:nth-child(6) { background: linear-gradient(45deg, #14b8a6, #2dd4bf); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.15);
            transform: rotate(30deg);
            transition: all 0.5s ease;
        }
        .stat-card:hover::before {
            top: 100%;
            left: 100%;
        }
        .header {
            background: linear-gradient(90deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        .icon-pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .notification {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        footer {
            background: #1e293b;
            color: #94a3b8;
        }
        .btn-action {
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-action:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.05);
        }
        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0);
                position: fixed;
                top: 64px;
                right: 0;
                width: 16rem;
                height: calc(100vh - 64px);
            }
            .main-content {
                margin-right: 16rem;
            }
        }
        @media (max-width: 767px) {
            .sidebar {
                position: fixed;
                top: 64px;
                right: 0;
                width: 75%;
                height: calc(100vh - 64px);
                z-index: 50;
            }
            .main-content {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <!-- هدر -->
        <header class="header p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-50">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-2xl text-blue-400 mr-2 md:text-3xl icon-pulse"></i>
                <h1 class="text-xl font-bold text-white md:text-2xl">آستور</h1>
            </div>
            <div class="flex items-center space-x-2 md:space-x-4">
                <button id="refresh-btn" class="text-white hover:text-blue-300">
                    <i class="fas fa-sync-alt text-lg md:text-xl"></i>
                </button>
                <span class="text-gray-300 text-sm mr-4 hidden md:inline"><?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
                <button id="menu-toggle" class="text-white md:hidden">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </header>

        <!-- نوتیفیکیشن -->
        <div id="notification" class="notification fixed top-16 left-0 right-0 mx-auto w-64 p-4 bg-green-500 text-white rounded-b-lg shadow-lg z-40 hidden">
            خوش آمدید، <?php echo htmlspecialchars($username); ?>!
        </div>

        <div class="flex flex-1 pt-16">
            <!-- نوار کناری -->
            <div id="sidebar" class="sidebar w-64 p-6">
                <ul class="space-y-6">
                    <li><a href="/site/admin/index.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-tachometer-alt mr-2"></i> داشبورد</a></li>
                    <li><a href="/site/admin/users.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-users mr-2"></i> مدیریت کاربران</a></li>
                    <li><a href="/site/admin/hotels.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-hotel mr-2"></i> مدیریت هتل‌ها</a></li>
                    <li><a href="/site/admin/approve_requests.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-check-circle mr-2"></i> تأیید درخواست‌ها</a></li>
                    <li><a href="/site/admin/doctors.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-user-md mr-2"></i> مدیریت پزشک‌ها</a></li>
                    <li><a href="/site/admin/hospitals.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-hospital mr-2"></i> مدیریت بیمارستان‌ها</a></li>
                    <li><a href="/site/admin/reservations.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-calendar-check mr-2"></i> مدیریت رزروها</a></li>
                </ul>
                <div class="mt-auto">
                    <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</p>
                    <a href="/site/admin/logout.php" class="block mt-4 text-red-400 hover:text-red-300"><i class="fas fa-sign-out-alt mr-2"></i> خروج</a>
                </div>
            </div>

            <!-- محتوای اصلی -->
            <main class="main-content flex-1 p-4 md:p-8">
                <!-- نمایش پیام‌های موفقیت یا خطا -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="notification w-full max-w-2xl mx-auto p-4 bg-green-500 text-white rounded-lg shadow-lg">
                        <?php echo $_SESSION['success_message']; ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="notification w-full max-w-2xl mx-auto p-4 bg-red-500 text-white rounded-lg shadow-lg">
                        <?php echo $_SESSION['error_message']; ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- کارت‌های آمار -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="stat-card card text-white">
                        <i class="fas fa-users text-3xl text-blue-200 absolute top-4 left-4 icon-pulse"></i>
                        <h3 class="text-base font-semibold mt-10 md:text-lg">تعداد کاربران</h3>
                        <p class="text-3xl font-bold mt-2 md:text-4xl"><?php echo $total_users; ?></p>
                        <a href="/site/admin/users.php" class="btn-action block mt-4 text-blue-300 hover:text-blue-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="stat-card card text-white">
                        <i class="fas fa-hotel text-3xl text-green-200 absolute top-4 left-4 icon-pulse"></i>
                        <h3 class="text-base font-semibold mt-10 md:text-lg">تعداد هتل‌ها</h3>
                        <p class="text-3xl font-bold mt-2 md:text-4xl"><?php echo $total_hotels; ?></p>
                        <a href="/site/admin/hotels.php" class="btn-action block mt-4 text-green-300 hover:text-green-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="stat-card card text-white">
                        <i class="fas fa-user-md text-3xl text-yellow-200 absolute top-4 left-4 icon-pulse"></i>
                        <h3 class="text-base font-semibold mt-10 md:text-lg">تعداد پزشکان</h3>
                        <p class="text-3xl font-bold mt-2 md:text-4xl"><?php echo $total_doctors; ?></p>
                        <a href="/site/admin/doctors.php" class="btn-action block mt-4 text-yellow-300 hover:text-yellow-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="stat-card card text-white">
                        <i class="fas fa-hospital text-3xl text-purple-200 absolute top-4 left-4 icon-pulse"></i>
                        <h3 class="text-base font-semibold mt-10 md:text-lg">تعداد بیمارستان‌ها</h3>
                        <p class="text-3xl font-bold mt-2 md:text-4xl"><?php echo $total_hospitals; ?></p>
                        <a href="/site/admin/hospitals.php" class="btn-action block mt-4 text-purple-300 hover:text-purple-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="stat-card card text-white">
                        <i class="fas fa-calendar-check text-3xl text-indigo-200 absolute top-4 left-4 icon-pulse"></i>
                        <h3 class="text-base font-semibold mt-10 md:text-lg">تعداد رزروها</h3>
                        <p class="text-3xl font-bold mt-2 md:text-4xl"><?php echo $total_reservations; ?></p>
                        <a href="/site/admin/reservations.php" class="btn-action block mt-4 text-indigo-300 hover:text-indigo-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="stat-card card text-white">
                        <i class="fas fa-wallet text-3xl text-teal-200 absolute top-4 left-4 icon-pulse"></i>
                        <h3 class="text-base font-semibold mt-10 md:text-lg">کل بالانس کاربران</h3>
                        <p class="text-3xl font-bold mt-2 md:text-4xl"><?php echo number_format($total_balance); ?> تومان</p>
                        <a href="/site/admin/balances.php" class="btn-action block mt-4 text-teal-300 hover:text-teal-100 text-sm">مشاهده جزئیات</a>
                    </div>
                </div>

                <!-- چارت تعاملی رزروها -->
                <div class="mt-8 card p-4 md:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-white md:text-xl"><i class="fas fa-chart-pie mr-2 text-blue-400 icon-pulse"></i> آمار رزروها</h3>
                        <select id="chart-filter" class="bg-gray-700 text-white p-2 rounded-lg text-sm">
                            <option value="all">همه رزروها</option>
                            <option value="hotel">فقط هتل</option>
                            <option value="medical">فقط پزشکی</option>
                            <option value="hospital">فقط بیمارستانی</option>
                        </select>
                    </div>
                    <canvas id="reservationsChart" class="max-w-full mx-auto" style="max-height: 350px;"></canvas>
                </div>

                <!-- رزروها -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="card p-4 md:p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 md:text-xl"><i class="fas fa-bed mr-2 text-green-400 icon-pulse"></i> رزروهای هتل</h3>
                        <p class="text-gray-300 text-sm md:text-base">کل: <span class="font-bold text-white"><?php echo number_format($hotel_total); ?> تومان</span></p>
                        <a href="/site/admin/reservations.php?type=hotel" class="btn-action block mt-4 text-green-300 hover:text-green-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="card p-4 md:p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 md:text-xl"><i class="fas fa-stethoscope mr-2 text-yellow-400 icon-pulse"></i> رزروهای پزشکی</h3>
                        <p class="text-gray-300 text-sm md:text-base">در انتظار: <span class="font-bold text-white"><?php echo number_format($medical_pending); ?> تومان</span></p>
                        <p class="text-gray-300 text-sm md:text-base">تأییدشده: <span class="font-bold text-white"><?php echo number_format($medical_confirmed); ?> تومان</span></p>
                        <p class="text-gray-300 text-sm md:text-base">لغوشده: <span class="font-bold text-white"><?php echo number_format($medical_cancelled); ?> تومان</span></p>
                        <a href="/site/admin/reservations.php?type=medical" class="btn-action block mt-4 text-yellow-300 hover:text-yellow-100 text-sm">مشاهده جزئیات</a>
                    </div>
                    <div class="card p-4 md:p-6">
                        <h3 class="text-lg font-semibold text-white mb-4 md:text-xl"><i class="fas fa-hospital-alt mr-2 text-purple-400 icon-pulse"></i> رزروهای بیمارستانی</h3>
                        <p class="text-gray-300 text-sm md:text-base">کل: <span class="font-bold text-white"><?php echo number_format($hospital_total); ?> تومان</span></p>
                        <a href="/site/admin/reservations.php?type=hospital" class="btn-action block mt-4 text-purple-300 hover:text-purple-100 text-sm">مشاهده جزئیات</a>
                    </div>
                </div>

                <!-- درخواست‌های ویرایش هتل -->
                <div class="mt-8 card p-4 md:p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 md:text-xl"><i class="fas fa-check-circle mr-2 text-blue-400 icon-pulse"></i> درخواست‌های ویرایش هتل</h3>
                    <?php if (empty($edit_requests)): ?>
                        <p class="text-gray-300 text-sm md:text-base">هیچ درخواست در انتظاری وجود ندارد.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-gray-300 text-sm border-collapse">
                                <thead>
                                    <tr class="bg-gray-700">
                                        <th class="border border-gray-600 p-2 sm:p-3">هتل</th>
                                        <th class="border border-gray-600 p-2 sm:p-3">نام جدید</th>
                                        <th class="border border-gray-600 p-2 sm:p-3">درخواست‌دهنده</th>
                                        <th class="border border-gray-600 p-2 sm:p-3">تاریخ درخواست</th>
                                        <th class="border border-gray-600 p-2 sm:p-3">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($edit_requests as $request): ?>
                                        <tr class="hover:bg-gray-600 transition-colors">
                                            <td class="border border-gray-600 p-2 sm:p-3"><?php echo htmlspecialchars($request['current_hotel_name']); ?></td>
                                            <td class="border border-gray-600 p-2 sm:p-3"><?php echo htmlspecialchars($request['hotel_name']); ?></td>
                                            <td class="border border-gray-600 p-2 sm:p-3"><?php echo htmlspecialchars($request['requested_by']); ?></td>
                                            <td class="border border-gray-600 p-2 sm:p-3"><?php echo htmlspecialchars($request['request_date']); ?></td>
                                            <td class="border border-gray-600 p-2 sm:p-3">
                                                <div class="flex space-x-2 justify-center">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="bg-green-600 text-white px-2 sm:px-3 py-1 rounded-lg hover:bg-green-700 transition-all duration-300"><i class="fas fa-check"></i></button>
                                                    </form>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="bg-red-600 text-white px-2 sm:px-3 py-1 rounded-lg hover:bg-red-700 transition-all duration-300"><i class="fas fa-times"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="/site/admin/approve_requests.php" class="btn-action block mt-4 text-blue-300 hover:text-blue-100 text-sm">مشاهده همه درخواست‌ها</a>
                    <?php endif; ?>
                </div>

                <!-- ردیم‌کدها -->
                <div class="mt-8 card p-4 md:p-6">
                    <h3 class="text-lg font-semibold text-white mb-4 md:text-xl"><i class="fas fa-ticket-alt mr-2 text-indigo-400 icon-pulse"></i> ردیم‌کدها</h3>
                    <div class="flex flex-col md:flex-row justify-between">
                        <p class="text-gray-300 text-sm md:text-base">استفاده‌شده: <span class="font-bold text-white"><?php echo $redeem_used ?: 0; ?></span></p>
                        <p class="text-gray-300 text-sm md:text-base">استفاده‌نشده: <span class="font-bold text-white"><?php echo $redeem_unused ?: 0; ?></span></p>
                    </div>
                    <a href="/site/admin/redeem_codes.php" class="btn-action block mt-4 text-indigo-300 hover:text-indigo-100 text-sm">مدیریت کد‌ها</a>
                </div>
            </main>
        </div>

        <!-- فوتر -->
        <footer class="p-4 text-center">
            <p>© <?php echo date('Y'); ?> آستور - تمامی حقوق محفوظ است</p>
        </footer>
    </div>

    <script>
        // منوی موبایل
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target) && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
            }
        });

        // نوتیفیکیشن
        const notification = document.getElementById('notification');
        setTimeout(() => {
            notification.classList.remove('hidden');
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 3000);
        }, 500);

        // رفرش صفحه
        document.getElementById('refresh-btn').addEventListener('click', () => {
            location.reload();
        });

        // دیتای چارت
        const chartData = {
            all: {
                labels: ['هتل', 'پزشکی - در انتظار', 'پزشکی - تأییدشده', 'پزشکی - لغوشده', 'بیمارستانی'],
                data: [<?php echo $hotel_total; ?>, <?php echo $medical_pending; ?>, <?php echo $medical_confirmed; ?>, <?php echo $medical_cancelled; ?>, <?php echo $hospital_total; ?>],
                colors: ['#10b981', '#f59e0b', '#34d399', '#fbbf24', '#8b5cf6']
            },
            hotel: {
                labels: ['هتل'],
                data: [<?php echo $hotel_total; ?>],
                colors: ['#10b981']
            },
            medical: {
                labels: ['در انتظار', 'تأییدشده', 'لغوشده'],
                data: [<?php echo $medical_pending; ?>, <?php echo $medical_confirmed; ?>, <?php echo $medical_cancelled; ?>],
                colors: ['#f59e0b', '#34d399', '#fbbf24']
            },
            hospital: {
                labels: ['بیمارستانی'],
                data: [<?php echo $hospital_total; ?>],
                colors: ['#8b5cf6']
            }
        };

        // چارت تعاملی
        const ctx = document.getElementById('reservationsChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartData.all.labels,
                datasets: [{
                    data: chartData.all.data,
                    backgroundColor: chartData.all.colors,
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#e2e8f0', font: { family: 'Vazir' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${value.toLocaleString()} تومان`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });

        // فیلتر چارت
        document.getElementById('chart-filter').addEventListener('change', (e) => {
            const filter = e.target.value;
            const selectedData = chartData[filter];
            chart.data.labels = selectedData.labels;
            chart.data.datasets[0].data = selectedData.data;
            chart.data.datasets[0].backgroundColor = selectedData.colors;
            chart.update();
        });
    </script>
</body>
</html>