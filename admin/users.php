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
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$username = $user['username'];
$role = $user['role'];

// فیلتر و جستجو
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$query = "SELECT user_id, username, email, `F-Name`, `L-Name`, Phone, balance, verification_status, role, video_path 
          FROM Users 
          WHERE (username LIKE :search OR user_id LIKE :search OR email LIKE :search)";
$params = ['search' => "%$search%"];

if ($role_filter) {
    $query .= " AND role = :role";
    $params['role'] = $role_filter;
}
if ($status_filter) {
    $query .= " AND verification_status = :status";
    $params['status'] = $status_filter;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - آستور</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            background: rgba(75, 85, 99, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: right;
        }
        th {
            background: rgba(55, 65, 81, 0.9);
        }
        tr:nth-child(even) {
            background: rgba(55, 65, 81, 0.3);
        }
        select {
            background: #374151;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem;
        }
        .popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .popup-content {
            background: #1e293b;
            padding: 1rem;
            border-radius: 16px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
        }
        video {
            max-width: 100%;
            border-radius: 8px;
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
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                margin-bottom: 1rem;
                background: rgba(55, 65, 81, 0.3);
                border-radius: 8px;
            }
            td {
                border: none;
                position: relative;
                padding-right: 50%;
            }
            td:before {
                position: absolute;
                right: 1rem;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                content: attr(data-label);
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

        <div class="flex flex-1 pt-16">
            <!-- نوار کناری -->
            <div id="sidebar" class="sidebar w-64 p-6">
                <ul class="space-y-6">
                    <li><a href="/site/admin/index.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-tachometer-alt mr-2"></i> داشبورد</a></li>
                    <li><a href="/site/admin/users.php" class="flex items-center text-white transition"><i class="fas fa-users mr-2"></i> مدیریت کاربران</a></li>
                    <li><a href="/site/admin/verification_requests.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-video mr-2"></i> درخواست‌های احراز هویت</a></li>
                    <li><a href="/site/admin/hotels.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-hotel mr-2"></i> مدیریت هتل‌ها</a></li>
                    <li><a href="/site/admin/reservations.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-calendar-check mr-2"></i> مدیریت رزروها</a></li>
                </ul>
                <div class="mt-auto">
                    <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</p>
                    <a href="/site/admin/logout.php" class="block mt-4 text-red-400 hover:text-red-300"><i class="fas fa-sign-out-alt mr-2"></i> خروج</a>
                </div>
            </div>

            <!-- محتوای اصلی -->
            <main class="main-content flex-1 p-4 md:p-8">
                <?php if (isset($_GET['message'])): ?>
                    <div class="bg-green-500 text-white p-4 rounded-lg mb-4">
                        <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="bg-red-500 text-white p-4 rounded-lg mb-4">
                        <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
                    </div>
                <?php endif; ?>
                <div class="card p-4 md:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-white md:text-2xl"><i class="fas fa-users mr-2 text-blue-400 icon-pulse"></i> مدیریت کاربران</h2>
                        <?php if ($role === 'Moderator'): ?>
                            <a href="/site/admin/add_user.php" class="btn-action text-green-300 hover:text-green-100"><i class="fas fa-plus mr-1"></i> افزودن کاربر جدید</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- فرم جستجو و فیلتر -->
                    <form method="GET" class="mb-6 flex flex-col md:flex-row gap-4">
                        <div class="flex items-center flex-1">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="جستجو بر اساس نام کاربری، شناسه یا ایمیل..." class="w-full p-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <button type="submit" class="mr-2 text-white hover:text-blue-300"><i class="fas fa-search text-lg"></i></button>
                        </div>
                        <select name="role_filter" onchange="this.form.submit()" class="md:w-1/4">
                            <option value="">همه نقش‌ها</option>
                            <option value="admin" <?php if ($role_filter === 'admin') echo 'selected'; ?>>ادمین</option>
                            <option value="hotelier" <?php if ($role_filter === 'hotelier') echo 'selected'; ?>>هتلی</option>
                            <option value="doctor" <?php if ($role_filter === 'doctor') echo 'selected'; ?>>پزشک</option>
                            <option value="user" <?php if ($role_filter === 'user') echo 'selected'; ?>>کاربر</option>
                            <option value="Moderator" <?php if ($role_filter === 'Moderator') echo 'selected'; ?>>مدراتور</option>
                        </select>
                        <select name="status_filter" onchange="this.form.submit()" class="md:w-1/4">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="Confirm" <?php if ($status_filter === 'Confirm') echo 'selected'; ?>>تأیید شده</option>
                            <option value="Rejected" <?php if ($status_filter === 'Rejected') echo 'selected'; ?>>رد شده</option>
                            <option value="Not Verified" <?php if ($status_filter === 'Not Verified') echo 'selected'; ?>>در انتظار تأیید</option>
                        </select>
                    </form>

                    <!-- جدول کاربران -->
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام کاربری</th>
                                    <th>نام</th>
                                    <th>نام خانوادگی</th>
                                    <th>ایمیل</th>
                                    <th>تلفن</th>
                                    <th>بالانس</th>
                                    <th>وضعیت تأیید</th>
                                    <th>ویدیو</th>
                                    <th>نقش</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td data-label="شناسه"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                        <td data-label="نام کاربری"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td data-label="نام"><?php echo htmlspecialchars($user['F-Name'] ?? '-'); ?></td>
                                        <td data-label="نام خانوادگی"><?php echo htmlspecialchars($user['L-Name'] ?? '-'); ?></td>
                                        <td data-label="ایمیل"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                        <td data-label="تلفن"><?php echo htmlspecialchars($user['Phone'] ?? '-'); ?></td>
                                        <td data-label="بالانس"><?php echo number_format($user['balance'] ?? 0); ?> تومان</td>
                                        <td data-label="وضعیت تأیید">
                                            <?php 
                                            switch ($user['verification_status']) {
                                                case 'Confirm': echo 'تأیید شده'; break;
                                                case 'Rejected': echo 'رد شده'; break;
                                                case 'Not Verified': echo 'در انتظار تأیید'; break;
                                                default: echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="ویدیو">
                                            <?php if ($user['video_path']): ?>
                                                <button class="btn-action text-blue-300 hover:text-blue-100" onclick="showVideo('<?php echo htmlspecialchars($user['video_path']); ?>')">نمایش</button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="نقش"><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td data-label="عملیات">
                                            <?php if ($role === 'Moderator'): ?>
                                                <a href="/site/admin/edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn-action text-yellow-300 hover:text-yellow-100 mr-2"><i class="fas fa-edit"></i></a>
                                            <?php endif; ?>
                                            <a href="/site/admin/delete_user.php?id=<?php echo $user['user_id']; ?>" class="btn-action text-red-300 hover:text-red-100" onclick="return confirm('مطمئن هستید که می‌خواهید این کاربر را حذف کنید؟');"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-4">کاربری یافت نشد!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>

        <!-- فوتر -->
        <footer class="p-4 text-center">
            <p>© <?php echo date('Y'); ?> آستور - تمامی حقوق محفوظ است</p>
        </footer>
    </div>

    <!-- پاپ‌آپ ویدیو -->
    <div id="videoPopup" class="popup">
        <div class="popup-content">
            <button onclick="hideVideo()" class="text-red-400 hover:text-red-300 mb-2"><i class="fas fa-times"></i> بستن</button>
            <video controls id="popupVideo">
                <source id="videoSource" src="" type="video/mp4">
                مرورگر شما از ویدیو پشتیبانی نمی‌کند.
            </video>
        </div>
    </div>

    <script>
        function showVideo(src) {
            const popup = document.getElementById('videoPopup');
            const videoSource = document.getElementById('videoSource');
            const video = document.getElementById('popupVideo');
            videoSource.src = src;
            video.load();
            popup.style.display = 'flex';
        }

        function hideVideo() {
            const popup = document.getElementById('videoPopup');
            const video = document.getElementById('popupVideo');
            video.pause();
            popup.style.display = 'none';
        }

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

        document.getElementById('refresh-btn').addEventListener('click', () => {
            location.reload();
        });
    </script>
</body>
</html>