<?php
session_start();
require_once __DIR__ . '/includes/db.php';
$page_title = "انتخاب پنل - آستور";
include __DIR__ . '/header.php';

// بررسی ورود کاربر و نقش او
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    error_log("Session not set - Redirecting to login.php. Session: " . print_r($_SESSION, true));
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
error_log("Choose panel - User ID: $user_id, Role: $role");

// دریافت اطلاعات کاربر
$stmt = $db->prepare("SELECT username FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    error_log("User not found - User ID: $user_id, Role: $role");
    echo "خطا: کاربر پیدا نشد! user_id: " . $user_id . ", role: " . $role;
    exit;
}

// هدایت خودکار بر اساس نقش
switch ($role) {
    case 'admin':
    case 'Moderator':
        error_log("Role is admin/Moderator - Showing panel selection");
        // نمایش صفحه انتخاب پنل برای ادمین و Moderator
        break;
    case 'hospital':
        error_log("Redirecting hospital to /site/hospitalsmanager/manage_hospitals.php");
        header("Location: /site/hospitalsmanager/manage_hospitals.php");
        exit;
    case 'doctor':
        error_log("Redirecting doctor to /site/doctorsmanager/manage_doctors.php");
        header("Location: /site/doctorsmanager/manage_doctors.php");
        exit;
    case 'hotelier':
        error_log("Redirecting hotelier to /site/hotelmanager/manage_hotels.php");
        header("Location: /site/hotelmanager/manage_hotels.php");
        exit;
    default:
        error_log("Redirecting default to /site/dashboard.php");
        header("Location: /site/dashboard.php");
        exit;
}
?>

<main class="container mx-auto p-6">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md mx-auto">
        <h2 class="text-2xl font-semibold text-blue-900 mb-6">خوش آمدید، <?php echo htmlspecialchars($user['username']); ?>!</h2>
        <p class="text-gray-700 mb-6">لطفاً پنل مورد نظر خود را انتخاب کنید:</p>
        <div class="flex flex-col gap-4">
            <?php if (in_array($role, ['admin', 'Moderator'])): ?>
                <a href="/site/admin/index.php" class="btn bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">پنل مدیریت</a>
            <?php endif; ?>
            <a href="/site/dashboard.php" class="btn bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">پنل کاربر</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>