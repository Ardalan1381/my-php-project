<?php
require_once __DIR__ . '/includes/db.php';
$page_title = "ثبت‌نام - آستور";
include __DIR__ . '/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: /site/dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = filter_input(INPUT_POST, 'fname', FILTER_SANITIZE_STRING);
    $lname = filter_input(INPUT_POST, 'lname', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $birth_date = filter_input(INPUT_POST, 'birth_date', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING);

    // چک کردن اینکه رمز عبور و تأییدش یکی باشن
    if ($password !== $confirm_password) {
        $error = "رمز عبور و تأیید رمز عبور یکسان نیستند!";
    } else {
        // چک کردن تکراری بودن تلفن یا یوزرنیم
        $stmt = $db->prepare("SELECT user_id FROM Users WHERE Phone = :phone OR username = :username");
        $stmt->execute([
            'phone' => ltrim($phone, '0'),
            'username' => $username
        ]);
        if ($stmt->fetch()) {
            $error = "شماره تلفن یا نام کاربری قبلاً ثبت شده است!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO Users (`F-Name`, `L-Name`, username, Phone, email, birth_date, password, verification_status) 
                                  VALUES (:fname, :lname, :username, :phone, :email, :birth_date, :password, 'Not Verified')");
            $stmt->execute([
                'fname' => $fname,
                'lname' => $lname,
                'username' => $username,
                'phone' => ltrim($phone, '0'),
                'email' => $email,
                'birth_date' => $birth_date,
                'password' => $hashed_password
            ]);
            $success = "ثبت‌نام با موفقیت انجام شد! لطفاً وارد شوید.";
        }
    }
}
?>
<main class="container mx-auto p-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-8 rounded-lg shadow-md max-w-md mx-auto border border-gray-700">
        <h2 class="text-2xl font-semibold text-white mb-6">ثبت‌نام</h2>
        <?php if (isset($success)): ?>
            <p class="text-green-400 bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 border border-green-700"><?php echo $success; ?></p>
        <?php elseif (isset($error)): ?>
            <p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 border border-red-700"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">نام</label>
                <input type="text" name="fname" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">نام خانوادگی</label>
                <input type="text" name="lname" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">نام کاربری</label>
                <input type="text" name="username" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">شماره تلفن</label>
                <input type="text" name="phone" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="+12025550123 یا 09123456789" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">ایمیل</label>
                <input type="email" name="email" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">تاریخ تولد</label>
                <input type="date" name="birth_date" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">رمز عبور</label>
                <input type="password" name="password" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">تأیید رمز عبور</label>
                <input type="password" name="confirm_password" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <button type="submit" class="btn bg-blue-500 text-white px-6 py-3 rounded-lg w-full hover:bg-blue-600 transition-all duration-300"><i class="fas fa-user-plus mr-2"></i> ثبت‌نام</button>
        </form>
        <p class="mt-4 text-center text-gray-300">حساب دارید؟ <a href="/site/login.php" class="text-blue-400 hover:text-blue-300 transition-all duration-300">وارد شوید</a></p>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>