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
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$username = $current_user['username'];
$role = $current_user['role'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت رزروها - آستور</title>
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
        }
        .card {
            background: rgba(75, 85, 99, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .header {
            background: linear-gradient(90deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
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
        select, input[type="date"], input[type="text"] {
            background: #374151;
            color: #e2e8f0;
            border: 1px solid #4b5563;
            border-radius: 8px;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <!-- هدر -->
        <header class="header p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-50">
            <div class="flex items-center">
                <i class="fas fa-shield-alt text-2xl text-blue-400 mr-2"></i>
                <h1 class="text-xl font-bold text-white">آستور</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300 text-sm"><?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</span>
                <a href="/site/admin/index.php" class="text-white hover:text-blue-300"><i class="fas fa-arrow-right text-lg"></i></a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <div class="card p-6">
                <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-calendar-check mr-2 text-blue-400"></i> مدیریت رزروها</h2>
                <!-- فرم فیلتر -->
                <div class="flex flex-col md:flex-row gap-4 mb-6">
                    <div>
                        <label for="type" class="text-sm text-gray-300 mr-2">نوع رزرو:</label>
                        <select id="type" class="text-sm">
                            <option value="all">همه رزروها</option>
                            <option value="hotel">رزروهای هتل</option>
                            <option value="medical">رزروهای پزشکی</option>
                            <option value="hospital">رزروهای بیمارستانی</option>
                        </select>
                    </div>
                    <div>
                        <label for="start_date" class="text-sm text-gray-300 mr-2">از تاریخ:</label>
                        <input type="date" id="start_date" class="text-sm">
                    </div>
                    <div>
                        <label for="end_date" class="text-sm text-gray-300 mr-2">تا تاریخ:</label>
                        <input type="date" id="end_date" class="text-sm">
                    </div>
                    <div>
                        <label for="search_username" class="text-sm text-gray-300 mr-2">نام کاربر:</label>
                        <input type="text" id="search_username" placeholder="جستجوی کاربر" class="text-sm">
                    </div>
                    <button id="filter_btn" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">فیلتر</button>
                </div>

                <div class="overflow-x-auto">
                    <table id="reservations_table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>کاربر</th>
                                <th>نام (هتل/پزشک/بیمارستان)</th>
                                <th>نوع</th>
                                <th>مبلغ (تومان)</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="reservations_body">
                            <!-- داده‌ها با AJAX پر می‌شن -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // تابع برای گرفتن رزروها
        function fetchReservations() {
            const type = document.getElementById('type').value;
            const start_date = document.getElementById('start_date').value;
            const end_date = document.getElementById('end_date').value;
            const search_username = document.getElementById('search_username').value;

            fetch('/site/admin/reservations_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_reservations&type=${type}&start_date=${start_date}&end_date=${end_date}&search_username=${search_username}`
            })
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('reservations_body');
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">رزروی یافت نشد!</td></tr>';
                } else {
                    data.forEach(res => {
                        const row = `
                            <tr>
                                <td>${res.id}</td>
                                <td>${res.username}</td>
                                <td>${res.entity_name}</td>
                                <td>${res.type}</td>
                                <td>${parseFloat(res.total_price).toLocaleString('fa-IR')}</td>
                                <td>${
                                    res.type === 'پزشکی' ?
                                    `<select onchange="updateStatus(${res.id}, this.value, '${res.type}')">
                                        <option value="Pending" ${res.status === 'Pending' ? 'selected' : ''}>در انتظار</option>
                                        <option value="Confirmed" ${res.status === 'Confirmed' ? 'selected' : ''}>تأییدشده</option>
                                        <option value="Cancelled" ${res.status === 'Cancelled' ? 'selected' : ''}>لغوشده</option>
                                    </select>` :
                                    '<span class="text-gray-400">وضعیت ندارد</span>'
                                }</td>
                                <td>${res.created_at}</td>
                                <td>
                                    <a href="#" onclick="deleteReservation(${res.id}, '${res.type}'); return false;" class="btn-action text-red-300 hover:text-red-100"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>`;
                        tbody.innerHTML += row;
                    });
                }
            })
            .catch(error => console.error('خطا:', error));
        }

        // تابع برای تغییر وضعیت
        function updateStatus(id, status, type) {
            fetch('/site/admin/reservations_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_status&id=${id}&status=${status}&type=${type}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) fetchReservations(); // به‌روزرسانی جدول
            })
            .catch(error => console.error('خطا:', error));
        }

        // تابع برای حذف رزرو
        function deleteReservation(id, type) {
            if (confirm('مطمئن هستید که می‌خواهید این رزرو را حذف کنید؟')) {
                fetch('/site/admin/reservations_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_reservation&id=${id}&type=${type}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) fetchReservations(); // به‌روزرسانی جدول
                })
                .catch(error => console.error('خطا:', error));
            }
        }

        // رویدادها
        document.getElementById('filter_btn').addEventListener('click', fetchReservations);
        document.getElementById('type').addEventListener('change', fetchReservations);
        document.getElementById('start_date').addEventListener('change', fetchReservations);
        document.getElementById('end_date').addEventListener('change', fetchReservations);
        document.getElementById('search_username').addEventListener('input', fetchReservations);

        // بارگذاری اولیه
        fetchReservations();
    </script>
</body>
</html>