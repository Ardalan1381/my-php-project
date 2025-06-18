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
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$query = "SELECT hotel_id, hotel_name, hotel_owner, hotel_phone, hotel_star, hotel_province, hotel_city, hotel_publicrelations, hotel_proposed 
          FROM Hotels 
          WHERE (hotel_name LIKE :search OR hotel_id LIKE :search)";
$params = ['search' => "%$search%"];

if ($status_filter) {
    $query .= " AND hotel_proposed = :status";
    $params['status'] = $status_filter;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// مدیریت درخواست AJAX برای تغییر وضعیت (فقط برای Moderator)
if (isset($_POST['action']) && $_POST['action'] === 'update_status' && $role === 'Moderator') {
    $hotel_id = $_POST['hotel_id'] ?? null;
    $new_status = $_POST['hotel_proposed'] ?? null;

    if ($hotel_id && in_array($new_status, ['Recommended', 'Not recommended'])) {
        $stmt = $db->prepare("UPDATE Hotels SET hotel_proposed = :hotel_proposed WHERE hotel_id = :hotel_id");
        $stmt->execute(['hotel_proposed' => $new_status, 'hotel_id' => $hotel_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ورودی نامعتبر']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت هتل‌ها - آستور</title>
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
        .status-select {
            background: #374151;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 0.25rem;
            width: 100%;
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
                    <li><a href="/site/admin/users.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-users mr-2"></i> مدیریت کاربران</a></li>
                    <li><a href="/site/admin/verification_requests.php" class="flex items-center text-gray-300 hover:text-white transition"><i class="fas fa-video mr-2"></i> درخواست‌های احراز هویت</a></li>
                    <li><a href="/site/admin/hotels.php" class="flex items-center text-white transition"><i class="fas fa-hotel mr-2"></i> مدیریت هتل‌ها</a></li>
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
                <?php endif; ?>
                <div class="card p-4 md:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-white md:text-2xl"><i class="fas fa-hotel mr-2 text-blue-400 icon-pulse"></i> مدیریت هتل‌ها</h2>
                        <?php if ($role === 'Moderator'): ?>
                            <a href="/site/admin/add_hotel.php" class="btn-action text-green-300 hover:text-green-100"><i class="fas fa-plus mr-1"></i> افزودن هتل جدید</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- فرم جستجو و فیلتر -->
                    <form method="GET" class="mb-6 flex flex-col md:flex-row gap-4">
                        <div class="flex items-center flex-1">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="جستجو بر اساس نام یا شناسه..." class="w-full p-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <button type="submit" class="mr-2 text-white hover:text-blue-300"><i class="fas fa-search text-lg"></i></button>
                        </div>
                        <select name="status_filter" onchange="this.form.submit()" class="md:w-1/4">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="Recommended" <?php if ($status_filter === 'Recommended') echo 'selected'; ?>>توصیه‌شده</option>
                            <option value="Not recommended" <?php if ($status_filter === 'Not recommended') echo 'selected'; ?>>توصیه‌نشده</option>
                        </select>
                    </form>

                    <!-- جدول هتل‌ها -->
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام هتل</th>
                                    <th>مالک</th>
                                    <th>شماره تماس</th>
                                    <th>ستاره</th>
                                    <th>استان</th>
                                    <th>شهر</th>
                                    <th>روابط عمومی</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hotels as $hotel): ?>
                                    <tr>
                                        <td data-label="شناسه"><?php echo htmlspecialchars($hotel['hotel_id']); ?></td>
                                        <td data-label="نام هتل"><?php echo htmlspecialchars($hotel['hotel_name']); ?></td>
                                        <td data-label="مالک"><?php echo htmlspecialchars($hotel['hotel_owner'] ?? '-'); ?></td>
                                        <td data-label="شماره تماس"><?php echo htmlspecialchars($hotel['hotel_phone'] ?? '-'); ?></td>
                                        <td data-label="ستاره"><?php echo htmlspecialchars($hotel['hotel_star'] ?? '-'); ?></td>
                                        <td data-label="استان"><?php echo htmlspecialchars($hotel['hotel_province']); ?></td>
                                        <td data-label="شهر"><?php echo htmlspecialchars($hotel['hotel_city']); ?></td>
                                        <td data-label="روابط عمومی"><?php echo htmlspecialchars($hotel['hotel_publicrelations'] ?? '-'); ?></td>
                                        <td data-label="وضعیت">
                                            <?php if ($role === 'Moderator'): ?>
                                                <select class="status-select" onchange="updateStatus(<?php echo $hotel['hotel_id']; ?>, this.value)">
                                                    <option value="Recommended" <?php if ($hotel['hotel_proposed'] === 'Recommended') echo 'selected'; ?>>توصیه‌شده</option>
                                                    <option value="Not recommended" <?php if ($hotel['hotel_proposed'] === 'Not recommended') echo 'selected'; ?>>توصیه‌نشده</option>
                                                </select>
                                            <?php else: ?>
                                                <?php echo $hotel['hotel_proposed'] === 'Recommended' ? 'توصیه‌شده' : 'توصیه‌نشده'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="عملیات">
                                            <?php if ($role === 'Moderator'): ?>
                                                <a href="/site/admin/edit_hotel.php?id=<?php echo $hotel['hotel_id']; ?>" class="btn-action text-yellow-300 hover:text-yellow-100 mr-2"><i class="fas fa-edit"></i></a>
                                                <a href="/site/admin/delete_hotel.php?id=<?php echo $hotel['hotel_id']; ?>" class="btn-action text-red-300 hover:text-red-100" onclick="return confirm('مطمئن هستید که می‌خواهید این هتل را حذف کنید؟');"><i class="fas fa-trash"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($hotels)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">هتلی یافت نشد!</td>
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

    <script>
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

        function updateStatus(hotelId, newStatus) {
            fetch('/site/admin/hotels.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_status&hotel_id=${hotelId}&hotel_proposed=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('وضعیت با موفقیت تغییر کرد');
                } else {
                    alert('خطا در تغییر وضعیت: ' + (data.error || 'مشکل ناشناخته'));
                }
            })
            .catch(error => {
                console.error('خطا:', error);
                alert('خطا در ارتباط با سرور');
            });
        }
    </script>
</body>
</html>