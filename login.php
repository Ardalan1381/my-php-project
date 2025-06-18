<?php
session_start();
require_once __DIR__ . '/includes/db.php';
$page_title = "ورود - آستور";
include __DIR__ . '/header.php';

// اگر کاربر قبلاً وارد شده، به صفحه مناسب هدایت بشه
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    error_log("User already logged in, role: $role"); // لاگ
    switch ($role) {
        case 'admin':
        case 'Moderator':
            header("Location: /site/choose_panel.php");
            break;
        case 'hospital':
            error_log("Redirecting hospital to /site/hospitalsmanager/manage_hospitals.php");
            header("Location: /site/hospitalsmanager/manage_hospitals.php");
            break;
        case 'doctor':
            error_log("Redirecting doctor to /site/doctorsmanager/manage_doctors.php");
            header("Location: /site/doctorsmanager/manage_doctors.php");
            break;
        case 'hotelier':
            error_log("Redirecting hotelier to /site/hotelmanager/manage_hotels.php");
            header("Location: /site/hotelmanager/manage_hotels.php");
            break;
        default:
            error_log("Redirecting default to /site/dashboard.php");
            header("Location: /site/dashboard.php");
            break;
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_STRING); // نام کاربری یا ایمیل
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    // بررسی کاربر در دیتابیس
    $stmt = $db->prepare("SELECT user_id, username, password, role FROM Users WHERE username = :identifier OR email = :identifier");
    $stmt->execute(['identifier' => $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // ذخیره اطلاعات کاربر در سشن
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        error_log("Login successful, role: " . $user['role']); // لاگ

        // هدایت بر اساس نقش
        switch ($user['role']) {
            case 'admin':
            case 'Moderator':
                error_log("Redirecting admin/Moderator to /site/choose_panel.php");
                header("Location: /site/choose_panel.php");
                break;
            case 'hospital':
                error_log("Redirecting hospital to /site/hospitalsmanager/manage_hospitals.php");
                header("Location: /site/hospitalsmanager/manage_hospitals.php");
                break;
            case 'doctor':
                error_log("Redirecting doctor to /site/doctorsmanager/manage_doctors.php");
                header("Location: /site/doctorsmanager/manage_doctors.php");
                break;
            case 'hotelier':
                error_log("Redirecting hotelier to /site/hotelmanager/manage_hotels.php");
                header("Location: /site/hotelmanager/manage_hotels.php");
                break;
            default:
                error_log("Redirecting default to /site/dashboard.php");
                header("Location: /site/dashboard.php");
                break;
        }
        exit;
    } else {
        $error = "نام کاربری/ایمیل یا رمز عبور اشتباه است!";
        error_log("Login failed for identifier: $identifier");
    }
}
?>

<main class="container mx-auto p-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-8 rounded-lg shadow-md max-w-md mx-auto border border-gray-700">
        <h2 class="text-2xl font-semibold text-white mb-6">ورود</h2>
        <?php if (isset($error)): ?>
            <p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 border border-red-700"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">نام کاربری یا ایمیل</label>
                <input type="text" name="identifier" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">رمز عبور</label>
                <input type="password" name="password" class="w-full p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <button type="submit" class="btn bg-blue-500 text-white px-6 py-3 rounded-lg w-full hover:bg-blue-600 transition-all duration-300"><i class="fas fa-sign-in-alt mr-2"></i> ورود</button>
        </form>
        <p class="mt-4 text-center text-gray-300">حساب ندارید؟ <a href="/site/register.php" class="text-blue-400 hover:text-blue-300 transition-all duration-300">ثبت‌نام کنید</a></p>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>