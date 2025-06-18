<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// پردازش درخواست ویرایش پزشک
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_doctor'])) {
    try {
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $specialty = filter_input(INPUT_POST, 'specialty', FILTER_SANITIZE_STRING);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
        $availability = filter_input(INPUT_POST, 'availability', FILTER_SANITIZE_STRING);
        $fee = filter_input(INPUT_POST, 'fee', FILTER_VALIDATE_FLOAT);
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        if (!$doctor_id || !$name || !$specialty || !$location || !$availability || !$fee) {
            throw new Exception("لطفاً تمام فیلدها را پر کنید!");
        }

        // بررسی مالکیت
        $stmt = $db->prepare("SELECT doctor_owner FROM Doctors WHERE doctor_id = :doctor_id");
        $stmt->execute(['doctor_id' => $doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($doctor['doctor_owner'] !== $username) {
            throw new Exception("شما اجازه ویرایش این پزشک را ندارید!");
        }

        // ثبت درخواست ویرایش در جدول Doctor_Edit_Requests
        $stmt = $db->prepare("INSERT INTO Doctor_Edit_Requests (doctor_id, name, specialty, location, availability, fee, is_available, doctor_owner, requested_by, request_status) 
                              VALUES (:doctor_id, :name, :specialty, :location, :availability, :fee, :is_available, :doctor_owner, :requested_by, 'Pending')");
        $stmt->execute([
            'doctor_id' => $doctor_id,
            'name' => $name,
            'specialty' => $specialty,
            'location' => $location,
            'availability' => $availability,
            'fee' => $fee,
            'is_available' => $is_available,
            'doctor_owner' => $username,
            'requested_by' => $username
        ]);

        $_SESSION['success_message'] = "درخواست ویرایش پزشک ثبت شد و پس از تأیید ادمین اعمال خواهد شد.";
        header('Location: /site/doctorsmanager/manage_doctors.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "خطا در ثبت درخواست ویرایش: " . $e->getMessage();
    }
}

// بارگذاری پزشکان متعلق به کاربر
try {
    $stmt = $db->prepare("SELECT * FROM Doctors WHERE doctor_owner = :doctor_owner");
    $stmt->execute(['doctor_owner' => $username]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "خطا در اتصال به دیتابیس: " . $e->getMessage();
    exit;
}

// بارگذاری رزروهای پزشک
try {
    $stmt = $db->prepare("SELECT mr.*, d.name AS doctor_name, u.username AS user_name 
                          FROM Medical_Reservations mr 
                          JOIN Doctors d ON mr.doctor_id = d.doctor_id 
                          JOIN Users u ON mr.user_id = u.user_id 
                          WHERE d.doctor_owner = :doctor_owner");
    $stmt->execute(['doctor_owner' => $username]);
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
    <meta name="viewport"="width=device-width, initial-scale=1.0">
    <title>مدیریت پزشکان - آستور</title>
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
                <i class="fas fa-user-md text-2xl text-yellow-400 mr-2"></i>
                <h1 class="text-xl font-bold text-white">مدیریت پزشکان - آستور</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300"><?php echo htmlspecialchars($username); ?> (پزشک)</span>
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

            <!-- اطلاعات پزشک -->
            <div class="card p-6 mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">اطلاعات پزشک شما</h2>
                <?php if (empty($doctors)): ?>
                    <p class="text-gray-300">هیچ پزشکی برای شما ثبت نشده است.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">نام</th>
                                    <th class="border border-gray-600 p-2">تخصص</th>
                                    <th class="border border-gray-600 p-2">موقعیت</th>
                                    <th class="border border-gray-600 p-2">زمان در دسترس</th>
                                    <th class="border border-gray-600 p-2">هزینه</th>
                                    <th class="border border-gray-600 p-2">وضعیت</th>
                                    <th class="border border-gray-600 p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctors as $doctor): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($doctor['name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($doctor['location']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($doctor['availability']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo number_format($doctor['fee']); ?> تومان</td>
                                        <td class="border border-gray-600 p-2"><?php echo $doctor['is_available'] ? 'در دسترس' : 'غیرفعال'; ?></td>
                                        <td class="border border-gray-600 p-2">
                                            <div class="flex space-x-2 justify-center">
                                                <!-- دکمه ویرایش -->
                                                <button onclick="openEditModal(<?php echo $doctor['doctor_id']; ?>, '<?php echo htmlspecialchars($doctor['name']); ?>', '<?php echo htmlspecialchars($doctor['specialty']); ?>', '<?php echo htmlspecialchars($doctor['location']); ?>', '<?php echo htmlspecialchars($doctor['availability']); ?>', '<?php echo $doctor['fee']; ?>', <?php echo $doctor['is_available']; ?>)" class="bg-blue-600 text-white px-3 py-1 rounded-lg hover:bg-blue-700"><i class="fas fa-edit"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- رزروهای پزشک -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold text-white mb-4">رزروهای پزشک</h2>
                <?php if (empty($reservations)): ?>
                    <p class="text-gray-300">هیچ رزروی برای پزشک شما ثبت نشده است.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">شناسه رزرو</th>
                                    <th class="border border-gray-600 p-2">نام پزشک</th>
                                    <th class="border border-gray-600 p-2">کاربر</th>
                                    <th class="border border-gray-600 p-2">تاریخ نوبت</th>
                                    <th class="border border-gray-600 p-2">وضعیت</th>
                                    <th class="border border-gray-600 p-2">مبلغ کل</th>
                                    <th class="border border-gray-600 p-2">تاریخ ایجاد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo $reservation['medical_reservation_id']; ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($reservation['doctor_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($reservation['user_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo $reservation['appointment_date']; ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo $reservation['status']; ?></td>
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
            <h2 class="text-xl font-semibold text-white mb-4">درخواست ویرایش پزشک</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="doctor_id" id="edit_doctor_id">
                <div>
                    <label for="edit_name" class="block text-gray-300">نام پزشک</label>
                    <input type="text" name="name" id="edit_name" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_specialty" class="block text-gray-300">تخصص</label>
                    <input type="text" name="specialty" id="edit_specialty" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_location" class="block text-gray-300">موقعیت</label>
                    <input type="text" name="location" id="edit_location" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_availability" class="block text-gray-300">زمان در دسترس</label>
                    <input type="text" name="availability" id="edit_availability" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_fee" class="block text-gray-300">هزینه ویزیت (تومان)</label>
                    <input type="number" name="fee" id="edit_fee" class="w-full p-2 rounded-lg bg-gray-700 text-white" required>
                </div>
                <div>
                    <label for="edit_is_available" class="block text-gray-300">در دسترس</label>
                    <input type="checkbox" name="is_available" id="edit_is_available" value="1">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">لغو</button>
                    <button type="submit" name="edit_doctor" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">ثبت درخواست</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(doctorId, name, specialty, location, availability, fee, isAvailable) {
            document.getElementById('edit_doctor_id').value = doctorId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_specialty').value = specialty;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_availability').value = availability;
            document.getElementById('edit_fee').value = fee;
            document.getElementById('edit_is_available').checked = isAvailable;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>