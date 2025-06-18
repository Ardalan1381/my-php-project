<?php
require_once __DIR__ . '/includes/db.php';
$page_title = "داشبورد - آستور";
include __DIR__ . '/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /site/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = $db->prepare("SELECT `F-Name`, `L-Name`, Phone, balance, video_path, verification_status FROM Users WHERE user_id = :user_id");
$user->execute(['user_id' => $user_id]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if ($user['verification_status'] == 'Rejected') {
    echo '<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20"><p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg border border-red-700">حساب شما رد شده است. لطفاً با پشتیبانی تماس بگیرید: 021-12345678</p></main>';
    include __DIR__ . '/footer.php';
    exit;
} elseif ($user['verification_status'] == 'Not Verified') {
    header("Location: /site/profile.php");
    exit;
}

// Redeem Code
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_code'])) {
    $code = filter_input(INPUT_POST, 'redeem_code', FILTER_SANITIZE_STRING);
    $stmt = $db->prepare("SELECT code_id, amount FROM Redeem_Codes WHERE code = :code AND used_by IS NULL");
    $stmt->execute(['code' => $code]);
    $redeem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($redeem) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE Users SET balance = balance + :amount WHERE user_id = :user_id")
               ->execute(['amount' => $redeem['amount'], 'user_id' => $user_id]);
            $db->prepare("UPDATE Redeem_Codes SET used_by = :user_id, used_at = NOW() WHERE code_id = :code_id")
               ->execute(['user_id' => $user_id, 'code_id' => $redeem['code_id']]);
            $db->prepare("INSERT INTO Wallet_Transactions (user_id, amount, transaction_type, description) 
                          VALUES (:user_id, :amount, 'REDEEM', :description)")
               ->execute(['user_id' => $user_id, 'amount' => $redeem['amount'], 'description' => "استفاده از کد تخفیف $code"]);
            $db->commit();
            $success = "کد با موفقیت اعمال شد! مبلغ " . number_format($redeem['amount']) . " تومان به حساب شما اضافه شد.";
            $user['balance'] += $redeem['amount'];
        } catch (Exception $e) {
            $db->rollBack();
            $error = "خطایی رخ داد: " . $e->getMessage();
        }
    } else {
        $error = "کد نامعتبر است یا قبلاً استفاده شده!";
    }
}
?>
<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
    <!-- هدر داشبورد -->
    <section class="bg-gradient-to-r from-gray-900 to-blue-900 text-white p-6 sm:p-8 rounded-xl shadow-lg mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold animate-fade-in"><?php echo htmlspecialchars($user['F-Name']); ?> خوش آمدید!</h1>
        <p class="text-base sm:text-lg mt-2">اینجا همه‌چیز برای مدیریت حساب شماست.</p>
    </section>

    <!-- اعلان‌ها -->
    <?php if (isset($success)): ?>
        <p class="text-green-400 bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-green-700"><i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?></p>
    <?php elseif (isset($error)): ?>
        <p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?></p>
    <?php endif; ?>

    <!-- اطلاعات کاربر -->
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md mb-6 border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-4"><i class="fas fa-user-circle mr-2 text-blue-400"></i> اطلاعات حساب</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <p class="text-sm sm:text-base text-gray-300"><span class="font-medium">نام:</span> <?php echo htmlspecialchars($user['F-Name']); ?></p>
            <p class="text-sm sm:text-base text-gray-300"><span class="font-medium">نام خانوادگی:</span> <?php echo htmlspecialchars($user['L-Name']); ?></p>
            <p class="text-sm sm:text-base text-gray-300"><span class="font-medium">شماره تلفن:</span> <?php echo '0' . htmlspecialchars($user['Phone']); ?></p>
            <p class="text-sm sm:text-base text-gray-300"><span class="font-medium">موجودی:</span> <span class="text-green-400"><?php echo number_format($user['balance'], 2); ?> تومان</span></p>
            <p class="text-sm sm:text-base text-gray-300"><span class="font-medium">وضعیت:</span> <span class="text-green-400">تأیید شده</span></p>
        </div>
        <a href="/site/profile.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-edit mr-2"></i> ویرایش پروفایل</a>
    </div>

    <!-- کد تخفیف -->
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md mb-6 border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-4"><i class="fas fa-gift mr-2 text-blue-400"></i> شارژ کیف پول</h2>
        <form method="POST" class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
            <input type="text" name="redeem_code" class="w-full sm:w-2/3 p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="کد تخفیف را وارد کنید" required>
            <button type="submit" class="w-full sm:w-auto bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-check mr-2"></i> اعمال</button>
        </form>
    </div>

    <!-- تاریخچه و رزروها -->
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-4"><i class="fas fa-history mr-2 text-blue-400"></i> مدیریت رزروها و تاریخچه</h2>
        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4 mb-6">
            <!-- دکمه‌های تاریخچه -->
            <a href="/site/dashboard_reservations.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-hotel mr-2"></i> رزروها</a>
            <a href="/site/dashboard_transactions.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-wallet mr-2"></i> تراکنش‌ها</a>
            <!-- دکمه‌های جدید برای رزرو پزشک و بیمارستان -->
            <a href="/site/book_doctor.php" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-stethoscope mr-2"></i> رزرو پزشک</a>
            <a href="/site/book_hospital.php" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition-all duration-300 text-sm sm:text-base"><i class="fas fa-hospital mr-2"></i> رزرو بیمارستان</a>
        </div>
    </div>
</main>
<style>
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .animate-fade-in { animation: fadeIn 1s ease-in; }
</style>
<?php include __DIR__ . '/footer.php'; ?>