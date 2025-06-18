<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Moderator') {
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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hotel_name = trim($_POST['hotel_name'] ?? '');
    $hotel_owner = trim($_POST['hotel_owner'] ?? '');
    $hotel_phone = trim($_POST['hotel_phone'] ?? '');
    $hotel_star = trim($_POST['hotel_star'] ?? '');
    $hotel_province = trim($_POST['hotel_province'] ?? '');
    $hotel_city = trim($_POST['hotel_city'] ?? '');
    $hotel_publicrelations = trim($_POST['hotel_publicrelations'] ?? '');
    $hotel_proposed = $_POST['hotel_proposed'] ?? 'Not recommended';

    // اعتبارسنجی
    if (empty($hotel_name)) $errors[] = "نام هتل اجباری است.";
    if (empty($hotel_province)) $errors[] = "استان اجباری است.";
    if (empty($hotel_city)) $errors[] = "شهر اجباری است.";
    if ($hotel_star && (!is_numeric($hotel_star) || $hotel_star < 0 || $hotel_star > 5)) $errors[] = "ستاره باید بین 0 تا 5 باشد.";

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO Hotels (hotel_name, hotel_owner, hotel_phone, hotel_star, hotel_province, hotel_city, hotel_publicrelations, hotel_proposed) 
                              VALUES (:hotel_name, :hotel_owner, :hotel_phone, :hotel_star, :hotel_province, :hotel_city, :hotel_publicrelations, :hotel_proposed)");
        $stmt->execute([
            'hotel_name' => $hotel_name,
            'hotel_owner' => $hotel_owner ?: null,
            'hotel_phone' => $hotel_phone ?: null,
            'hotel_star' => $hotel_star ?: null,
            'hotel_province' => $hotel_province,
            'hotel_city' => $hotel_city,
            'hotel_publicrelations' => $hotel_publicrelations ?: null,
            'hotel_proposed' => $hotel_proposed
        ]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>افزودن هتل جدید - آستور</title>
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
        input, select, textarea {
            background: #374151;
            color: #e2e8f0;
            border: 1px solid #4b5563;
            border-radius: 8px;
            padding: 0.5rem;
            width: 100%;
        }
        .error {
            background: #ef4444;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .success {
            background: #10b981;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
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
                <a href="/site/admin/hotels.php" class="text-white hover:text-blue-300"><i class="fas fa-arrow-right text-lg"></i></a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <div class="card p-6 max-w-2xl mx-auto">
                <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-hotel mr-2 text-green-400"></i> افزودن هتل جدید</h2>
                
                <?php if ($success): ?>
                    <div class="success text-white">هتل با موفقیت اضافه شد! <a href="/site/admin/hotels.php" class="underline">بازگشت به لیست</a></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="error text-white">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-300">نام هتل</label>
                        <input type="text" name="hotel_name" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">مالک هتل (اختیاری)</label>
                        <input type="text" name="hotel_owner">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">شماره تماس (اختیاری)</label>
                        <input type="text" name="hotel_phone">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">ستاره (0-5، اختیاری)</label>
                        <input type="number" name="hotel_star" min="0" max="5">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">استان</label>
                        <input type="text" name="hotel_province" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">شهر</label>
                        <input type="text" name="hotel_city" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">روابط عمومی (اختیاری)</label>
                        <input type="text" name="hotel_publicrelations">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">وضعیت</label>
                        <select name="hotel_proposed">
                            <option value="Recommended">توصیه‌شده</option>
                            <option value="Not recommended" selected>توصیه‌نشده</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-action bg-green-500 text-white p-2 rounded-lg w-full hover:bg-green-600">ثبت هتل</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>