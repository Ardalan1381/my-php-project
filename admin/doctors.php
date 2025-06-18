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

// دریافت لیست پزشک‌ها
try {
    $stmt = $db->prepare("SELECT * FROM Doctors");
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>مدیریت پزشک‌ها - آستور</title>
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
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-white"><i class="fas fa-user-md mr-2 text-blue-400"></i> مدیریت پزشک‌ها</h2>
                    <a href="/site/admin/add_doctor.php" class="btn-action text-green-300 hover:text-green-100"><i class="fas fa-plus mr-1"></i> افزودن پزشک</a>
                </div>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نام</th>
                                <th>تخصص</th>
                                <th>ساعات کاری</th>
                                <th>هزینه (تومان)</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['doctor_id']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['availability']); ?></td>
                                    <td><?php echo number_format($doctor['fee'], 2); ?></td>
                                    <td>
                                        <a href="/site/admin/edit_doctor.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn-action text-yellow-300 hover:text-yellow-100 mr-2"><i class="fas fa-edit"></i></a>
                                        <a href="/site/admin/delete_doctor.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn-action text-red-300 hover:text-red-100" onclick="return confirm('مطمئن هستید که می‌خواهید این پزشک را حذف کنید؟');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($doctors)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">پزشکی یافت نشد!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>