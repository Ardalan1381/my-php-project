<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

// فعال کردن لاگ‌های PHP برای دیباگ
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// تنظیم مسیر لاگ‌ها
$log_dir = __DIR__ . '/../logs';
$log_file = $log_dir . '/php_errors.log';

// بررسی وجود پوشه logs و ایجاد آن در صورت عدم وجود
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// تنظیم مسیر لاگ
ini_set('error_log', $log_file);

function debug_log($message) {
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/php_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    if (is_writable($log_dir)) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $log_file);
    }
}

if (!isset($_SESSION['user_id'])) {
    debug_log("User not logged in");
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> لطفاً وارد شوید!</p>';
    header('Location: /site/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
debug_log("User ID: $user_id");

// بررسی وجود کاربر و موجودی
$stmt = $db->prepare("SELECT `F-Name`, `L-Name`, balance FROM Users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    debug_log("User not found for user_id: $user_id");
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> کاربر یافت نشد!</p>';
    header('Location: /site/login.php');
    exit;
}
debug_log("User found: " . json_encode($user));

// بارگذاری لیست پزشکان
try {
    $stmt = $db->prepare("SELECT doctor_id, name, specialty, location, fee, is_available FROM Doctors WHERE is_available = 1");
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log("Doctors fetched: " . count($doctors));
} catch (Exception $e) {
    debug_log("Error fetching doctors: " . $e->getMessage());
    $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در بارگذاری لیست پزشکان: ' . htmlspecialchars($e->getMessage()) . '</p>';
    header('Location: /site/dashboard.php');
    exit;
}

// پردازش فرم رزرو
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_doctor'])) {
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);

    // اعتبارسنجی ورودی‌ها
    if (!$doctor_id || !$appointment_date) {
        debug_log("Validation failed: Missing required fields");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> لطفاً همه فیلدها را پر کنید!</p>';
        header('Location: /site/book_doctor.php');
        exit;
    }

    // اعتبارسنجی فرمت تاریخ
    try {
        $appointment_datetime = new DateTime($appointment_date);
        $current_datetime = new DateTime();
        if ($appointment_datetime <= $current_datetime) {
            throw new Exception("نمی‌توانید برای گذشته وقت رزرو کنید!");
        }
        debug_log("Appointment datetime: " . $appointment_datetime->format('Y-m-d H:i:s'));
    } catch (Exception $e) {
        debug_log("Validation failed: " . $e->getMessage());
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> ' . htmlspecialchars($e->getMessage()) . '</p>';
        header('Location: /site/book_doctor.php');
        exit;
    }

    // پیدا کردن پزشک
    $stmt = $db->prepare("SELECT name, fee, is_available FROM Doctors WHERE doctor_id = :doctor_id");
    $stmt->execute(['doctor_id' => $doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor || !$doctor['is_available']) {
        debug_log("Doctor not found or not available: doctor_id=$doctor_id");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> پزشک مورد نظر یافت نشد یا در دسترس نیست!</p>';
        header('Location: /site/book_doctor.php');
        exit;
    }

    $total_price = $doctor['fee'];
    debug_log("Doctor found: " . json_encode($doctor) . ", Total price: $total_price");

    // دوباره بارگذاری $user برای اطمینان
    $stmt = $db->prepare("SELECT balance FROM Users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    debug_log("User reloaded before booking: " . json_encode($user));

    // بررسی موجودی کاربر
    if (!isset($user['balance']) || $user['balance'] < $total_price) {
        debug_log("Insufficient balance: user_balance=" . ($user['balance'] ?? 'undefined') . ", total_price=$total_price");
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> موجودی شما کافی نیست! موجودی فعلی: ' . number_format($user['balance'] ?? 0) . ' تومان</p>';
        header('Location: /site/book_doctor.php');
        exit;
    }

    // شروع تراکنش
    try {
        $db->beginTransaction();
        debug_log("Transaction started");

        // کسر موجودی کاربر
        $new_balance = $user['balance'] - $total_price;
        $stmt = $db->prepare("UPDATE Users SET balance = :new_balance WHERE user_id = :user_id");
        $stmt->execute(['new_balance' => $new_balance, 'user_id' => $user_id]);
        $updated_rows = $stmt->rowCount();
        debug_log("User balance update - Rows affected: $updated_rows, New balance: $new_balance, User ID: $user_id");

        // فقط اگه مقدار تغییر کرده و به‌روزرسانی انجام نشده خطا بندازیم
        if ($updated_rows === 0 && $user['balance'] != $new_balance) {
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM Users WHERE user_id = :user_id");
            $stmt_check->execute(['user_id' => $user_id]);
            $user_exists = $stmt_check->fetchColumn();
            if ($user_exists == 0) {
                throw new Exception("کاربر با شناسه $user_id وجود ندارد!");
            }
            throw new Exception("به‌روزرسانی موجودی کاربر انجام نشد! ممکن است مشکلی در دیتابیس باشد.");
        }

        // ثبت تراکنش
        $description = "رزرو وقت پزشک - ID: $doctor_id";
        $stmt = $db->prepare("INSERT INTO Wallet_Transactions (user_id, amount, transaction_type, description) 
                              VALUES (:user_id, :amount, 'BOOKING', :description)");
        $stmt->execute([
            'user_id' => $user_id,
            'amount' => -$total_price,
            'description' => $description
        ]);
        $inserted_rows = $stmt->rowCount();
        debug_log("Transaction insert - Rows affected: $inserted_rows");
        if ($inserted_rows === 0) {
            throw new Exception("ثبت تراکنش انجام نشد!");
        }

        // ثبت رزرو
        $stmt = $db->prepare("INSERT INTO Medical_Reservations (user_id, doctor_id, total_price, appointment_date, status) 
                              VALUES (:user_id, :doctor_id, :total_price, :appointment_date, 'Pending')");
        $stmt->execute([
            'user_id' => $user_id,
            'doctor_id' => $doctor_id,
            'total_price' => $total_price,
            'appointment_date' => $appointment_datetime->format('Y-m-d H:i:s')
        ]);
        $inserted_rows = $stmt->rowCount();
        debug_log("Doctor reservation insert - Rows affected: $inserted_rows");
        if ($inserted_rows === 0) {
            throw new Exception("ثبت رزرو انجام نشد!");
        }

        $db->commit();
        debug_log("Transaction committed successfully");
        $_SESSION['success_message'] = '<p class="text-green-400 bg-green-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-green-700"><i class="fas fa-check-circle mr-2"></i> رزرو وقت پزشک با موفقیت انجام شد! تاریخ وقت: ' . $appointment_datetime->format('Y-m-d H:i') . '</p>';
        header('Location: /site/dashboard_reservations.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        debug_log("Transaction failed: " . $e->getMessage());
        $_SESSION['error_message'] = '<p class="text-red-400 bg-red-900 bg-opacity-20 p-4 rounded-lg mb-6 flex items-center text-sm sm:text-base border border-red-700"><i class="fas fa-exclamation-circle mr-2"></i> خطا در رزرو وقت پزشک: ' . htmlspecialchars($e->getMessage()) . '</p>';
        header('Location: /site/book_doctor.php');
        exit;
    }
}

// لاگ‌گذاری مقدار $user['balance'] قبل از نمایش
debug_log("Balance before rendering: " . ($user['balance'] ?? 'undefined'));

// هدر برای جلوگیری از کش
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// هدر صفحه
$page_title = "رزرو وقت پزشک - آستور";
include __DIR__ . '/header.php';

// لاگ‌گذاری بعد از هدر
debug_log("Balance after header: " . ($user['balance'] ?? 'undefined'));

?>

<main class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pt-20">
    <div class="bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-md border border-gray-700">
        <h2 class="text-xl sm:text-2xl font-semibold text-white mb-6"><i class="fas fa-user-md mr-2 text-blue-400"></i> رزرو وقت پزشک</h2>

        <!-- نمایش پیام‌های موفقیت یا خطا -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="animate-fade-in">
                <?php echo $_SESSION['success_message']; ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="animate-fade-in">
                <?php echo $_SESSION['error_message']; ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- نمایش موجودی کاربر -->
        <div class="mb-6 p-4 bg-gray-700 bg-opacity-50 rounded-lg">
            <p class="text-gray-300 text-sm sm:text-base"><strong>موجودی شما:</strong> <?php echo number_format($user['balance'] ?? 0) . ' تومان'; ?></p>
        </div>

        <!-- فیلتر پزشکان -->
        <div class="mb-6 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
            <select id="filter-specialty" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                <option value="all">همه تخصص‌ها</option>
                <?php
                $specialties = array_unique(array_column($doctors, 'specialty'));
                foreach ($specialties as $specialty) {
                    echo '<option value="' . htmlspecialchars($specialty) . '">' . htmlspecialchars($specialty) . '</option>';
                }
                ?>
            </select>
            <select id="filter-location" class="p-3 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base">
                <option value="all">همه مکان‌ها</option>
                <?php
                $locations = array_unique(array_column($doctors, 'location'));
                foreach ($locations as $location) {
                    echo '<option value="' . htmlspecialchars($location) . '">' . htmlspecialchars($location) . '</option>';
                }
                ?>
            </select>
        </div>

        <!-- لیست پزشکان -->
        <div id="doctors-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($doctors)): ?>
                <div class="col-span-full text-center p-6 bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg rounded-xl shadow-md border border-gray-700">
                    <i class="fas fa-user-md text-gray-400 text-3xl sm:text-4xl mb-4"></i>
                    <p class="text-gray-300 text-base sm:text-lg">پزشکی در دسترس نیست!</p>
                    <a href="/site/dashboard.php" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base">بازگشت به داشبورد</a>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-item bg-gray-800 bg-opacity-80 backdrop-filter backdrop-blur-lg p-4 sm:p-6 rounded-xl shadow-md border border-gray-700 hover:shadow-lg transition-all duration-300" 
                         data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>" 
                         data-location="<?php echo htmlspecialchars($doctor['location']); ?>">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-4 sm:space-y-0">
                            <div class="flex items-center space-x-4">
                                <i class="fas fa-user-md text-blue-400 text-xl sm:text-2xl"></i>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-semibold text-white"><?php echo htmlspecialchars($doctor['name']); ?></h3>
                                    <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">تخصص:</strong> <?php echo htmlspecialchars($doctor['specialty']); ?></p>
                                    <p class="text-gray-300 text-sm sm:text-base"><strong class="hidden sm:inline">مکان:</strong> <?php echo htmlspecialchars($doctor['location']); ?></p>
                                    <p class="text-blue-400 font-semibold text-base sm:text-lg"><?php echo number_format($doctor['fee']) . ' تومان'; ?></p>
                                </div>
                            </div>
                            <div class="text-left sm:text-right space-y-2">
                                <form method="POST" action="/site/book_doctor.php">
                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['doctor_id']; ?>">
                                    <input type="datetime-local" name="appointment_date" 
                                           min="<?php echo date('Y-m-d\TH:i', strtotime('+1 minute')); ?>" 
                                           class="p-2 border border-gray-600 bg-gray-700 text-white rounded-lg text-sm sm:text-base mb-2" required>
                                    <button type="submit" name="book_doctor" 
                                            class="bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600 transition-all duration-300 text-sm sm:text-base">
                                        <i class="fas fa-calendar-check mr-2"></i> رزرو وقت
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- لاگ‌گذاری قبل از فوتر -->
<?php debug_log("Balance before footer: " . ($user['balance'] ?? 'undefined')); ?>

<script>
document.getElementById('filter-specialty').addEventListener('change', filterDoctors);
document.getElementById('filter-location').addEventListener('change', filterDoctors);

function filterDoctors() {
    const specialtyFilter = document.getElementById('filter-specialty').value;
    const locationFilter = document.getElementById('filter-location').value;
    const doctors = document.querySelectorAll('.doctor-item');

    doctors.forEach(doctor => {
        const specialty = doctor.getAttribute('data-specialty');
        const location = doctor.getAttribute('data-location');
        const specialtyMatch = (specialtyFilter === 'all' || specialty === specialtyFilter);
        const locationMatch = (locationFilter === 'all' || location === locationFilter);

        if (specialtyMatch && locationMatch) {
            doctor.style.display = 'block';
        } else {
            doctor.style.display = 'none';
        }
    });
}
</script>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in;
    }
</style>

<?php include __DIR__ . '/footer.php'; ?>