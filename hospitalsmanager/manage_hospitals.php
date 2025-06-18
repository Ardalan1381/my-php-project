<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hospital') {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// پردازش درخواست ویرایش بیمارستان
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_hospital'])) {
    try {
        $hospital_id = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
        $room_price = filter_input(INPUT_POST, 'room_price', FILTER_VALIDATE_FLOAT);

        if (!$hospital_id || !$name || !$location || !$room_price) {
            throw new Exception("لطفاً تمام فیلدها را پر کنید!");
        }

        // بررسی مالکیت
        $stmt = $db->prepare("SELECT hospital_owner FROM Hospitals WHERE hospital_id = :hospital_id");
        $stmt->execute(['hospital_id' => $hospital_id]);
        $hospital = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($hospital['hospital_owner'] !== $username) {
            throw new Exception("شما اجازه ویرایش این بیمارستان را ندارید!");
        }

        // ثبت درخواست ویرایش در جدول Hospital_Edit_Requests
        $stmt = $db->prepare("INSERT INTO Hospital_Edit_Requests (hospital_id, name, location, room_price, hospital_owner, requested_by, request_status) 
                              VALUES (:hospital_id, :name, :location, :room_price, :hospital_owner, :requested_by, 'Pending')");
        $stmt->execute([
            'hospital_id' => $hospital_id,
            'name' => $name,
            'location' => $location,
            'room_price' => $room_price,
            'hospital_owner' => $username,
            'requested_by' => $username
        ]);

        $_SESSION['success_message'] = "درخواست ویرایش بیمارستان ثبت شد و پس از تأیید ادمین اعمال خواهد شد.";
        header('Location: /site/hospitalsmanager/manage_hospitals.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا در ثبت درخواست ویرایش: " . $e->getMessage();
    }
}

// بارگذاری بیمارستان‌های متعلق به کاربر
try {
    $stmt = $db->prepare("SELECT * FROM Hospitals WHERE hospital_owner = :hospital_owner");
    $stmt->execute(['hospital_owner' => $username]);
    $hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "خطا در اتصال به دیتابیس: " . $e->getMessage();
    exit;
}

// بارگذاری رزروهای بیمارستان
try {
    $stmt = $db->prepare("SELECT hr.*, h.name AS hospital_name, u.username AS user_name 
                          FROM Hospital_Reservations hr 
                          JOIN Hospitals h ON hr.hospital_id = h.hospital_id 
                          JOIN Users u ON hr.user_id = u.user_id 
                          WHERE h.hospital_owner = :hospital_owner");
    $stmt->execute(['hospital_owner' => $username]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "خطا در بارگذاری رزروها: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت بیمارستان‌ها - آستور</title>
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
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <!-- هدر -->
        <header class="header p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-50">
            <div class="flex items-center">
                <i class="fas fa-hospital text-2xl text-purple-400 mr-2"></i>
                <h1 class="text-xl font-bold text-white">مدیریت بیمارستان‌ها - آستور</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300"><?php echo htmlspecialchars($username); ?> (بیمارستان)</span>
                <a href="/site/logout.php" class="text-red-400 hover:text-red-300"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <!-- نمایش پیام‌ها -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-500 text-white p-4 rounded-lg mb-6">
                    <?php echo $_SESSION['success_message']; ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-500 text-white p-4 rounded-lg mb-6">
                    <?php echo $_SESSION['error_message']; ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- اطلاعات بیمارستان -->
            <div class="card p-6 mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">اطلاعات بیمارستان شما</h2>
                <?php if (empty($hospitals)): ?>
                    <p class="text-gray-300">هیچ بیمارستانی برای شما ثبت نشده است.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">نام</th>
                                    <th class="border border-gray-600 p-2">موقعیت</th>
                                    <th class="border border-gray-600 p-2">قیمت اتاق</th>
                                    <th class="border border-gray-600 p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($hospital['name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($hospital['location']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo number_format($hospital['room_price']); ?> تومان</td>
                                        <td class="border border-gray-600 p-2">
                                            <div class="flex space-x-2 justify-center">
                                                <!-- دکمه ویرایش -->
                                                <button onclick="openEditModal(<?php echo $hospital['hospital_id']; ?>, '<?php echo htmlspecialchars($hospital['name']); ?>', '<?php echo htmlspecialchars($hospital['location']); ?>', '<?php echo $hospital['room_price']; ?>')" class="bg-blue-600 text-white px-3 py-1 rounded-lg hover:bg-blue-700"><i class="fas fa-edit"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- رزروهای بیمارستان -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold text-white mb-4">رزروهای بیمارستان</h2>
                <?php if (empty($reservations)): ?>
                    <p class="text-gray-300">هیچ رزروی برای بیمارستان شما ثبت نشده است.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">شناسه رزرو</th>
                                    <th class="border border-gray-600 p-2">نام بیمارستان</th>
                                    <th class="border border-gray-600 p-2">کاربر</th>
                                    <th class="border border-gray-600 p-2">تاریخ ورود</th>
                                    <th class="border border-gray-600 p-2">مبلغ کل</th>
                                    <th class="border border-gray-600 p-2">تاریخ ایجاد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo $reservation['hospital_reservation_id']; ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($reservation['hospital_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($reservation['user_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo $reservation['check_in_date']; ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo number_format($reservation['total_price']); ?> تومان</td>
                                        <td class="border border-gray-600 p-2"><?php echo $reservation['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- فوتر -->
        <footer class="p-4 text-center bg-gray-900 text-gray-400">
            <p>© <?php echo date('Y'); ?> آستور - تمامی حقوق محفوظ است</p>
        </footer>
    </div>

    <!-- مودال ویرایش -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-gray-800 p-6 rounded-lg w-full max-w-md">
            <h2 class="text-xl font-semibold text-white mb-4">درخواست ویرایش بیمارستان</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="hospital_id" id="edit_hospital_id">
                <div>
                    <label for="edit_name" class="block text-gray-300">نام بیمارستان</label>
                    <input type="text" name="name" id="edit_name" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_location" class="block text-gray-300">موقعیت</label>
                    <input type="text" name="location" id="edit_location" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_room_price" class="block text-gray-300">قیمت اتاق (تومان)</label>
                    <input type="number" name="room_price" id="edit_room_price" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">لغو</button>
                    <button type="submit" name="edit_hospital" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">ثبت درخواست</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(hospitalId, name, location, roomPrice) {
            document.getElementById('edit_hospital_id').value = hospitalId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_room_price').value = roomPrice;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>