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

// مدیریت درخواست‌ها
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'], $_POST['request_id'], $_POST['request_type'])) {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $request_type = filter_input(INPUT_POST, 'request_type', FILTER_SANITIZE_STRING);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    try {
        if ($request_type === 'hotel') {
            $stmt = $db->prepare("SELECT * FROM Hotel_Edit_Requests WHERE request_id = :request_id");
            $stmt->execute(['request_id' => $request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($action === 'approve') {
                $stmt = $db->prepare("UPDATE Hotels 
                                      SET hotel_name = :hotel_name, location = :location, price_per_night = :price_per_night, 
                                          facilities = :facilities, room_types = :room_types, images = :images 
                                      WHERE hotel_id = :hotel_id");
                $stmt->execute([
                    'hotel_id' => $request['hotel_id'],
                    'hotel_name' => $request['hotel_name'],
                    'location' => $request['location'],
                    'price_per_night' => $request['price_per_night'],
                    'facilities' => $request['facilities'],
                    'room_types' => $request['room_types'],
                    'images' => $request['images']
                ]);
                $status = 'Approved';
            } else {
                $status = 'Rejected';
            }

            $stmt = $db->prepare("UPDATE Hotel_Edit_Requests SET request_status = :status WHERE request_id = :request_id");
            $stmt->execute(['status' => $status, 'request_id' => $request_id]);

            // ثبت لاگ فعالیت
            $stmt = $db->prepare("INSERT INTO Activity_Logs (user_id, action) VALUES (:user_id, :action)");
            $stmt->execute([
                'user_id' => $user_id,
                'action' => "درخواست ویرایش هتل با شناسه {$request_id} " . ($action === 'approve' ? 'تأیید' : 'رد') . " شد"
            ]);
        } elseif ($request_type === 'hospital') {
            $stmt = $db->prepare("SELECT * FROM Hospital_Edit_Requests WHERE request_id = :request_id");
            $stmt->execute(['request_id' => $request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($action === 'approve') {
                $stmt = $db->prepare("UPDATE Hospitals 
                                      SET name = :name, location = :location, room_price = :room_price 
                                      WHERE hospital_id = :hospital_id");
                $stmt->execute([
                    'hospital_id' => $request['hospital_id'],
                    'name' => $request['name'],
                    'location' => $request['location'],
                    'room_price' => $request['room_price']
                ]);
                $status = 'Approved';
            } else {
                $status = 'Rejected';
            }

            $stmt = $db->prepare("UPDATE Hospital_Edit_Requests SET request_status = :status WHERE request_id = :request_id");
            $stmt->execute(['status' => $status, 'request_id' => $request_id]);

            $stmt = $db->prepare("INSERT INTO Activity_Logs (user_id, action) VALUES (:user_id, :action)");
            $stmt->execute([
                'user_id' => $user_id,
                'action' => "درخواست ویرایش بیمارستان با شناسه {$request_id} " . ($action === 'approve' ? 'تأیید' : 'رد') . " شد"
            ]);
        } elseif ($request_type === 'doctor') {
            $stmt = $db->prepare("SELECT * FROM Doctor_Edit_Requests WHERE request_id = :request_id");
            $stmt->execute(['request_id' => $request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($action === 'approve') {
                $stmt = $db->prepare("UPDATE Doctors 
                                      SET name = :name, specialty = :specialty, location = :location, 
                                          availability = :availability, fee = :fee, is_available = :is_available 
                                      WHERE doctor_id = :doctor_id");
                $stmt->execute([
                    'doctor_id' => $request['doctor_id'],
                    'name' => $request['name'],
                    'specialty' => $request['specialty'],
                    'location' => $request['location'],
                    'availability' => $request['availability'],
                    'fee' => $request['fee'],
                    'is_available' => $request['is_available']
                ]);
                $status = 'Approved';
            } else {
                $status = 'Rejected';
            }

            $stmt = $db->prepare("UPDATE Doctor_Edit_Requests SET request_status = :status WHERE request_id = :request_id");
            $stmt->execute(['status' => $status, 'request_id' => $request_id]);

            $stmt = $db->prepare("INSERT INTO Activity_Logs (user_id, action) VALUES (:user_id, :action)");
            $stmt->execute([
                'user_id' => $user_id,
                'action' => "درخواست ویرایش پزشک با شناسه {$request_id} " . ($action === 'approve' ? 'تأیید' : 'رد') . " شد"
            ]);
        }

        // ارسال اعلان به کاربر
        $stmt = $db->prepare("SELECT user_id FROM Users WHERE username = :username");
        $stmt->execute(['username' => $request['requested_by']]);
        $requested_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($requested_user) {
            $stmt = $db->prepare("INSERT INTO Notifications (user_id, message) VALUES (:user_id, :message)");
            $stmt->execute([
                'user_id' => $requested_user['user_id'],
                'message' => "درخواست ویرایش شما برای " . ($request_type === 'hotel' ? 'هتل' : ($request_type === 'hospital' ? 'بیمارستان' : 'پزشک')) . " " . ($action === 'approve' ? 'تأیید' : 'رد') . " شد."
            ]);
        }

        header("Location: /site/admin/approve_requests.php");
        exit;
    } catch (PDOException $e) {
        echo "خطا: " . $e->getMessage();
        exit;
    }
}

// بارگذاری درخواست‌ها
try {
    // درخواست‌های هتل
    $stmt = $db->prepare("SELECT her.*, h.hotel_name AS current_hotel_name 
                          FROM Hotel_Edit_Requests her 
                          JOIN Hotels h ON her.hotel_id = h.hotel_id 
                          ORDER BY her.request_date DESC");
    $stmt->execute();
    $hotel_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // درخواست‌های بیمارستان
    $stmt = $db->prepare("SELECT her.*, h.name AS current_name 
                          FROM Hospital_Edit_Requests her 
                          JOIN Hospitals h ON her.hospital_id = h.hospital_id 
                          ORDER BY her.request_date DESC");
    $stmt->execute();
    $hospital_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // درخواست‌های پزشک
    $stmt = $db->prepare("SELECT der.*, d.name AS current_name 
                          FROM Doctor_Edit_Requests der 
                          JOIN Doctors d ON der.doctor_id = d.doctor_id 
                          ORDER BY der.request_date DESC");
    $stmt->execute();
    $doctor_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>تأیید درخواست‌های ویرایش - آستور</title>
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
    </style>
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <!-- هدر -->
        <header class="header p-4 flex justify-between items-center fixed top-0 left-0 right-0 z-50">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-2xl text-blue-400 mr-2"></i>
                <h1 class="text-xl font-bold text-white">تأیید درخواست‌های ویرایش - آستور</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-300"><?php echo htmlspecialchars($username); ?> (ادمین)</span>
                <a href="/site/admin/index.php" class="text-white hover:text-blue-300"><i class="fas fa-arrow-right text-lg"></i></a>
                <a href="/site/logout.php" class="text-red-400 hover:text-red-300"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <!-- درخواست‌های ویرایش هتل -->
            <div class="card p-6 mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">درخواست‌های ویرایش هتل</h2>
                <?php if (empty($hotel_requests)): ?>
                    <p class="text-gray-300">هیچ درخواستی وجود ندارد.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">هتل</th>
                                    <th class="border border-gray-600 p-2">نام جدید</th>
                                    <th class="border border-gray-600 p-2">درخواست‌دهنده</th>
                                    <th class="border border-gray-600 p-2">تاریخ درخواست</th>
                                    <th class="border border-gray-600 p-2">وضعیت</th>
                                    <th class="border border-gray-600 p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hotel_requests as $request): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['current_hotel_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['hotel_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['requested_by']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['request_date']); ?></td>
                                        <td class="border border-gray-600 p-2">
                                            <?php
                                            if ($request['request_status'] === 'Pending') {
                                                echo '<span class="text-yellow-400">در انتظار</span>';
                                            } elseif ($request['request_status'] === 'Approved') {
                                                echo '<span class="text-green-400">تأییدشده</span>';
                                            } else {
                                                echo '<span class="text-red-400">ردشده</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="border border-gray-600 p-2">
                                            <?php if ($request['request_status'] === 'Pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="request_type" value="hotel">
                                                    <button type="submit" name="action" value="approve" class="text-green-400 hover:text-green-300 mr-2">
                                                        <i class="fas fa-check"></i> تأیید
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="text-red-400 hover:text-red-300">
                                                        <i class="fas fa-times"></i> رد
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- درخواست‌های ویرایش بیمارستان -->
            <div class="card p-6 mb-8">
                <h2 class="text-xl font-semibold text-white mb-4">درخواست‌های ویرایش بیمارستان</h2>
                <?php if (empty($hospital_requests)): ?>
                    <p class="text-gray-300">هیچ درخواستی وجود ندارد.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">بیمارستان</th>
                                    <th class="border border-gray-600 p-2">نام جدید</th>
                                    <th class="border border-gray-600 p-2">درخواست‌دهنده</th>
                                    <th class="border border-gray-600 p-2">تاریخ درخواست</th>
                                    <th class="border border-gray-600 p-2">وضعیت</th>
                                    <th class="border border-gray-600 p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospital_requests as $request): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['current_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['requested_by']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['request_date']); ?></td>
                                        <td class="border border-gray-600 p-2">
                                            <?php
                                            if ($request['request_status'] === 'Pending') {
                                                echo '<span class="text-yellow-400">در انتظار</span>';
                                            } elseif ($request['request_status'] === 'Approved') {
                                                echo '<span class="text-green-400">تأییدشده</span>';
                                            } else {
                                                echo '<span class="text-red-400">ردشده</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="border border-gray-600 p-2">
                                            <?php if ($request['request_status'] === 'Pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="request_type" value="hospital">
                                                    <button type="submit" name="action" value="approve" class="text-green-400 hover:text-green-300 mr-2">
                                                        <i class="fas fa-check"></i> تأیید
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="text-red-400 hover:text-red-300">
                                                        <i class="fas fa-times"></i> رد
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- درخواست‌های ویرایش پزشک -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold text-white mb-4">درخواست‌های ویرایش پزشک</h2>
                <?php if (empty($doctor_requests)): ?>
                    <p class="text-gray-300">هیچ درخواستی وجود ندارد.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-gray-300 text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="border border-gray-600 p-2">پزشک</th>
                                    <th class="border border-gray-600 p-2">نام جدید</th>
                                    <th class="border border-gray-600 p-2">درخواست‌دهنده</th>
                                    <th class="border border-gray-600 p-2">تاریخ درخواست</th>
                                    <th class="border border-gray-600 p-2">وضعیت</th>
                                    <th class="border border-gray-600 p-2">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($doctor_requests as $request): ?>
                                    <tr class="hover:bg-gray-600">
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['current_name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['name']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['requested_by']); ?></td>
                                        <td class="border border-gray-600 p-2"><?php echo htmlspecialchars($request['request_date']); ?></td>
                                        <td class="border border-gray-600 p-2">
                                            <?php
                                            if ($request['request_status'] === 'Pending') {
                                                echo '<span class="text-yellow-400">در انتظار</span>';
                                            } elseif ($request['request_status'] === 'Approved') {
                                                echo '<span class="text-green-400">تأییدشده</span>';
                                            } else {
                                                echo '<span class="text-red-400">ردشده</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="border border-gray-600 p-2">
                                            <?php if ($request['request_status'] === 'Pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                    <input type="hidden" name="request_type" value="doctor">
                                                    <button type="submit" name="action" value="approve" class="text-green-400 hover:text-green-300 mr-2">
                                                        <i class="fas fa-check"></i> تأیید
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="text-red-400 hover:text-red-300">
                                                        <i class="fas fa-times"></i> رد
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
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
</body>
</html>