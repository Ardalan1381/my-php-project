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
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    echo "خطا: کاربر پیدا نشد!";
    exit;
}

$username = $current_user['username'];
$role = $current_user['role'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $f_name = trim($_POST['f_name'] ?? '');
    $l_name = trim($_POST['l_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $balance = trim($_POST['balance'] ?? '0');
    $verification_status = $_POST['verification_status'] ?? 'Not Verified';
    $new_role = $_POST['role'] ?? 'user';
    $card_uid = trim($_POST['card_uid'] ?? '');

    // اعتبارسنجی
    if (empty($new_username)) $errors[] = "نام کاربری اجباری است.";
    if (empty($password)) $errors[] = "رمز عبور اجباری است.";
    if (empty($f_name) || empty($l_name)) $errors[] = "نام و نام خانوادگی اجباری است.";
    if (!is_numeric($balance)) $errors[] = "بالانس باید عدد باشد.";

    // چک کردن تکراری بودن نام کاربری
    $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE username = :username");
    $stmt->execute(['username' => $new_username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "این نام کاربری قبلاً ثبت شده است.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO Users (username, password, email, `F-Name`, `L-Name`, Phone, balance, verification_status, role, card_uid) 
                              VALUES (:username, :password, :email, :f_name, :l_name, :phone, :balance, :verification_status, :role, :card_uid)");
        $stmt->execute([
            'username' => $new_username,
            'password' => $hashed_password,
            'email' => $email ?: null,
            'f_name' => $f_name,
            'l_name' => $l_name,
            'phone' => $phone ?: null,
            'balance' => $balance,
            'verification_status' => $verification_status,
            'role' => $new_role,
            'card_uid' => $card_uid ?: null
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
    <title>افزودن کاربر - آستور</title>
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
        input, select {
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
                <a href="/site/admin/users.php" class="text-white hover:text-blue-300"><i class="fas fa-arrow-right text-lg"></i></a>
            </div>
        </header>

        <!-- محتوای اصلی -->
        <main class="flex-1 p-4 md:p-8 pt-20">
            <div class="card p-6 max-w-2xl mx-auto">
                <h2 class="text-xl font-semibold text-white mb-4"><i class="fas fa-user-plus mr-2 text-green-400"></i> افزودن کاربر</h2>
                
                <?php if ($success): ?>
                    <div class="success text-white">کاربر با موفقیت اضافه شد! <a href="/site/admin/users.php" class="underline">بازگشت به لیست</a></div>
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
                        <label class="block text-sm text-gray-300">نام کاربری</label>
                        <input type="text" name="username" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">رمز عبور</label>
                        <input type="password" name="password" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">ایمیل (اختیاری)</label>
                        <input type="email" name="email">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">نام</label>
                        <input type="text" name="f_name" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">نام خانوادگی</label>
                        <input type="text" name="l_name" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">تلفن (اختیاری)</label>
                        <input type="text" name="phone">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">بالانس (تومان)</label>
                        <input type="number" name="balance" value="0" step="1">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">وضعیت تأیید</label>
                        <select name="verification_status">
                            <option value="Not Verified">تأیید نشده</option>
                            <option value="Confirm">تأیید شده</option>
                            <option value="Rejected">رد شده</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">نقش</label>
                        <select name="role">
                            <option value="user">کاربر</option>
                            <option value="admin">ادمین</option>
                            <option value="hotelier">هتلی</option>
                            <option value="doctor">پزشک</option>
                            <option value="Moderator">مدراتور</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-300">شناسه کارت (اختیاری)</label>
                        <input type="text" name="card_uid">
                    </div>
                    <button type="submit" class="btn-action bg-green-500 text-white p-2 rounded-lg w-full hover:bg-green-600">ثبت کاربر</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>